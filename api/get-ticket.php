<?php
require_once '../vendor/autoload.php';
require_once '../function.php';
require_once '../database.php';

// Start the session
session_start();

// Get user info from Auth0
$auth0 = new Auth0\SDK\Auth0([
    'domain' => 'fayyaztravels.us.auth0.com',
    'clientId' => 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    'clientSecret' => 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    'redirectUri' => 'https://crm.fyyz.link/callback.php',
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

    // Get ticket ID and type from query parameters
    $ticketId = $_GET['id'] ?? null;
    $ticketType = $_GET['type'] ?? null;

    if (!$ticketId || !$ticketType) {
        http_response_code(400);
        echo json_encode(['error' => 'Ticket ID and type are required']);
        exit;
    }

    // Determine which table to query based on ticket type
    $table = '';
    switch ($ticketType) {
        case 'estimate':
            $table = 'estimate_tickets';
            break;
        case 'supplier':
            $table = 'supplier_tickets';
            break;
        case 'general':
            $table = 'general_tickets';
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid ticket type']);
            exit;
    }

    // Get ticket details
    $ticket = $database->get($table, '*', ['id' => $ticketId]);

    if (!$ticket) {
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    // Always include description and image fields if present
    $ticket['description'] = $ticket['description'] ?? $ticket['estimate_message'] ?? $ticket['supplier_message'] ?? '';
    $ticket['supporting_image_path'] = $ticket['supporting_image_path'] ?? null;
    $ticket['supplier_invoice_path'] = $ticket['supplier_invoice_path'] ?? null;
    $ticket['customer_invoice_path'] = $ticket['customer_invoice_path'] ?? null;
    $ticket['payment_proof_path'] = $ticket['payment_proof_path'] ?? null;

    // Get user info who created the ticket
    $ticketUser = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
    if ($ticketUser) {
        $ticket['user_name'] = $ticketUser['name'];
        $ticket['user_email'] = $ticketUser['email'];
    } else {
        $ticket['user_name'] = 'Unknown User';
        $ticket['user_email'] = '';
    }

    // Add ticket type
    $ticket['type'] = ucfirst($ticketType);

    // Get admin comments for this ticket
    $comments = $database->select('ticket_comments', [
        '[>]users' => ['user_id' => 'id']
    ], [
        'ticket_comments.comment',
        'ticket_comments.created_at',
        'users.name(user_name)',
        'users.is_admin'
    ], [
        'ticket_comments.ticket_id' => $ticketId,
        'ticket_comments.ticket_type' => $ticketType,
        'users.is_admin' => 1,
        'ORDER' => ['ticket_comments.created_at' => 'ASC']
    ]);

    // Add comments to the response
    $ticket['comments'] = $comments;

    echo json_encode([
        'success' => true,
        'ticket' => $ticket
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
