<?php
require_once '../vendor/autoload.php';
require_once '../function.php';
require_once '../database.php';
require_once '../functions/notifications.php';

// Start the session
session_start();

// Get user info from Auth0
$auth0 = new Auth0\SDK\Auth0([
    'domain' => 'fayyaztravels.us.auth0.com',
    'clientId' => 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    'clientSecret' => 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    'redirectUri' => 'https://crm.fayyaz.travel/callback.php',
    'cookieSecret' => 'your-secret-key-here'
]);

try {
    $user = $auth0->getUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Get user data from database
    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found in database']);
        exit;
    }

    // Get POST data (handle both multipart/form-data and application/json)
    if (!empty($_POST)) {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }

    if (empty($data) && empty($_FILES)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit;
    }

    // Validate ticket type
    if (!isset($data['ticket_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ticket type is required']);
        exit;
    }

    // Every ticket must have a booking/reference number
    if (empty($data['booking_reference'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Booking/Reference number is required']);
        exit;
    }

    // Trim booking reference
    $bookingReference = trim($data['booking_reference']);

    // Common fields for all tickets
    $commonFields = [
        'user_id' => $dbUser['id'],
        'owner_id' => $data['owner_id'] ?? $dbUser['id'],
        'booking_reference' => $bookingReference,
        'priority' => $data['priority'] ?? 'MEDIUM',
        'status' => ($data['ticket_type'] === 'refund' || ($data['ticket_type'] === 'general' && isset($data['ticket_subtype']) && $data['ticket_subtype'] === 'Customers Refund')) ? 'SUBMITTED' : 'OPEN',
        'created_at' => date('Y-m-d H:i:s'),
        'expected_timeline' => !empty($data['expected_timeline']) ? $data['expected_timeline'] : null
    ];

    // Handle different ticket types
    switch ($data['ticket_type']) {
        case 'estimate':
            // Validate required fields
            $requiredFields = [
                'customer_name',
                'billing_address',
                'email',
                'contact_number',
                'consultant_name',
                'service_date',
                'package_details',
                'number_of_persons',
                'rate_per_person',
                'total_amount',
                'description'
            ];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            // Insert into estimate_tickets table
            $ticketData = array_merge($commonFields, [
                'customer_name' => $data['customer_name'],
                'billing_address' => $data['billing_address'],
                'email' => $data['email'],
                'contact_number' => $data['contact_number'],
                'consultant_name' => $data['consultant_name'],
                'service_date' => $data['service_date'],
                'package_details' => $data['package_details'],
                'number_of_persons' => $data['number_of_persons'],
                'rate_per_person' => $data['rate_per_person'],
                'total_amount' => $data['total_amount'],
                'description' => $data['description'],
                'estimate_message' => $data['estimate_message'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null
            ]);

            $result = $database->insert('estimate_tickets', $ticketData);
            break;

        case 'supplier':
            // Validate required text fields
            $requiredFields = [
                'supplier_id',
                'travel_date',
                'due_date',
                'payment_type',
                'bank_details',
                'complete_costing'
            ];

            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            // Validate required files
            if (empty($_FILES['supplier_invoice']) || empty($_FILES['customer_invoice']) || empty($_FILES['payment_proof'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Supplier Invoice, Customer Invoice, and Payment Proof are all required.']);
                exit;
            }

            // Handle file uploads
            $supplierInvoicePath = handleFileUpload($_FILES['supplier_invoice'], 'supplier_invoices');
            $customerInvoicePath = handleFileUpload($_FILES['customer_invoice'], 'customer_invoices');
            $paymentProofPath = handleFileUpload($_FILES['payment_proof'], 'payment_proofs');

            $ticketData = array_merge($commonFields, [
                'supplier_id' => $data['supplier_id'],
                'travel_date' => $data['travel_date'],
                'due_date' => $data['due_date'],
                'supplier_invoice_currency' => '',
                'supplier_local_currency' => '',
                'payment_type' => $data['payment_type'],
                'bank_details' => $data['bank_details'],
                'complete_costing' => $data['complete_costing'],
                'supplier_invoice_path' => $supplierInvoicePath,
                'customer_invoice_path' => $customerInvoicePath,
                'payment_proof_path' => $paymentProofPath,
                'supplier_message' => $data['supplier_message'] ?? null
            ]);

            $result = $database->insert('supplier_tickets', $ticketData);
            break;

        case 'invoice':
            if (empty($data['client_type'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Client Type is required']);
                exit;
            }

            $details = "<h4>Actual Invoice Request</h4>";
            $details .= "<p><strong>Client Type:</strong> " . htmlspecialchars($data['client_type']) . "</p>";
            
            if ($data['client_type'] === 'Individual') {
                if (empty($_FILES['payment_proof'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Payment proof is required for individual clients']);
                    exit;
                }
                $details .= "<p><strong>Boss Confirmation:</strong> " . (!empty($data['boss_confirmation']) ? 'Yes' : 'No') . "</p>";
            } elseif ($data['client_type'] === 'New Corporate') {
                if (empty($_FILES['contract_copy'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Contract copy is required for new corporate clients']);
                    exit;
                }
                $details .= "<p><strong>Boss Approval:</strong> " . (!empty($data['boss_approval']) ? 'Yes' : 'No') . "</p>";
            }
            if (!empty($data['description'])) {
                $details .= "<h5>Additional Details:</h5><div>" . $data['description'] . "</div>";
            }

            if (isset($_FILES['payment_proof'])) {
                $path = handleFileUpload($_FILES['payment_proof'], 'payment_proofs');
                if ($path) $details .= "<p><strong>Payment Proof:</strong> <a href='uploads/payment_proofs/{$path}' target='_blank'>View File</a></p>";
            }
            if (isset($_FILES['contract_copy'])) {
                $path = handleFileUpload($_FILES['contract_copy'], 'contracts');
                if ($path) $details .= "<p><strong>Contract Copy:</strong> <a href='uploads/contracts/{$path}' target='_blank'>View File</a></p>";
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Convert Estimate to Invoice'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'purchase_order':
            $requiredFields = ['supplier_id', 'client_name', 'from_destination', 'to_destination', 'po_date', 'quantity', 'rate', 'description'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            $supplier = $database->get('suppliers', ['name'], ['id' => $data['supplier_id']]);

            $details = "<h4>Purchase Order Request</h4>";
            $details .= "<p><strong>Supplier Name:</strong> " . htmlspecialchars($supplier['name'] ?? 'Unknown') . "</p>";
            $details .= "<p><strong>Client Name:</strong> " . htmlspecialchars($data['client_name']) . "</p>";
            $details .= "<p><strong>From (Destination):</strong> " . htmlspecialchars($data['from_destination']) . "</p>";
            $details .= "<p><strong>To (Destination):</strong> " . htmlspecialchars($data['to_destination']) . "</p>";
            $details .= "<p><strong>PO Date:</strong> " . htmlspecialchars($data['po_date']) . "</p>";
            $details .= "<p><strong>Quantity:</strong> " . htmlspecialchars($data['quantity']) . "</p>";
            $details .= "<p><strong>Rate:</strong> " . htmlspecialchars($data['rate']) . "</p>";
            $details .= "<p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($data['description'])) . "</p>";
            if (!empty($data['supplier_instructions'])) {
                $details .= "<h5>Supplier Instructions:</h5><div>" . $data['supplier_instructions'] . "</div>";
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Purchase Order Request'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'payment_link':
            $requiredFields = ['client_name', 'email', 'phone', 'country', 'amount'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            $details = "<h4>Payment Link Request</h4>";
            $details .= "<p><strong>Client Name:</strong> " . htmlspecialchars($data['client_name']) . "</p>";
            $details .= "<p><strong>Email Address:</strong> " . htmlspecialchars($data['email']) . "</p>";
            $details .= "<p><strong>Phone Number:</strong> " . htmlspecialchars($data['phone']) . "</p>";
            $details .= "<p><strong>Country:</strong> " . htmlspecialchars($data['country']) . "</p>";
            $details .= "<p><strong>Amount:</strong> " . htmlspecialchars($data['amount']) . "</p>";
            if (!empty($data['inclusive_charges'])) {
                $details .= "<p><strong>Inclusive Charges:</strong> " . htmlspecialchars($data['inclusive_charges']) . "</p>";
            }
            $details .= "<p><strong>Platform Preference:</strong> " . htmlspecialchars($data['platform'] ?? 'Any') . "</p>";

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Create Payment Link'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'amex_payment':
            if (empty($_FILES['amex_form'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Completed AMEX Form is required']);
                exit;
            }

            $details = "<h4>Amex Credit Card Payment Request</h4>";
            if (!empty($data['description'])) {
                $details .= "<h5>Additional Details:</h5><div>" . $data['description'] . "</div>";
            }

            if (isset($_FILES['amex_form'])) {
                $path = handleFileUpload($_FILES['amex_form'], 'amex_forms');
                if ($path) $details .= "<p><strong>Completed AMEX Form:</strong> <a href='uploads/amex_forms/{$path}' target='_blank'>View File</a></p>";
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Payment from Amex Card (CC)'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'refund':
            $requiredFields = ['reason', 'customer_bank_details'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            if (empty($_FILES['customer_invoice']) || empty($_FILES['payment_proof']) || empty($_FILES['supplier_invoice']) || empty($_FILES['supplier_credit_note'])) {
                http_response_code(400);
                echo json_encode(['error' => 'All required documents (Customer Invoice, Payment Proof, Supplier Invoice, and Credit Note) must be uploaded.']);
                exit;
            }

            if (empty($data['policy_confirmation'])) {
                http_response_code(400);
                echo json_encode(['error' => 'You must confirm the 14-day refund policy.']);
                exit;
            }

            $details = "<h4>Refund Request</h4>";
            $details .= "<p><strong>Reason for Refund:</strong><br>" . nl2br(htmlspecialchars($data['reason'])) . "</p>";
            $details .= "<p><strong>Customer Bank Details:</strong><br>" . nl2br(htmlspecialchars($data['customer_bank_details'])) . "</p>";
            $details .= "<p><strong>14-day policy confirmation:</strong> Yes</p>";

            $fileLabels = [
                'customer_invoice' => 'Customer Invoice',
                'payment_proof' => 'Payment Proof',
                'supplier_invoice' => 'Supplier Invoice',
                'supplier_credit_note' => 'Supplier Credit Note / Refund Confirmation'
            ];

            foreach ($fileLabels as $key => $label) {
                if (isset($_FILES[$key])) {
                    $path = handleFileUpload($_FILES[$key], 'refund_docs');
                    if ($path) $details .= "<p><strong>$label:</strong> <a href='uploads/refund_docs/{$path}' target='_blank'>View File</a></p>";
                }
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Customers Refund'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'offboarding':
            $requiredCheckboxes = ['handover_estimates', 'handover_payments', 'handover_receivables', 'tracker_updated'];
            foreach ($requiredCheckboxes as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "All offboarding checklist items must be completed."]);
                    exit;
                }
            }

            $details = "<h4>Employee Offboarding Checklist</h4>";
            $details .= "<ul>";
            $details .= "<li><strong>Handover Estimates:</strong> " . (!empty($data['handover_estimates']) ? 'Completed' : 'Pending') . "</li>";
            $details .= "<li><strong>Handover Payments:</strong> " . (!empty($data['handover_payments']) ? 'Completed' : 'Pending') . "</li>";
            $details .= "<li><strong>Handover Receivables:</strong> " . (!empty($data['handover_receivables']) ? 'Completed' : 'Pending') . "</li>";
            $details .= "<li><strong>Tracker Updated:</strong> " . (!empty($data['tracker_updated']) ? 'Completed' : 'Pending') . "</li>";
            $details .= "</ul>";
            
            if (!empty($data['description'])) {
                $details .= "<h5>Additional Handover Notes:</h5><div>" . $data['description'] . "</div>";
            }

            if (!empty($data['handover_user_id'])) {
                $handoverUser = $database->get('users', ['name'], ['id' => $data['handover_user_id']]);
                if ($handoverUser) {
                    $details .= "<p><strong>Assigned Handover TC:</strong> " . htmlspecialchars($handoverUser['name']) . "</p>";
                }
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Employee Offboarding'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'payment_update':
            if (empty($_FILES['payment_proof'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Payment proof is required']);
                exit;
            }

            $details = "<h4>Update Payments in QB</h4>";
            if (!empty($data['estimate_no'])) $details .= "<p><strong>Estimate No:</strong> " . htmlspecialchars($data['estimate_no']) . "</p>";
            if (!empty($data['invoice_no'])) $details .= "<p><strong>Invoice No:</strong> " . htmlspecialchars($data['invoice_no']) . "</p>";
            
            $path = handleFileUpload($_FILES['payment_proof'], 'payment_proofs');
            if ($path) $details .= "<p><strong>Payment Proof:</strong> <a href='uploads/payment_proofs/{$path}' target='_blank'>View File</a></p>";
            
            if (!empty($data['description'])) {
                $details .= "<h5>Additional Details:</h5><div>" . $data['description'] . "</div>";
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Updating Payments in QB and giving paid invoice to sales team'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'modify_ticket':
            if (empty($data['modification_reason'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Reason for modification is required']);
                exit;
            }

            $details = "<h4>Modify Estimates/Invoices</h4>";
            if (!empty($data['estimate_no'])) $details .= "<p><strong>Estimate No:</strong> " . htmlspecialchars($data['estimate_no']) . "</p>";
            if (!empty($data['invoice_no'])) $details .= "<p><strong>Invoice No:</strong> " . htmlspecialchars($data['invoice_no']) . "</p>";
            $details .= "<p><strong>Reason for Modification:</strong><br>" . nl2br(htmlspecialchars($data['modification_reason'])) . "</p>";

            if (isset($_FILES['supporting_image'])) {
                $path = handleFileUpload($_FILES['supporting_image'], 'supporting_images');
                if ($path) $details .= "<p><strong>Supporting Image:</strong> <a href='uploads/supporting_images/{$path}' target='_blank'>View File</a></p>";
            }

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Modification in the estimates/invoices if any new changes coming from customer'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'general':
            // Validate required fields
            if (!isset($data['description']) || empty($data['description'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Description is required']);
                exit;
            }

            // Handle file upload
            $supportingImagePath = null;
            if (isset($_FILES['supporting_image'])) {
                $supportingImagePath = handleFileUpload($_FILES['supporting_image'], 'supporting_images');
            }

            // Insert into general_tickets table
            $ticketData = array_merge($commonFields, [
                'description' => $data['description'],
                'supporting_image_path' => $supportingImagePath,
                'ticket_subtype' => $data['ticket_subtype'] ?? null
            ]);

            $result = $database->insert('general_tickets', $ticketData);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ticket type']);
            exit;
    }

    if ($result) {
        // Get the last inserted ID
        $ticketId = $database->id();

        // Format the current date
        $currentDateTime = date('Y-m-d H:i:s');
        $formattedDate = date('F j, Y', strtotime($currentDateTime));

        // Add initial comment for ticket creation with username and formatted date
        $database->insert('ticket_comments', [
            'ticket_id' => $ticketId,
            'ticket_type' => $data['ticket_type'],
            'user_id' => $dbUser['id'],
            'comment' => sprintf('Ticket created by %s on %s with Booking Ref: %s', $dbUser['name'], $formattedDate, $bookingReference),
            'created_at' => $currentDateTime
        ]);

        // Create notification for ticket creation
        notifyTicketCreation($dbUser['id'], $ticketId, $data['ticket_type']);

        // If user is not admin, also notify admins
        if (!isset($dbUser['is_admin']) || $dbUser['is_admin'] != 1) {
            $admins = $database->select('users', ['id'], ['is_admin' => 1]);
            if ($admins) {
                foreach ($admins as $admin) {
                    notifyTicketCreation($admin['id'], $ticketId, $data['ticket_type']);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Ticket created successfully',
            'ticket_id' => $ticketId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create ticket']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// Helper function to handle file uploads
function handleFileUpload($file, $directory)
{
    $uploadDir = "../uploads/$directory/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }
    return null;
}
