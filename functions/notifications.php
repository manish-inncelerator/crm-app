<?php
require_once __DIR__ . '/../database.php';

/**
 * Create a new notification
 * 
 * @param int $userId The ID of the user to notify
 * @param int $ticketId The ID of the related ticket
 * @param string $ticketType The type of ticket (estimate, supplier, general)
 * @param string $type The notification type (info, success, warning, error)
 * @param string $title The notification title
 * @param string $message The notification message
 * @return bool True if notification was created successfully
 */
function createNotification($userId, $ticketId, $ticketType, $type, $title, $message)
{
    global $database;

    try {
        $database->insert('notifications', [
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'ticket_type' => $ticketType,
            'type' => $type,
            'title' => $title,
            'message' => $message
        ]);
        return true;
    } catch (Exception $e) {
        writeLog('Error creating notification: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Create a notification for ticket status change
 */
function notifyTicketStatusChange($userId, $ticketId, $ticketType, $oldStatus, $newStatus)
{
    try {
        global $database;
        $user = $database->get('users', ['is_admin', 'name'], ['id' => $userId]);
        $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
        $ticket = $database->get($ticketType . '_tickets', ['user_id'], ['id' => $ticketId]);
        $creator = $database->get('users', ['name'], ['id' => $ticket['user_id']]);
        if ($isAdmin) {
            $title = 'Ticket Status Updated';
            $message = sprintf('The status of %s\'s %s ticket has changed from %s to %s', $creator['name'], ucfirst($ticketType), $oldStatus, $newStatus);
            writeLog("[notifyTicketStatusChange] ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        } else {
            $title = 'Ticket Status Updated';
            $message = sprintf('The status of your %s ticket has changed from %s to %s', ucfirst($ticketType), $oldStatus, $newStatus);
            writeLog("[notifyTicketStatusChange] NON-ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        }
        return createNotification($userId, $ticketId, $ticketType, 'info', $title, $message);
    } catch (Exception $e) {
        writeLog('Error creating status change notification: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Create a notification for ticket priority change
 */
function notifyTicketPriorityChange($userId, $ticketId, $ticketType, $oldPriority, $newPriority)
{
    try {
        global $database;
        $user = $database->get('users', ['is_admin', 'name'], ['id' => $userId]);
        $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
        $ticket = $database->get($ticketType . '_tickets', ['user_id'], ['id' => $ticketId]);
        $creator = $database->get('users', ['name'], ['id' => $ticket['user_id']]);
        if ($isAdmin) {
            $title = 'Ticket Priority Updated';
            $message = sprintf('The priority of %s\'s %s ticket has changed from %s to %s', $creator['name'], ucfirst($ticketType), $oldPriority, $newPriority);
            writeLog("[notifyTicketPriorityChange] ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        } else {
            $title = 'Ticket Priority Updated';
            $message = sprintf('The priority of your %s ticket has changed from %s to %s', ucfirst($ticketType), $oldPriority, $newPriority);
            writeLog("[notifyTicketPriorityChange] NON-ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        }
        return createNotification($userId, $ticketId, $ticketType, 'info', $title, $message);
    } catch (Exception $e) {
        writeLog('Error creating priority change notification: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Create a notification for new ticket comment
 */
function notifyNewComment($userId, $ticketId, $ticketType, $commenterId)
{
    try {
        global $database;
        $user = $database->get('users', ['is_admin', 'name'], ['id' => $userId]);
        $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
        $ticket = $database->get($ticketType . '_tickets', ['user_id'], ['id' => $ticketId]);
        $creator = $database->get('users', ['name'], ['id' => $ticket['user_id']]);
        $commenter = $database->get('users', ['name'], ['id' => $commenterId]);
        if ($isAdmin) {
            $title = 'New Comment Added';
            $message = sprintf('%s added a comment to %s\'s %s ticket', $commenter['name'], $creator['name'], ucfirst($ticketType));
            writeLog("[notifyNewComment] ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        } else {
            $title = 'New Comment on Your Ticket';
            $message = sprintf('%s added a comment to your %s ticket', $commenter['name'], ucfirst($ticketType));
            writeLog("[notifyNewComment] NON-ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        }
        return createNotification($userId, $ticketId, $ticketType, 'info', $title, $message);
    } catch (Exception $e) {
        writeLog('Error creating comment notification: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Create a notification for ticket creation
 */
function notifyTicketCreation($userId, $ticketId, $ticketType)
{
    try {
        global $database;
        $user = $database->get('users', ['is_admin', 'name'], ['id' => $userId]);
        $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
        $ticket = $database->get($ticketType . '_tickets', ['user_id'], ['id' => $ticketId]);
        $creator = $database->get('users', ['name'], ['id' => $ticket['user_id']]);
        if ($isAdmin) {
            $title = 'New Ticket Created';
            $message = sprintf('A new notification has been created by %s', $creator['name']);
            writeLog("[notifyTicketCreation] ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        } else {
            $title = 'Ticket Created';
            $message = sprintf('Your %s ticket has been created successfully', ucfirst($ticketType));
            writeLog("[notifyTicketCreation] NON-ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        }
        return createNotification($userId, $ticketId, $ticketType, 'info', $title, $message);
    } catch (Exception $e) {
        writeLog('Error creating ticket notification: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Create a notification for ticket estimated time change
 */
function notifyTicketEstimatedTimeChange($userId, $ticketId, $ticketType, $oldTime, $newTime)
{
    try {
        global $database;
        $user = $database->get('users', ['is_admin', 'name'], ['id' => $userId]);
        $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
        $ticket = $database->get($ticketType . '_tickets', ['user_id'], ['id' => $ticketId]);
        $creator = $database->get('users', ['name'], ['id' => $ticket['user_id']]);
        if ($isAdmin) {
            $title = 'Ticket Estimated Time Updated';
            $message = sprintf('The estimated time of %s\'s %s ticket has changed from %s to %s', $creator['name'], ucfirst($ticketType), $oldTime, $newTime);
            writeLog("[notifyTicketEstimatedTimeChange] ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        } else {
            $title = 'Ticket Estimated Time Updated';
            $message = sprintf('The estimated time of your %s ticket has changed from %s to %s', ucfirst($ticketType), $oldTime, $newTime);
            writeLog("[notifyTicketEstimatedTimeChange] NON-ADMIN: user_id={$userId}, user_name={$user['name']}, message={$message}", 'DEBUG');
        }
        return createNotification($userId, $ticketId, $ticketType, 'info', $title, $message);
    } catch (Exception $e) {
        writeLog('Error creating estimated time change notification: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Mark a notification as read
 */
function markNotificationAsRead($notificationId)
{
    global $database;

    try {
        $database->update('notifications', [
            'is_read' => true
        ], [
            'id' => $notificationId
        ]);
        return true;
    } catch (Exception $e) {
        writeLog('Error marking notification as read: ' . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Get unread notifications count for a user
 */
function getUnreadNotificationsCount($userId)
{
    global $database;

    try {
        return $database->count('notifications', [
            'user_id' => $userId,
            'is_read' => false
        ]);
    } catch (Exception $e) {
        writeLog('Error getting unread notifications count: ' . $e->getMessage(), 'ERROR');
        return 0;
    }
}
