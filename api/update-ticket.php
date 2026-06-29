<?php
require_once '../vendor/autoload.php';
require_once '../function.php';
require_once '../database.php';
require_once '../functions/notifications.php';

// Add getEstimatedTime function
function getEstimatedTime($ticket)
{
    $subject = strtolower(trim($ticket['subject'] ?? ''));
    $type = strtolower($ticket['type'] ?? '');
    $subtype = strtolower(trim($ticket['ticket_subtype'] ?? ''));
    // Exact subject/subtype mapping
    $map = [
        'create estimates in qb' => '5 Minutes',
        'converting estimate into actual invoice once payment received' => '5 Minutes',
        'convert estimate to invoice' => '5 Minutes',
        'create payment link' => '5 Minutes',
        'creating payment link in the flywire and tazapay for customers payment' => '5 Minutes',
        'updating payments in qb and giving paid invoice to sales team' => '5 Minutes',
        'modification in the estimates/invoices if any new changes coming from customer but that are sometime' => '10 Minutes',
        'normal customers / corporate customer  payments follow up' => '5 Minutes',
        'suppliers payment follow up for containing ticketing and packages' => '5 Minutes',
        'customers refund' => '30 to 45 Days',
        'payment from amex card (cc)' => '10 Minutes',
    ];
    if (isset($map[$subject])) {
        return $map[$subject];
    }
    if ($subtype && isset($map[$subtype])) {
        return $map[$subtype];
    }
    // Default for supplier tickets
    if ($type === 'supplier') {
        return '5 Minutes';
    }
    // Default for general tickets
    if ($type === 'general') {
        return '';
    }
    return '';
}

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

    // Check if user is admin
    if (!isset($dbUser['is_admin']) || $dbUser['is_admin'] != 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Only admins can update tickets']);
        exit;
    }

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data']);
        exit;
    }

    // Validate required fields
    if (!isset($data['ticket_id']) || !isset($data['ticket_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Ticket ID and type are required']);
        exit;
    }

    // Get the ticket table name
    $ticketTable = 'tickets_unified';

    // Get current ticket data
    $currentTicket = $database->get($ticketTable, '*', ['id' => $data['ticket_id']]);
    if (!$currentTicket) {
        http_response_code(404);
        echo json_encode(['error' => 'Ticket not found']);
        exit;
    }

    // Prepare update data
    $updateData = [];

    // Handle status change
    if (isset($data['status']) && $data['status'] !== $currentTicket['status']) {
        // Enforce Refund Workflow
        $tType = $data['ticket_type'] ?? ($currentTicket['type'] ?? '');
        if ($tType === 'refund') {
            $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);
            if (!$isMasterAdmin && in_array($data['status'], ['APPROVED', 'REJECTED'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Only Super Admins can Approve or Reject refunds.']);
                exit;
            }
        }
        
        $updateData['status'] = $data['status'];
        // Create notification for status change
        notifyTicketStatusChange($currentTicket['user_id'], $data['ticket_id'], $data['ticket_type'], $currentTicket['status'], $data['status']);

        // Add comment for status change
        $database->insert('ticket_comments', [
            'ticket_id' => $data['ticket_id'],
            'ticket_type' => $data['ticket_type'],
            'user_id' => $dbUser['id'],
            'comment' => "Status changed from {$currentTicket['status']} to {$data['status']}",
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Handle priority change
    if (isset($data['priority']) && $data['priority'] !== $currentTicket['priority']) {
        $updateData['priority'] = $data['priority'];
        // Create notification for priority change
        notifyTicketPriorityChange($currentTicket['user_id'], $data['ticket_id'], $data['ticket_type'], $currentTicket['priority'], $data['priority']);

        // Add comment for priority change
        $database->insert('ticket_comments', [
            'ticket_id' => $data['ticket_id'],
            'ticket_type' => $data['ticket_type'],
            'user_id' => $dbUser['id'],
            'comment' => "Priority changed from {$currentTicket['priority']} to {$data['priority']}",
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Handle estimated time change - only if estimated_time is explicitly provided
    if (array_key_exists('estimated_time', $data) && $data['estimated_time'] !== null && isset($data['estimated_time_changed']) && $data['estimated_time_changed']) {
        // Fetch the old estimated_time value directly from the ticket table
        $oldEstimatedTime = $currentTicket['estimated_time'];
        if ($data['estimated_time'] !== $oldEstimatedTime) {
            // Add estimated_time to update data
            $updateData['estimated_time'] = $data['estimated_time'];

            // Add comment for estimated time change
            $database->insert('ticket_comments', [
                'ticket_id' => $data['ticket_id'],
                'ticket_type' => $data['ticket_type'],
                'user_id' => $dbUser['id'],
                'comment' => "Estimated time changed from " . ($oldEstimatedTime ?: 'Not set') . " to " . ($data['estimated_time'] ?: 'Not set'),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Create notification for estimated time change
            notifyTicketEstimatedTimeChange($currentTicket['user_id'], $data['ticket_id'], $data['ticket_type'], $oldEstimatedTime, $data['estimated_time']);
        }
    }

    // Handle expected timeline change
    if (isset($data['expected_timeline']) && $data['expected_timeline'] !== $currentTicket['expected_timeline']) {
        $updateData['expected_timeline'] = $data['expected_timeline'];
        $oldTimeline = $currentTicket['expected_timeline'] ?: 'Not set';
        $newTimeline = $data['expected_timeline'] ?: 'Not set';
        
        $database->insert('ticket_comments', [
            'ticket_id' => $data['ticket_id'],
            'ticket_type' => $data['ticket_type'],
            'user_id' => $dbUser['id'],
            'comment' => "Expected timeline changed from {$oldTimeline} to {$newTimeline}. Reason: " . ($data['delay_reason'] ?? 'Not provided'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // Handle delay reason
    if (isset($data['delay_reason']) && $data['delay_reason'] !== $currentTicket['delay_reason']) {
        $updateData['delay_reason'] = $data['delay_reason'];
    }

    // Handle ownership reassignment
    if (isset($data['owner_id']) && $data['owner_id'] != $currentTicket['owner_id']) {
        $updateData['owner_id'] = $data['owner_id'];
        $oldOwner = $database->get('users', ['name'], ['id' => $currentTicket['owner_id']]);
        $newOwner = $database->get('users', ['name'], ['id' => $data['owner_id']]);
        
        $database->insert('ticket_comments', [
            'ticket_id' => $data['ticket_id'],
            'ticket_type' => $data['ticket_type'],
            'user_id' => $dbUser['id'],
            'comment' => "Ticket reassigned from " . ($oldOwner['name'] ?? 'Unknown') . " to " . ($newOwner['name'] ?? 'Unknown'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        notifyTicketReassignment($data['owner_id'], $data['ticket_id'], $data['ticket_type'], $oldOwner['name'] ?? 'Unknown');
    }

    // Handle comment
    if (isset($data['comment']) && !empty($data['comment'])) {
        // Insert comment
        $database->insert('ticket_comments', [
            'ticket_id' => $data['ticket_id'],
            'ticket_type' => $data['ticket_type'],
            'user_id' => $dbUser['id'],
            'comment' => $data['comment'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Create notification for new comment
        notifyNewComment($currentTicket['user_id'], $data['ticket_id'], $data['ticket_type'], $dbUser['id']);
        
        // Email Notifications
        require_once '../sendMail.php';
        $ticketLink = "https://crm.fayyaz.travel/ticket-details.php?id={$data['ticket_id']}";
        
        // 1. Email the ticket creator
        if ($currentTicket['user_id'] != $dbUser['id']) {
            $creator = $database->get('users', ['email', 'name'], ['id' => $currentTicket['user_id']]);
            if ($creator && !empty($creator['email'])) {
                $body = "<p>Hello {$creator['name']},</p><p>A new reply/update has been added to your ticket (<strong>#{$data['ticket_id']}</strong>) by <strong>{$dbUser['name']}</strong>.</p><hr><p>{$data['comment']}</p><hr><p>View your ticket to respond.</p>";
                sendEmail($creator['email'], "Update on Ticket #{$data['ticket_id']}", 'default', $body, true, $ticketLink);
            }
        }

        // 2. Email admins (excluding super admins, unless it's a refund)
        $adminFilters = [
            'receive_emails' => 1,
            'id[!]' => $dbUser['id']
        ];
        if ($ticket['type'] !== 'refund') {
            $adminFilters['is_master_admin'] = 0;
        }
        $emailAdmins = $database->select('users', ['email'], ['AND' => $adminFilters]);
        if (!empty($emailAdmins)) {
            $adminEmails = array_column($emailAdmins, 'email');
            $body = "<p>A new reply/update has been added to ticket (<strong>#{$data['ticket_id']}</strong>) by <strong>{$dbUser['name']}</strong>.</p><hr><p>{$data['comment']}</p><hr><p>Please review it in the CRM.</p>";
            sendEmail($adminEmails, "New Reply on Ticket #{$data['ticket_id']}", 'default', $body, true, $ticketLink);
        }
    }

    // Update ticket if there are changes
    if (!empty($updateData)) {
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $result = $database->update($ticketTable, $updateData, ['id' => $data['ticket_id']]);
        if (!$result) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update ticket']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ticket updated successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

