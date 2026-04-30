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
    if (!$data || !isset($data['id']) || !isset($data['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit;
    }

    $ticketId = $data['id'];
    $ticketType = $data['type'];
    $table = $ticketType . '_tickets';

    // Get current ticket to check permission
    $currentTicket = $database->get($table, '*', ['id' => $ticketId]);
    if (!$currentTicket) {
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    // Check if user is owner, creator or admin
    $isAdmin = (isset($dbUser['is_admin']) && $dbUser['is_admin'] == 1);
    $isOwner = ($currentTicket['owner_id'] == $dbUser['id']);
    $isCreator = ($currentTicket['user_id'] == $dbUser['id']);

    if (!$isAdmin && !$isOwner && !$isCreator) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to edit this ticket']);
        exit;
    }

    // Prepare update data
    $updateData = [];
    if (isset($data['booking_reference'])) {
        $updateData['booking_reference'] = trim($data['booking_reference']);
    }
    if (isset($data['description'])) {
        $updateData['description'] = $data['description'];
    }
    if (isset($data['priority'])) {
        $updateData['priority'] = $data['priority'];
    }
    if (isset($data['status'])) {
        $updateData['status'] = $data['status'];
    }
    if (isset($data['owner_id'])) {
        $updateData['owner_id'] = $data['owner_id'];
    }

    // Type-specific updates (e.g. customer name for estimate)
    if ($ticketType === 'estimate') {
        if (isset($data['customer_name'])) $updateData['customer_name'] = $data['customer_name'];
        if (isset($data['total_amount'])) $updateData['total_amount'] = $data['total_amount'];
    }

    if (empty($updateData)) {
        echo json_encode(['success' => true, 'message' => 'No changes made']);
        exit;
    }

    $updateData['updated_at'] = date('Y-m-d H:i:s');
    $result = $database->update($table, $updateData, ['id' => $ticketId]);

    if ($result) {
        // Log the edit
        $database->insert('ticket_comments', [
            'ticket_id' => $ticketId,
            'ticket_type' => $ticketType,
            'user_id' => $dbUser['id'],
            'comment' => 'Ticket details updated via Edit option.',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        echo json_encode(['success' => true, 'message' => 'Ticket updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update ticket']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
