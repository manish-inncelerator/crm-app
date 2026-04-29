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

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
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

    // Common fields for all tickets
    $commonFields = [
        'user_id' => $dbUser['id'],
        'priority' => $data['priority'],
        'status' => 'OPEN',
        'created_at' => date('Y-m-d H:i:s')
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
                'estimate_message' => $data['estimate_message'] ?? null
            ]);

            $result = $database->insert('estimate_tickets', $ticketData);
            break;

        case 'supplier':
            // Ensure new columns exist safely
            try {
                $database->query("ALTER TABLE supplier_tickets ADD COLUMN IF NOT EXISTS supplier_name VARCHAR(255) DEFAULT '' AFTER due_date");
                $database->query("ALTER TABLE supplier_tickets ADD COLUMN IF NOT EXISTS complete_costing TEXT AFTER bank_details");
            } catch (Exception $e) { }

            // Validate required fields
            $requiredFields = [
                'supplier_name',
                'travel_date',
                'due_date',
                'payment_type',
                'bank_details',
                'complete_costing'
            ];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "Missing required field: $field"]);
                    exit;
                }
            }

            // Handle file uploads
            $supplierInvoicePath = null;
            $customerInvoicePath = null;
            $paymentProofPath = null;

            if (isset($_FILES['supplier_invoice'])) {
                $supplierInvoicePath = handleFileUpload($_FILES['supplier_invoice'], 'supplier_invoices');
            }
            if (isset($_FILES['customer_invoice'])) {
                $customerInvoicePath = handleFileUpload($_FILES['customer_invoice'], 'customer_invoices');
            }
            if (isset($_FILES['payment_proof'])) {
                $paymentProofPath = handleFileUpload($_FILES['payment_proof'], 'payment_proofs');
            }

            $ticketData = array_merge($commonFields, [
                'supplier_name' => $data['supplier_name'],
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
            $details = "<h4>Actual Invoice Request</h4>";
            $details .= "<p><strong>Client Type:</strong> " . htmlspecialchars($data['client_type'] ?? '') . "</p>";
            
            if (($data['client_type'] ?? '') === 'Individual') {
                $details .= "<p><strong>Boss Confirmation:</strong> " . (!empty($data['boss_confirmation']) && $data['boss_confirmation'] !== 'false' ? 'Yes' : 'No') . "</p>";
            } elseif (($data['client_type'] ?? '') === 'New Corporate') {
                $details .= "<p><strong>Boss Approval:</strong> " . (!empty($data['boss_approval']) && $data['boss_approval'] !== 'false' ? 'Yes' : 'No') . "</p>";
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
            $details = "<h4>Purchase Order Request</h4>";
            $details .= "<p><strong>Supplier Name:</strong> " . htmlspecialchars($data['supplier_name'] ?? '') . "</p>";
            $details .= "<p><strong>Client Name:</strong> " . htmlspecialchars($data['client_name'] ?? '') . "</p>";
            $details .= "<p><strong>PO Date:</strong> " . htmlspecialchars($data['po_date'] ?? '') . "</p>";
            $details .= "<p><strong>Quantity:</strong> " . htmlspecialchars($data['quantity'] ?? '') . "</p>";
            $details .= "<p><strong>Rate:</strong> " . htmlspecialchars($data['rate'] ?? '') . "</p>";
            $details .= "<p><strong>Description:</strong><br>" . nl2br(htmlspecialchars($data['description'] ?? '')) . "</p>";
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
            $details = "<h4>Payment Link Request</h4>";
            $details .= "<p><strong>Client Name:</strong> " . htmlspecialchars($data['client_name'] ?? '') . "</p>";
            $details .= "<p><strong>Email Address:</strong> " . htmlspecialchars($data['email'] ?? '') . "</p>";
            $details .= "<p><strong>Phone Number:</strong> " . htmlspecialchars($data['phone'] ?? '') . "</p>";
            $details .= "<p><strong>Country:</strong> " . htmlspecialchars($data['country'] ?? '') . "</p>";
            $details .= "<p><strong>Amount:</strong> " . htmlspecialchars($data['amount'] ?? '') . "</p>";
            $details .= "<p><strong>Platform Preference:</strong> " . htmlspecialchars($data['platform'] ?? '') . "</p>";

            $ticketData = array_merge($commonFields, [
                'description' => $details,
                'ticket_subtype' => 'Create Payment Link'
            ]);
            $result = $database->insert('general_tickets', $ticketData);
            break;

        case 'amex_payment':
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
            $details = "<h4>Refund Request</h4>";
            $details .= "<p><strong>Reason for Refund:</strong><br>" . nl2br(htmlspecialchars($data['reason'] ?? '')) . "</p>";
            $details .= "<p><strong>Customer Bank Details:</strong><br>" . nl2br(htmlspecialchars($data['customer_bank_details'] ?? '')) . "</p>";
            $details .= "<p><strong>14-day policy confirmation:</strong> " . (!empty($data['policy_confirmation']) && $data['policy_confirmation'] !== 'false' ? 'Yes' : 'No') . "</p>";

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
            'comment' => sprintf('Ticket created by %s on %s', $dbUser['name'], $formattedDate),
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
