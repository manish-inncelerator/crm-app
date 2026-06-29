<?php
require_once '../vendor/autoload.php';
require_once '../function.php';
require_once '../database.php';
require_once '../functions/notifications.php';

session_start();

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

    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found in database']);
        exit;
    }

    $data = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

    if (empty($data) && empty($_FILES)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit;
    }

    if (!isset($data['ticket_category'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ticket category is required']);
        exit;
    }

    if (empty($data['booking_reference'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Booking/Reference number is required']);
        exit;
    }

    $category = $data['ticket_category'];
    $bookingReference = trim($data['booking_reference']);
    $priority = $data['priority'] ?? 'MEDIUM';
    
    // Default values
    $type = 'general';
    $subtype = $category;
    $status = 'OPEN';
    $description = $data['description'] ?? '';
    
    $metadata = [];
    
    // Map category to Type and extract metadata
    if ($category === 'estimate') {
        $type = 'estimate';
        $subtype = 'Estimate Creation';
        $metadata = [
            'customer_name' => $data['customer_name'] ?? null,
            'email' => $data['email'] ?? null,
            'contact_number' => $data['contact_number'] ?? null,
            'service_date' => $data['service_date'] ?? null,
            'total_amount' => $data['total_amount'] ?? null,
            'package_details' => $data['package_details'] ?? null
        ];
    } elseif ($category === 'supplier') {
        $type = 'supplier';
        $subtype = 'Supplier Payment';
        $metadata = [
            'supplier_id' => $data['supplier_id'] ?? null,
            'payment_type' => $data['payment_type'] ?? null,
            'bank_details' => $data['bank_details'] ?? null
        ];
        
        if (isset($_FILES['supplier_invoice'])) {
            $metadata['supplier_invoice_path'] = handleFileUpload($_FILES['supplier_invoice'], 'supplier_invoices');
        }
        if (isset($_FILES['customer_invoice'])) {
            $metadata['customer_invoice_path'] = handleFileUpload($_FILES['customer_invoice'], 'customer_invoices');
        }
    } elseif ($category === 'invoice') {
        $type = 'general';
        $subtype = 'Convert Estimate to Invoice';
        $metadata = [
            'client_type' => $data['client_type'] ?? null
        ];
        if (isset($_FILES['payment_proof'])) {
            $metadata['payment_proof_path'] = handleFileUpload($_FILES['payment_proof'], 'payment_proofs');
        }
    } else {
        // Fallback for everything else
        $type = 'general';
        $subtype = ucfirst(str_replace('_', ' ', $category));
        if (isset($_FILES['supporting_image'])) {
            $metadata['supporting_image_path'] = handleFileUpload($_FILES['supporting_image'], 'supporting_images');
        }
    }
    
    if ($category === 'refund') {
        $status = 'SUBMITTED';
    }

    $ticketData = [
        'user_id' => $dbUser['id'],
        'owner_id' => $dbUser['id'],
        'type' => $type,
        'subtype' => $subtype,
        'booking_reference' => $bookingReference,
        'priority' => $priority,
        'status' => $status,
        'description' => $description,
        'metadata' => json_encode($metadata),
        'created_at' => date('Y-m-d H:i:s')
    ];

    $result = $database->insert('tickets_unified', $ticketData);

    if ($result) {
        $ticketId = $database->id();
        $currentDateTime = date('Y-m-d H:i:s');
        $formattedDate = date('F j, Y', strtotime($currentDateTime));

        $database->insert('ticket_comments', [
            'ticket_id' => $ticketId,
            'ticket_type' => $type,
            'user_id' => $dbUser['id'],
            'comment' => sprintf('Ticket created by %s on %s with Booking Ref: %s', $dbUser['name'], $formattedDate, $bookingReference),
            'created_at' => $currentDateTime
        ]);

        notifyTicketCreation($dbUser['id'], $ticketId, $type);

        if (!isset($dbUser['is_admin']) || $dbUser['is_admin'] != 1) {
            $admins = $database->select('users', ['id'], ['is_admin' => 1]);
            if ($admins) {
                foreach ($admins as $admin) {
                    notifyTicketCreation($admin['id'], $ticketId, $type);
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
        echo json_encode(['error' => 'Failed to create ticket in database']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

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
