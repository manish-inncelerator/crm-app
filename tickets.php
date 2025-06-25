<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

session_start();

$httpClient = new Client([
    'verify' => false
]);

$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fyyz.link/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

$sessionStore = new SessionStore($config);
$auth0 = new Auth0($config);

// Restore the PHP timeAgo function for use in the table
function timeAgo($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    $units = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
    ];
    foreach ($units as $secs => $str) {
        $val = floor($diff / $secs);
        if ($val >= 1) {
            return $val . ' ' . $str . ($val > 1 ? 's' : '') . ' ago';
        }
    }
    return date('Y-m-d', $time);
}

// Add this function after the timeAgo function
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
        'initial training on qb for estimate creations' => '30 Minutes',
        'sometime requests for separate payment receipts for the customers' => '10 Minutes',
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

// Add this function before the HTML
function getPriorityClass($priority)
{
    switch ($priority) {
        case 'URGENT':
            return 'bg-danger';
        case 'HIGH':
            return 'bg-warning text-dark';
        case 'MEDIUM':
            return 'bg-info text-dark';
        case 'LOW':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

// Add this function after the getPriorityClass function
function getDefaultEstimatedTime($subject)
{
    $subject = strtolower(trim($subject));

    // Debug log
    error_log("Subject being checked: " . $subject);

    $defaultTimes = [
        'create estimates in qb' => '5 Minutes',
        'converting estimate into actual invoice once payment received' => '5 Minutes',
        'convert estimate to invoice' => '5 Minutes',
        'create payment link' => '5 Minutes',
        'creating payment link in the flywire and tazapay for customers payment' => '5 Minutes',
        'updating payments in qb and giving paid invoice to sales team' => '5 Minutes',
        'modification in the estimates/invoices if any new changes coming from customer but that are sometime' => '10 Minutes',
        'normal customers / corporate customer  payments follow up' => '5 Minutes',
        'suppliers payment follow up for containing ticketing and packages' => '5 Minutes',
        'initial training on qb for estimate creations' => '30 Minutes',
        'sometime requests for separate payment receipts for the customers' => '10 Minutes',
        'sharing bank details file for the accounts for customer payments' => 'Already discussed and file shared with Jeth',
        'customers refund' => '30 to 45 Days',
        'payment from amex card (cc)' => '10 Minutes'
    ];

    // Try exact match first
    if (isset($defaultTimes[$subject])) {
        error_log("Found exact match for: " . $subject);
        return $defaultTimes[$subject];
    }

    // Try partial match
    foreach ($defaultTimes as $key => $value) {
        if (strpos($subject, $key) !== false || strpos($key, $subject) !== false) {
            error_log("Found partial match for: " . $subject . " with key: " . $key);
            return $value;
        }
    }

    // If no match found, return default based on ticket type
    if (strpos($subject, 'estimate') !== false) {
        return '5 Minutes';
    } elseif (strpos($subject, 'supplier') !== false) {
        return '5 Minutes';
    } elseif (strpos($subject, 'payment') !== false) {
        return '10 Minutes';
    }

    error_log("No match found for: " . $subject);
    return '5 Minutes'; // Default fallback
}

try {
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);
    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }

    // Fetch tickets based on user role
    $isAdmin = isset($dbUser['is_admin']) && $dbUser['is_admin'] == 1;

    // Base conditions for tickets
    $conditions = [];
    if (!$isAdmin) {
        $conditions['user_id'] = $dbUser['id'];
    }

    // Fetch open tickets (OPEN and IN_PROGRESS)
    $openEstimateTickets = $database->select('estimate_tickets', '*', array_merge($conditions, [
        'status' => ['OPEN', 'IN_PROGRESS'],
        'ORDER' => ['id' => 'DESC']
    ]));
    $openSupplierTickets = $database->select('supplier_tickets', '*', array_merge($conditions, [
        'status' => ['OPEN', 'IN_PROGRESS'],
        'ORDER' => ['id' => 'DESC']
    ]));
    $openGeneralTickets = $database->select('general_tickets', '*', array_merge($conditions, [
        'status' => ['OPEN', 'IN_PROGRESS'],
        'ORDER' => ['id' => 'DESC']
    ]));

    // Fetch closed tickets (CLOSED only)
    $closedEstimateTickets = $database->select('estimate_tickets', '*', array_merge($conditions, [
        'status' => 'CLOSED',
        'ORDER' => ['id' => 'DESC']
    ]));
    $closedSupplierTickets = $database->select('supplier_tickets', '*', array_merge($conditions, [
        'status' => 'CLOSED',
        'ORDER' => ['id' => 'DESC']
    ]));
    $closedGeneralTickets = $database->select('general_tickets', '*', array_merge($conditions, [
        'status' => 'CLOSED',
        'ORDER' => ['id' => 'DESC']
    ]));

    // Combine all tickets
    $openTickets = array_merge(
        array_map(function ($ticket) use ($database) {
            $ticket['type'] = 'Estimate';
            $ticket['type_key'] = 'estimate';
            $ticket['subject'] = 'Estimate Creation';
            // Get user info
            $user = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
            $ticket['user_name'] = $user ? $user['name'] : 'Unknown User';
            $ticket['user_email'] = $user ? $user['email'] : '';
            return $ticket;
        }, $openEstimateTickets),
        array_map(function ($ticket) use ($database) {
            $ticket['type'] = 'Supplier';
            $ticket['type_key'] = 'supplier';
            $ticket['subject'] = 'Supplier Payment';
            // Get user info
            $user = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
            $ticket['user_name'] = $user ? $user['name'] : 'Unknown User';
            $ticket['user_email'] = $user ? $user['email'] : '';
            return $ticket;
        }, $openSupplierTickets),
        array_map(function ($ticket) use ($database) {
            $ticket['type'] = 'General';
            $ticket['type_key'] = 'general';
            $ticket['subject'] = $ticket['ticket_subtype'] ?? 'General Ticket';
            // Get user info
            $user = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
            $ticket['user_name'] = $user ? $user['name'] : 'Unknown User';
            $ticket['user_email'] = $user ? $user['email'] : '';
            return $ticket;
        }, $openGeneralTickets)
    );

    $closedTickets = array_merge(
        array_map(function ($ticket) use ($database) {
            $ticket['type'] = 'Estimate';
            $ticket['type_key'] = 'estimate';
            $ticket['subject'] = 'Estimate Creation';
            // Get user info
            $user = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
            $ticket['user_name'] = $user ? $user['name'] : 'Unknown User';
            $ticket['user_email'] = $user ? $user['email'] : '';
            return $ticket;
        }, $closedEstimateTickets),
        array_map(function ($ticket) use ($database) {
            $ticket['type'] = 'Supplier';
            $ticket['type_key'] = 'supplier';
            $ticket['subject'] = 'Supplier Payment';
            // Get user info
            $user = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
            $ticket['user_name'] = $user ? $user['name'] : 'Unknown User';
            $ticket['user_email'] = $user ? $user['email'] : '';
            return $ticket;
        }, $closedSupplierTickets),
        array_map(function ($ticket) use ($database) {
            $ticket['type'] = 'General';
            $ticket['type_key'] = 'general';
            $ticket['subject'] = $ticket['ticket_subtype'] ?? 'General Ticket';
            // Get user info
            $user = $database->get('users', ['name', 'email'], ['id' => $ticket['user_id']]);
            $ticket['user_name'] = $user ? $user['name'] : 'Unknown User';
            $ticket['user_email'] = $user ? $user['email'] : '';
            return $ticket;
        }, $closedGeneralTickets)
    );

    // After fetching $openTickets and $closedTickets, gather unique users for the filter (admin only):
    $allUsers = [];
    if ($isAdmin) {
        $userIds = array_unique(array_merge(
            array_column($openTickets, 'user_id'),
            array_column($closedTickets, 'user_id')
        ));
        if (!empty($userIds)) {
            $allUsers = $database->select('users', ['id', 'name'], ['id' => $userIds]);
        }
    }

    // After fetching $openTickets and $closedTickets, for each ticket, fetch the latest admin comment and check if it is a priority change. Add a 'priority_changed_by_admin' flag to the ticket array if so.
    foreach ([$openTickets, $closedTickets] as &$ticketList) {
        foreach ($ticketList as &$ticket) {
            $latestChangeComment = $database->get('ticket_comments', 'comment', [
                'ticket_id' => $ticket['id'],
                'ticket_type' => strtolower($ticket['type']),
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 1
            ]);
            if ($latestChangeComment && (strpos($latestChangeComment, 'Priority changed') === 0 || strpos($latestChangeComment, 'Status changed') === 0 || strpos($latestChangeComment, 'Estimated time changed') === 0)) {
                $ticket['admin_change_comment'] = $latestChangeComment;
            } else {
                $ticket['admin_change_comment'] = '';
            }
            $latestStatusChangeComment = $database->get('ticket_comments', 'comment', [
                'ticket_id' => $ticket['id'],
                'ticket_type' => strtolower($ticket['type']),
                'comment[~]' => 'Status changed%',
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 1
            ]);
            $ticket['status_change_comment'] = $latestStatusChangeComment ? $latestStatusChangeComment : '';
            $latestPriorityChangeComment = $database->get('ticket_comments', 'comment', [
                'ticket_id' => $ticket['id'],
                'ticket_type' => strtolower($ticket['type']),
                'comment[~]' => 'Priority changed%',
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 1
            ]);
            $ticket['priority_change_comment'] = $latestPriorityChangeComment ? $latestPriorityChangeComment : '';
            $latestTimeChangeComment = $database->get('ticket_comments', 'comment', [
                'ticket_id' => $ticket['id'],
                'ticket_type' => strtolower($ticket['type']),
                'comment[~]' => 'Estimated time changed%',
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 1
            ]);
            $ticket['time_change_comment'] = $latestTimeChangeComment ? $latestTimeChangeComment : '';
            $latestAnyComment = $database->get('ticket_comments', 'comment', [
                'ticket_id' => $ticket['id'],
                'ticket_type' => strtolower($ticket['type']),
                'ORDER' => ['created_at' => 'DESC'],
                'LIMIT' => 1
            ]);
            $ticket['debug_any_comment'] = $latestAnyComment ? $latestAnyComment : '';
        }
    }
    unset($ticketList);
} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Tickets');
?>
<!-- jQuery (must be first) -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<style>
    /* Ticket Details Modal Styles */
    .ticket-details-section {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .ticket-details-section h6 {
        color: #495057;
        font-weight: 600;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #dee2e6;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ticket-details-section h6 i {
        font-size: 1.1em;
        color: #4e1f00;
    }

    .ticket-detail-item {
        margin-bottom: 1rem;
    }

    .ticket-detail-item:last-child {
        margin-bottom: 0;
    }

    .ticket-detail-label {
        color: #6c757d;
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ticket-detail-label i {
        font-size: 1em;
        color: #4e1f00;
    }

    .ticket-detail-value {
        color: #212529;
        font-weight: 500;
    }

    .ticket-description {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: 1rem;
        margin-top: 0.5rem;
    }

    .ticket-description p:last-child {
        margin-bottom: 0;
    }

    .ticket-status-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ticket-priority-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .ticket-meta {
        font-size: 0.875rem;
        color: #6c757d;
    }

    .ticket-section-divider {
        height: 1px;
        background: #dee2e6;
        margin: 1.5rem 0;
    }

    /* Enhanced Modal Close Button */
    .modal .btn-close {
        background: #f3e7db;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        padding: 0;
        margin: 0;
        opacity: 1;
        position: relative;
        transition: all 0.3s ease;
        border: 1.5px solid rgba(78, 31, 0, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal .btn-close i {
        font-size: 1.2rem;
        color: #4e1f00;
        transition: all 0.3s ease;
    }

    .modal .btn-close:hover {
        background: #4e1f00;
        transform: rotate(90deg);
        border-color: #4e1f00;
    }

    .modal .btn-close:hover i {
        color: #fff;
    }

    /* Dark mode styles */
    body.dark-mode .ticket-details-section h6 i,
    body.dark-mode .ticket-detail-label i {
        color: #a97c50;
    }

    body.dark-mode .modal .btn-close {
        background: #2d1a0d;
        border: 1.5px solid rgba(255, 255, 255, 0.1);
    }

    body.dark-mode .modal .btn-close i {
        color: #fff;
    }

    body.dark-mode .modal .btn-close:hover {
        background: #fff;
        border-color: #fff;
    }

    body.dark-mode .modal .btn-close:hover i {
        color: #2d1a0d;
    }

    .timeline-item {
        position: relative;
        padding-left: 1.5rem;
    }

    .timeline-item:before {
        content: '';
        position: absolute;
        left: 0;
        top: 0.5rem;
        width: 10px;
        height: 10px;
        background: #0d6efd;
        border-radius: 50%;
    }

    .timeline-comment {
        font-size: 0.97rem;
        color: #333;
        background: #f8f9fa;
    }

    /* Make modal body scrollable and keep header/footer fixed */
    .modal-dialog {
        display: flex;
        flex-direction: column;
        height: 90vh;
        max-width: 800px;
    }

    .modal-content {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .modal-header,
    .modal-footer {
        flex-shrink: 0;
        background: #fff;
        z-index: 2;
        border-radius: 0;
    }

    .modal-body {
        overflow-y: auto;
        flex: 1 1 auto;
        max-height: 60vh;
        min-height: 100px;
        background: #fff;
        z-index: 1;
    }

    body.dark-mode .modal-body {
        background: #23272b;
        color: #fff;
    }

    body.dark-mode .ticket-details-section {
        background: #343a40;
        color: #fff;
    }

    body.dark-mode .ticket-description,
    body.dark-mode .timeline-comment {
        background: #343a40;
        color: #fff;
        border-color: #444c56;
    }

    body.dark-mode .modal-header,
    body.dark-mode .modal-footer {
        background: #23272b;
        color: #fff;
        border-color: #444c56;
    }

    body.dark-mode .timeline-item:before {
        background: #0dcaf0;
    }

    body.dark-mode .badge {
        color: #23272b !important;
        background: #0dcaf0 !important;
    }

    body.dark-mode .ticket-detail-label,
    body.dark-mode .ticket-detail-value,
    body.dark-mode .ticket-meta {
        color: #fff !important;
    }

    body.dark-mode .timeline-comment {
        background: #23272b !important;
        color: #fff !important;
        border-color: #444c56 !important;
    }

    /* Modern Create New Ticket Button */
    .create-ticket-btn {
        background: linear-gradient(90deg, #4E1F00 0%, #a97c50 100%);
        color: #fff !important;
        font-weight: 700;
        font-size: 1.15rem;
        border: none;
        border-radius: 2rem;
        padding: 12px 32px 12px 24px;
        box-shadow: 0 4px 16px rgba(78, 31, 0, 0.15);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
        position: relative;
        z-index: 1;
    }

    .create-ticket-btn i {
        font-size: 1.3em;
        margin-right: 0.25em;
    }

    .create-ticket-btn:hover,
    .create-ticket-btn:focus {
        background: linear-gradient(90deg, #a97c50 0%, #4E1F00 100%);
        box-shadow: 0 6px 24px rgba(78, 31, 0, 0.25);
        color: #fff !important;
        transform: translateY(-2px) scale(1.03);
        text-decoration: none;
    }

    .badge.bg-purple {
        background-color: #9c27b0 !important;
        color: white !important;
    }

    body.dark-mode .badge.bg-purple {
        background-color: #ba68c8 !important;
        color: #2d1a0d !important;
    }

    /* Add these styles after the existing styles */
    .action-buttons {
        display: flex;
        gap: 0.5rem;
        flex-wrap: nowrap;
        justify-content: flex-start;
        align-items: center;
    }

    .action-buttons .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .action-buttons .btn i {
        font-size: 0.875rem;
    }

    /* Make the action column wider to accommodate buttons */
    #openTicketsTable th:last-child,
    #closedTicketsTable th:last-child {
        min-width: 200px;
    }

    /* Add these styles after the existing styles */
    /* DataTables Pagination Styling */
    .dataTables_paginate {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--sidebar-divider);
    }

    .paginate_button {
        padding: 0.5rem 1rem !important;
        border-radius: 12px !important;
        border: 1.5px solid var(--sidebar-border) !important;
        background: var(--sidebar-bg) !important;
        color: var(--sidebar-text) !important;
        transition: all 0.3s ease !important;
        font-family: var(--font-family) !important;
        margin: 0 0.25rem !important;
    }

    .paginate_button:hover {
        background: var(--sidebar-hover) !important;
        border-color: var(--sidebar-hover-border) !important;
        color: var(--sidebar-hover-text) !important;
    }

    .paginate_button.current {
        background: var(--sidebar-active) !important;
        border-color: var(--sidebar-active-border) !important;
        color: var(--sidebar-active-text) !important;
    }

    .paginate_button.disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }

    /* DataTables Length Selector */
    .dataTables_length select {
        padding: 0.5rem 2rem 0.5rem 1rem !important;
        border-radius: 12px !important;
        border: 1.5px solid var(--sidebar-border) !important;
        background: var(--sidebar-bg) !important;
        color: var(--sidebar-text) !important;
        font-family: var(--font-family) !important;
        appearance: none !important;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%234e1f00' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 16px 12px !important;
    }

    .dataTables_length select:focus {
        outline: none !important;
        border-color: var(--sidebar-active-border) !important;
        box-shadow: 0 0 0 0.2rem rgba(78, 31, 0, 0.25) !important;
    }

    /* DataTables Info */
    .dataTables_info {
        padding-top: 1rem !important;
        color: var(--sidebar-text) !important;
        font-family: var(--font-family) !important;
    }

    /* DataTables Search */
    .dataTables_filter input {
        padding: 0.5rem 1rem !important;
        border-radius: 12px !important;
        border: 1.5px solid var(--sidebar-border) !important;
        background: var(--sidebar-bg) !important;
        color: var(--sidebar-text) !important;
        font-family: var(--font-family) !important;
        width: 250px !important;
        transition: all 0.3s ease !important;
    }

    .dataTables_filter input:focus {
        outline: none !important;
        border-color: var(--sidebar-active-border) !important;
        box-shadow: 0 0 0 0.2rem rgba(78, 31, 0, 0.25) !important;
    }

    /* DataTables Export Buttons */
    .dt-buttons .btn {
        padding: 0.5rem 1rem !important;
        border-radius: 12px !important;
        border: 1.5px solid var(--sidebar-border) !important;
        background: var(--sidebar-bg) !important;
        color: var(--sidebar-text) !important;
        font-family: var(--font-family) !important;
        margin-right: 0.5rem !important;
        transition: all 0.3s ease !important;
    }

    .dt-buttons .btn:hover {
        background: var(--sidebar-hover) !important;
        border-color: var(--sidebar-hover-border) !important;
        color: var(--sidebar-hover-text) !important;
    }

    /* Dark mode styles */
    [data-bs-theme="dark"] .paginate_button {
        background: var(--sidebar-bg) !important;
        border-color: var(--sidebar-border) !important;
        color: var(--sidebar-text) !important;
    }

    [data-bs-theme="dark"] .paginate_button:hover {
        background: var(--sidebar-hover) !important;
        border-color: var(--sidebar-hover-border) !important;
        color: var(--sidebar-hover-text) !important;
    }

    [data-bs-theme="dark"] .paginate_button.current {
        background: var(--sidebar-active) !important;
        border-color: var(--sidebar-active-border) !important;
        color: var(--sidebar-active-text) !important;
    }

    [data-bs-theme="dark"] .dataTables_length select {
        background-color: var(--sidebar-bg) !important;
        border-color: var(--sidebar-border) !important;
        color: var(--sidebar-text) !important;
    }

    [data-bs-theme="dark"] .dataTables_filter input {
        background-color: var(--sidebar-bg) !important;
        border-color: var(--sidebar-border) !important;
        color: var(--sidebar-text) !important;
    }

    [data-bs-theme="dark"] .dt-buttons .btn {
        background-color: var(--sidebar-bg) !important;
        border-color: var(--sidebar-border) !important;
        color: var(--sidebar-text) !important;
    }

    /* Add these styles after the existing styles */
    @keyframes highlight-row {
        0% {
            background-color: rgba(78, 31, 0, 0.1);
        }

        25% {
            background-color: rgba(78, 31, 0, 0.2);
        }

        50% {
            background-color: rgba(78, 31, 0, 0.1);
        }

        75% {
            background-color: rgba(78, 31, 0, 0.2);
        }

        100% {
            background-color: transparent;
        }
    }

    .highlight-update {
        animation: highlight-row 1.5s ease-in-out;
    }

    /* Dark mode support for highlight */
    [data-bs-theme="dark"] .highlight-update {
        animation: highlight-row-dark 1.5s ease-in-out;
    }

    @keyframes highlight-row-dark {
        0% {
            background-color: rgba(255, 255, 255, 0.1);
        }

        25% {
            background-color: rgba(255, 255, 255, 0.2);
        }

        50% {
            background-color: rgba(255, 255, 255, 0.1);
        }

        75% {
            background-color: rgba(255, 255, 255, 0.2);
        }

        100% {
            background-color: transparent;
        }
    }

    /* Toast Styling */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1060;
    }

    .toast {
        background: var(--sidebar-bg);
        border: 1.5px solid var(--sidebar-border);
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .toast.bg-success {
        border-color: #198754;
    }

    .toast.bg-danger {
        border-color: #dc3545;
    }

    .toast-header {
        border-bottom: 1.5px solid var(--sidebar-border);
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
        padding: 0.75rem 1rem;
    }

    .toast-body {
        padding: 1rem;
        font-family: var(--font-family);
    }

    [data-bs-theme="dark"] .toast {
        background: var(--sidebar-bg);
        border-color: var(--sidebar-border);
    }

    [data-bs-theme="dark"] .toast-header {
        border-color: var(--sidebar-border);
    }

    /* Add these styles to your existing CSS */
    .quick-search .dropdown-menu {
        padding: 0.5rem 0;
        margin: 0.125rem 0 0;
        border: 1px solid rgba(0, 0, 0, .15);
        border-radius: 0.25rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, .175);
    }

    .quick-search .dropdown-header {
        padding: 0.5rem 1rem;
        font-weight: 600;
        color: #6d4e1f;
        background-color: #f8f9fa;
    }

    .quick-search .dropdown-item {
        padding: 0.5rem 1rem;
        border-bottom: 1px solid #f0f0f0;
    }

    .quick-search .dropdown-item:last-child {
        border-bottom: none;
    }

    .quick-search .dropdown-item:hover {
        background-color: #f8f9fa;
    }

    .quick-search .dropdown-item small {
        display: block;
        margin-top: 0.25rem;
    }
</style>

<!-- Add this right after the opening body tag -->
<div class="toast-container">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <i class="bi me-2" id="toast-icon"></i>
            <strong class="me-auto" id="toast-title">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toast-message"></div>
    </div>
</div>

<script>
    const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    // Human-readable time ago function for JS
    function timeAgoJS(dateString) {
        const now = new Date();
        const date = new Date(dateString);
        const diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'just now';
        const units = [{
                s: 31536000,
                n: 'year'
            },
            {
                s: 2592000,
                n: 'month'
            },
            {
                s: 604800,
                n: 'week'
            },
            {
                s: 86400,
                n: 'day'
            },
            {
                s: 3600,
                n: 'hour'
            },
            {
                s: 60,
                n: 'minute'
            }
        ];
        for (const u of units) {
            const val = Math.floor(diff / u.s);
            if (val >= 1) return val + ' ' + u.n + (val > 1 ? 's' : '') + ' ago';
        }
        return date.toLocaleDateString();
    }

    // Toast notification function
    function showToast(title, message, type = 'success') {
        const toast = document.getElementById('toast');
        const toastTitle = document.getElementById('toast-title');
        const toastMessage = document.getElementById('toast-message');
        const toastIcon = document.getElementById('toast-icon');

        // Set toast style based on type
        toast.className = 'toast';
        if (type === 'success') {
            toast.classList.add('bg-success', 'text-white');
            toastIcon.className = 'bi bi-check-circle-fill';
        } else if (type === 'error') {
            toast.classList.add('bg-danger', 'text-white');
            toastIcon.className = 'bi bi-exclamation-circle-fill';
        }

        // Set content
        toastTitle.textContent = title;
        toastMessage.textContent = message;

        // Show toast
        const bsToast = new bootstrap.Toast(toast, {
            autohide: true,
            delay: 3000
        });
        bsToast.show();
    }

    // JavaScript version of getEstimatedTime function
    function getEstimatedTime(ticket) {
        const subject = (ticket.subject || '').toLowerCase().trim();
        const type = (ticket.type || '').toLowerCase();
        const subtype = (ticket.ticket_subtype || '').toLowerCase().trim();

        // Exact subject/subtype mapping
        const map = {
            'create estimates in qb': '5 Minutes',
            'converting estimate into actual invoice once payment received': '5 Minutes',
            'convert estimate to invoice': '5 Minutes',
            'create payment link': '5 Minutes',
            'creating payment link in the flywire and tazapay for customers payment': '5 Minutes',
            'updating payments in qb and giving paid invoice to sales team': '5 Minutes',
            'modification in the estimates/invoices if any new changes coming from customer but that are sometime': '10 Minutes',
            'normal customers / corporate customer  payments follow up': '5 Minutes',
            'suppliers payment follow up for containing ticketing and packages': '5 Minutes',
            'initial training on qb for estimate creations': '30 Minutes',
            'sometime requests for separate payment receipts for the customers': '10 Minutes',
            'customers refund': '30 to 45 Days',
            'payment from amex card (cc)': '10 Minutes'
        };

        if (map[subject]) {
            return map[subject];
        }
        if (subtype && map[subtype]) {
            return map[subtype];
        }
        // Default for supplier tickets
        if (type === 'supplier') {
            return '5 Minutes';
        }
        // Default for general tickets
        if (type === 'general') {
            return '';
        }
        return '';
    }

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('dashboard-dark-mode', document.body.classList.contains('dark-mode'));
        updateMobileModeIcon();
    }

    function updateMobileModeIcon() {
        var icon = document.querySelector('.mobile-bottom-navbar .mode i');
        if (!icon) return;
        if (document.body.classList.contains('dark-mode')) {
            icon.classList.remove('fa-moon');
            icon.classList.add('fa-sun');
        } else {
            icon.classList.remove('fa-sun');
            icon.classList.add('fa-moon');
        }
    }

    window.onload = function() {
        if (localStorage.getItem('dashboard-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
        }
        updateMobileModeIcon();
    }

    function sidebarLogout() {
        window.location.href = 'logout.php';
    }

    $(document).ready(function() {
        // Initialize DataTable for open tickets
        const openTable = $('#openTicketsTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
            buttons: [{
                    extend: 'copy',
                    className: 'btn',
                    text: '<i class="bi bi-clipboard"></i> Copy'
                },
                {
                    extend: 'csv',
                    className: 'btn',
                    text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV'
                },
                {
                    extend: 'excel',
                    className: 'btn',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel'
                },
                {
                    extend: 'pdf',
                    className: 'btn',
                    text: '<i class="bi bi-file-earmark-pdf"></i> PDF'
                },
                {
                    extend: 'print',
                    className: 'btn',
                    text: '<i class="bi bi-printer"></i> Print'
                }
            ],
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            language: {
                paginate: {
                    first: '<i class="bi bi-chevron-double-left"></i>',
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>',
                    last: '<i class="bi bi-chevron-double-right"></i>'
                }
            },
            columnDefs: [{
                    targets: 1, // User column
                    type: 'string',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'filter') {
                            if (typeof data === 'string') {
                                return data.trim();
                            }
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = data;
                            return tempDiv.textContent.trim();
                        }
                        return data;
                    }
                },
                {
                    targets: <?php echo $isAdmin ? '4' : '3'; ?>, // Priority column
                    type: 'string',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'filter') {
                            const priorityMatch = data.match(/>([A-Z]+)</);
                            return priorityMatch ? priorityMatch[1] : '';
                        }
                        return data;
                    }
                }
            ],
            ordering: false
        });

        // Initialize DataTable for closed tickets
        const closedTable = $('#closedTicketsTable').DataTable({
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
            buttons: [{
                    extend: 'copy',
                    className: 'btn',
                    text: '<i class="bi bi-clipboard"></i> Copy'
                },
                {
                    extend: 'csv',
                    className: 'btn',
                    text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV'
                },
                {
                    extend: 'excel',
                    className: 'btn',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel'
                },
                {
                    extend: 'pdf',
                    className: 'btn',
                    text: '<i class="bi bi-file-earmark-pdf"></i> PDF'
                },
                {
                    extend: 'print',
                    className: 'btn',
                    text: '<i class="bi bi-printer"></i> Print'
                }
            ],
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            language: {
                paginate: {
                    first: '<i class="bi bi-chevron-double-left"></i>',
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>',
                    last: '<i class="bi bi-chevron-double-right"></i>'
                }
            },
            columnDefs: [{
                    targets: 1, // User column
                    type: 'string',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'filter') {
                            if (typeof data === 'string') {
                                return data.trim();
                            }
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = data;
                            return tempDiv.textContent.trim();
                        }
                        return data;
                    }
                },
                {
                    targets: <?php echo $isAdmin ? '4' : '3'; ?>, // Priority column
                    type: 'string',
                    render: function(data, type, row) {
                        if (type === 'sort' || type === 'filter') {
                            const priorityMatch = data.match(/>([A-Z]+)</);
                            return priorityMatch ? priorityMatch[1] : '';
                        }
                        return data;
                    }
                }
            ],
            ordering: false
        });

        // Priority filter functionality
        $('#priorityFilter').on('change', function() {
            const selectedPriority = $(this).val();

            // Custom filtering function for priority
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (!selectedPriority) return true; // Show all if no priority selected

                // Get the priority cell content - adjust index based on admin status
                const priorityIndex = <?php echo $isAdmin ? '4' : '3'; ?>; // Priority column index
                const priorityCell = data[priorityIndex];

                // Try different methods to extract priority
                let priority = '';

                // Method 1: Direct text extraction
                if (typeof priorityCell === 'string') {
                    priority = priorityCell.trim();
                }

                // Method 2: Extract from badge
                if (!priority) {
                    const priorityMatch = priorityCell.match(/>([A-Z]+)</);
                    priority = priorityMatch ? priorityMatch[1] : '';
                }

                // Method 3: Extract from badge text
                if (!priority) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = priorityCell;
                    priority = tempDiv.textContent.trim();
                }

                return priority === selectedPriority;
            });

            // Apply filter to both tables
            openTable.draw();
            closedTable.draw();

            // Remove the custom filter function after applying
            $.fn.dataTable.ext.search.pop();
        });

        // User filter functionality (admin only)
        <?php if ($isAdmin): ?>
            $('#userFilter').on('change', function() {
                const selectedUser = $(this).val();

                // Custom filtering function for user
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    if (!selectedUser) return true; // Show all if no user selected

                    // Get the user cell content
                    const userCell = data[1]; // User name is in the 2nd column (index 1)

                    // Try different methods to extract user name
                    let userName = '';

                    // Method 1: Direct text extraction
                    if (typeof userCell === 'string') {
                        userName = userCell.trim();
                    }

                    // Method 2: Extract from HTML if needed
                    if (!userName) {
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = userCell;
                        userName = tempDiv.textContent.trim();
                    }

                    // Method 3: Try to get the text content directly
                    if (!userName && userCell) {
                        userName = $(userCell).text().trim();
                    }

                    // Case-insensitive comparison
                    return userName.toLowerCase() === selectedUser.toLowerCase();
                });

                // Apply filter to both tables
                openTable.draw();
                closedTable.draw();

                // Remove the custom filter function after applying
                $.fn.dataTable.ext.search.pop();
            });
        <?php endif; ?>

        // Ticket Management Modal
        const ticketModal = document.getElementById('ticketModal');
        let originalEstimatedTime = null;
        if (ticketModal) {
            ticketModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const ticketId = button.getAttribute('data-ticket-id');
                const ticketType = button.getAttribute('data-ticket-type');
                const ticketPriority = button.getAttribute('data-ticket-priority');
                const ticketStatus = button.getAttribute('data-ticket-status');
                const ticketEstimatedTime = button.getAttribute('data-ticket-estimated-time');

                // Set form values
                document.getElementById('ticketId').value = ticketId;
                document.getElementById('ticketType').value = ticketType;
                document.getElementById('ticketPriority').value = ticketPriority;
                document.getElementById('ticketStatus').value = ticketStatus;
                document.getElementById('ticketEstimatedTime').value = ticketEstimatedTime || '';
                document.getElementById('ticketComment').value = '';

                // Store the original estimated time for change detection
                originalEstimatedTime = ticketEstimatedTime || '';
            });

            // Handle ticket update
            document.getElementById('updateTicketBtn').addEventListener('click', async function() {
                const form = document.getElementById('ticketUpdateForm');
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());

                // Add estimated_time_changed flag if estimated_time was changed
                const currentEstimatedTime = data.estimated_time || '';
                data.estimated_time_changed = (currentEstimatedTime !== originalEstimatedTime);

                try {
                    const response = await fetch('api/update-ticket.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Show success toast
                        showToast('Success', result.message, 'success');

                        // Close modal
                        bootstrap.Modal.getInstance(ticketModal).hide();

                        // Find and highlight the updated row
                        const ticketId = data.ticket_id;
                        const ticketType = data.ticket_type;
                        const table = ticketType === 'Estimate' ? openTable : closedTable;

                        // Find the row by ticket ID
                        const row = table.row((idx, rowData) => {
                            // Extract ticket ID from the first column
                            const rowTicketId = rowData[0].toString();
                            return rowTicketId === ticketId.toString();
                        }).node();

                        if (row) {
                            // Remove any existing highlight class
                            row.classList.remove('highlight-update');

                            // Force a reflow to restart the animation
                            void row.offsetWidth;

                            // Add highlight class
                            row.classList.add('highlight-update');

                            // Update the row data
                            const rowData = table.row(row).data();
                            if (data.priority) {
                                rowData[<?php echo $isAdmin ? '4' : '3'; ?>] = `<span class="badge ticket-priority-badge ${getPriorityClass(data.priority)}">
                                    <i class="bi bi-flag-fill"></i>
                                    ${data.priority}
                                </span>`;
                            }
                            if (data.status) {
                                rowData[<?php echo $isAdmin ? '5' : '4'; ?>] = `<span class="badge ${data.status === 'CLOSED' ? 'bg-success' : data.status === 'IN_PROGRESS' ? 'bg-info' : 'bg-warning text-dark'}">
                                    <i class="bi ${data.status === 'CLOSED' ? 'bi-check-circle-fill' : data.status === 'IN_PROGRESS' ? 'bi-arrow-repeat' : 'bi-clock-fill'}"></i>
                                    ${data.status}
                                </span>`;
                            }
                            if (data.estimated_time) {
                                rowData[<?php echo $isAdmin ? '6' : '5'; ?>] = data.estimated_time;
                            }

                            // Update the row and redraw
                            table.row(row).data(rowData).draw(false);
                        }
                    } else {
                        throw new Error(result.error || 'Failed to update ticket');
                    }
                } catch (error) {
                    // Show error toast
                    showToast('Error', error.message, 'error');
                    console.error('Error details:', error);
                }
            });
        }

        // View Ticket Modal
        const viewTicketModal = document.getElementById('viewTicketModal');
        if (viewTicketModal) {
            viewTicketModal.addEventListener('show.bs.modal', async function(event) {
                const button = event.relatedTarget;
                const ticketId = button.getAttribute('data-ticket-id');
                const ticketType = button.getAttribute('data-ticket-type');
                const ticketEstimatedTime = button.getAttribute('data-ticket-estimated-time');
                const detailsContainer = document.getElementById('ticketDetails');

                try {
                    const response = await fetch(`api/get-ticket.php?id=${ticketId}&type=${ticketType}`);
                    const result = await response.json();

                    if (result.success) {
                        const ticket = result.ticket;
                        // Add the estimated time to the ticket object
                        ticket.estimated_time = ticketEstimatedTime;

                        let html = `
                            <div class="ticket-details-section">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-ticket-detailed"></i>
                                                Ticket ID
                                            </div>
                                            <div class="ticket-detail-value">TCKT-${String(ticket.id).padStart(6, '0')}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-tag"></i>
                                                Type
                                            </div>
                                            <div class="ticket-detail-value">${ticket.type}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-person"></i>
                                                Created By
                                            </div>
                                            <div class="ticket-detail-value">${ticket.user_name}</div>
                                            <div class="ticket-meta">${ticket.user_email}</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-calendar"></i>
                                                Created On
                                            </div>
                                            <div class="ticket-detail-value">${new Date(ticket.created_at).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="ticket-details-section">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-flag"></i>
                                                Priority
                                            </div>
                                            <div class="ticket-detail-value">
                                                <span class="badge ticket-priority-badge ${getPriorityClass(ticket.priority)}">
                                                    <i class="bi bi-flag-fill"></i>
                                                    ${ticket.priority}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-circle"></i>
                                                Status
                                            </div>
                                            <div class="ticket-detail-value">
                                                <span class="badge ${ticket['status'] === 'CLOSED' ? 'bg-success' : ticket['status'] === 'IN_PROGRESS' ? 'bg-info' : 'bg-warning text-dark'}">
                                                    <i class="bi ${ticket['status'] === 'CLOSED' ? 'bi-check-circle-fill' : ticket['status'] === 'IN_PROGRESS' ? 'bi-arrow-repeat' : 'bi-clock-fill'}"></i>
                                                    ${ticket['status']}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-clock"></i>
                                                Estimated Time
                                            </div>
                                            <div class="ticket-detail-value">
                                                ${ticket.estimated_time}
                                                ${ticket.time_change_comment ? `<div class="text-info small mt-1">${ticket.time_change_comment}</div>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;

                        // Add type-specific fields
                        if (ticket.type === 'Estimate') {
                            html += `
                                <div class="ticket-details-section">
                                    <h6>
                                        <i class="bi bi-person-vcard"></i>
                                        Customer Information
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-person-badge"></i>
                                                    Customer Name
                                                </div>
                                                <div class="ticket-detail-value">${ticket.customer_name}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-person-workspace"></i>
                                                    Consultant
                                                </div>
                                                <div class="ticket-detail-value">${ticket.consultant_name}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-envelope"></i>
                                                    Email
                                                </div>
                                                <div class="ticket-detail-value">${ticket.email}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-telephone"></i>
                                                    Contact Number
                                                </div>
                                                <div class="ticket-detail-value">${ticket.contact_number}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ticket-detail-item">
                                        <div class="ticket-detail-label">
                                            <i class="bi bi-geo-alt"></i>
                                            Billing Address
                                        </div>
                                        <div class="ticket-detail-value">${ticket.billing_address}</div>
                                    </div>
                                </div>

                                <div class="ticket-details-section">
                                    <h6>
                                        <i class="bi bi-calendar-check"></i>
                                        Service Details
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-calendar-date"></i>
                                                    Service Date
                                                </div>
                                                <div class="ticket-detail-value">${new Date(ticket.service_date).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-people"></i>
                                                    Number of Persons
                                                </div>
                                                <div class="ticket-detail-value">${ticket.number_of_persons}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-currency-dollar"></i>
                                                    Rate per Person
                                                </div>
                                                <div class="ticket-detail-value">${ticket.rate_per_person}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-cash-stack"></i>
                                                    Total Amount
                                                </div>
                                                <div class="ticket-detail-value">${ticket.total_amount}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ticket-detail-item">
                                        <div class="ticket-detail-label">
                                            <i class="bi bi-box-seam"></i>
                                            Package Details
                                        </div>
                                        <div class="ticket-detail-value">${ticket.package_details}</div>
                                    </div>
                                </div>`;
                        } else if (ticket.type === 'Supplier') {
                            html += `
                                <div class="ticket-details-section">
                                    <h6>
                                        <i class="bi bi-credit-card"></i>
                                        Payment Information
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-airplane"></i>
                                                    Travel Date
                                                </div>
                                                <div class="ticket-detail-value">${new Date(ticket.travel_date).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-calendar-event"></i>
                                                    Due Date
                                                </div>
                                                <div class="ticket-detail-value">${new Date(ticket.due_date).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-wallet2"></i>
                                                    Payment Type
                                                </div>
                                                <div class="ticket-detail-value">${ticket.payment_type}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-currency-exchange"></i>
                                                    Supplier Invoice Currency
                                                </div>
                                                <div class="ticket-detail-value">${ticket.supplier_invoice_currency}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-currency-bitcoin"></i>
                                                    Supplier Local Currency
                                                </div>
                                                <div class="ticket-detail-value">${ticket.supplier_local_currency}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ticket-detail-item">
                                        <div class="ticket-detail-label">
                                            <i class="bi bi-bank"></i>
                                            Bank Details
                                        </div>
                                        <div class="ticket-detail-value">${ticket.bank_details}</div>
                                    </div>
                                </div>

                                <div class="ticket-details-section">
                                    <h6>
                                        <i class="bi bi-paperclip"></i>
                                        Attachments
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                    Supplier Invoice
                                                </div>
                                                ${ticket.supplier_invoice_path ? 
                                                    `<a href="uploads/supplier_invoices/${ticket.supplier_invoice_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>` : 
                                                    '<span class="text-muted">Not uploaded</span>'}
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                    Customer Invoice
                                                </div>
                                                ${ticket.customer_invoice_path ? 
                                                    `<a href="uploads/customer_invoices/${ticket.customer_invoice_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>` : 
                                                    '<span class="text-muted">Not uploaded</span>'}
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="ticket-detail-item">
                                                <div class="ticket-detail-label">
                                                    <i class="bi bi-receipt"></i>
                                                    Payment Proof
                                                </div>
                                                ${ticket.payment_proof_path ? 
                                                    `<a href="uploads/payment_proofs/${ticket.payment_proof_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>` : 
                                                    '<span class="text-muted">Not uploaded</span>'}
                                            </div>
                                        </div>
                                    </div>
                                </div>`;
                        }

                        // Add admin comments (without template literals, using normal JS)
                        if (!isAdmin && ticket.comments && ticket.comments.length > 0) {
                            var section = document.createElement('div');
                            section.className = 'ticket-details-section';
                            var h6 = document.createElement('h6');
                            h6.innerHTML = '<i class="bi bi-clock-history"></i> Admin Updates';
                            section.appendChild(h6);
                            var ul = document.createElement('ul');
                            ul.className = 'timeline list-unstyled';
                            ticket.comments.forEach(function(comment) {
                                var icon = 'bi-person-badge';
                                var badgeClass = 'bg-primary';
                                if (comment.comment.startsWith('Priority changed')) {
                                    icon = 'bi-flag';
                                    badgeClass = 'bg-warning';
                                } else if (comment.comment.startsWith('Status changed')) {
                                    icon = 'bi-circle';
                                    badgeClass = 'bg-info';
                                } else if (comment.comment.startsWith('Estimated time changed')) {
                                    icon = 'bi-clock';
                                    badgeClass = 'bg-purple';
                                }
                                var li = document.createElement('li');
                                li.className = 'timeline-item mb-4';
                                var topDiv = document.createElement('div');
                                topDiv.className = 'd-flex align-items-center mb-1';
                                var badge = document.createElement('span');
                                badge.className = 'badge ' + badgeClass + ' me-2';
                                var badgeIcon = document.createElement('i');
                                badgeIcon.className = 'bi ' + icon;
                                badge.appendChild(badgeIcon);
                                badge.appendChild(document.createTextNode(' ' + comment.user_name));
                                var dateSpan = document.createElement('span');
                                dateSpan.className = 'text-muted small';
                                dateSpan.textContent = new Date(comment.created_at).toLocaleString('en-US', {
                                    month: 'long',
                                    day: 'numeric',
                                    year: 'numeric'
                                });
                                topDiv.appendChild(badge);
                                topDiv.appendChild(dateSpan);
                                var commentDiv = document.createElement('div');
                                commentDiv.className = 'timeline-comment bg-light p-3 rounded border';
                                commentDiv.innerHTML = comment.comment;
                                li.appendChild(topDiv);
                                li.appendChild(commentDiv);
                                ul.appendChild(li);
                            });
                            section.appendChild(ul);
                            detailsContainer.appendChild(section);
                        } else {
                            detailsContainer.innerHTML = html;
                        }

                        // After the main details HTML is built, add:
                        if (ticket.description) {
                            html += `
                                <div class="ticket-details-section">
                                    <h6><i class="bi bi-card-text"></i> Description</h6>
                                    <div class="ticket-description">${ticket.description}</div>
                                </div>
                            `;
                        }
                        // Show admin comments below Description
                        if (ticket.comments && ticket.comments.length > 0) {
                            html += `
                                <div class="ticket-details-section">
                                    <h6><i class="bi bi-person-badge"></i> Admin Comments</h6>
                                    <ul class="timeline list-unstyled">
                                        ${ticket.comments.map(comment => `
                                            <li class="timeline-item mb-4">
                                                <div class="d-flex align-items-center mb-1">
                                                    <span class="badge bg-purple me-2"><i class="bi bi-person-badge"></i> ${comment.user_name}</span>
                                                    <span class="text-muted small">${new Date(comment.created_at).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</span>
                                                </div>
                                                <div class="timeline-comment bg-light p-3 rounded border">${comment.comment}</div>
                                            </li>
                                        `).join('')}
                                    </ul>
                                </div>
                            `;
                        }
                        const imageFields = [{
                                key: 'supporting_image_path',
                                label: 'Supporting Image',
                                folder: 'uploads/supporting_images/'
                            },
                            {
                                key: 'supplier_invoice_path',
                                label: 'Supplier Invoice',
                                folder: 'uploads/supplier_invoices/'
                            },
                            {
                                key: 'customer_invoice_path',
                                label: 'Customer Invoice',
                                folder: 'uploads/customer_invoices/'
                            },
                            {
                                key: 'payment_proof_path',
                                label: 'Payment Proof',
                                folder: 'uploads/payment_proofs/'
                            }
                        ];
                        let imagesHtml = '';
                        imageFields.forEach(field => {
                            if (ticket[field.key]) {
                                imagesHtml += `<div class="mb-3"><strong>${field.label}:</strong><br><img src="${field.folder}${ticket[field.key]}" alt="${field.label}" style="max-width:320px;max-height:220px;border-radius:8px;box-shadow:0 1px 4px #0002;"></div>`;
                            }
                        });
                        if (imagesHtml) {
                            html += `<div class="ticket-details-section"><h6><i class="bi bi-image"></i> Images</h6>${imagesHtml}</div>`;
                        }

                        detailsContainer.innerHTML = html;
                    } else {
                        throw new Error(result.error || 'Failed to load ticket details');
                    }
                } catch (error) {
                    detailsContainer.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
                    console.error('Error details:', error);
                }
            });
        }

        // Helper function for priority badge classes
        function getPriorityClass(priority) {
            switch (priority) {
                case 'URGENT':
                    return 'bg-danger';
                case 'HIGH':
                    return 'bg-warning text-dark';
                case 'MEDIUM':
                    return 'bg-info text-dark';
                case 'LOW':
                    return 'bg-success';
                default:
                    return 'bg-secondary';
            }
        }
    });

    // Add these new functions for quick ticket search
    let searchTimeout = null;

    document.getElementById('quickTicketSearch').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const searchTerm = e.target.value.trim();

        if (searchTerm.length < 1) {
            document.getElementById('quickSearchResults').style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            // Search in all ticket types
            const ticketTypes = ['estimate', 'supplier', 'general'];
            let resultsFound = false;

            Promise.all(ticketTypes.map(type =>
                fetch(`api/tickets.php?type=${type}&search=${searchTerm}`)
                .then(response => response.json())
                .then(data => ({
                    type,
                    data
                }))
            )).then(results => {
                const resultsDiv = document.getElementById('quickSearchResults');
                resultsDiv.innerHTML = '';

                results.forEach(({
                    type,
                    data
                }) => {
                    if (data.tickets && data.tickets.length > 0) {
                        resultsFound = true;
                        const typeHeader = document.createElement('div');
                        typeHeader.className = 'dropdown-header';
                        typeHeader.textContent = type.charAt(0).toUpperCase() + type.slice(1) + ' Tickets';
                        resultsDiv.appendChild(typeHeader);

                        data.tickets.forEach(ticket => {
                            const item = document.createElement('a');
                            item.className = 'dropdown-item';
                            item.href = `tickets.php?id=${ticket.id}&type=${type}`;
                            item.innerHTML = `
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>#${ticket.id}</strong> - ${ticket.subject}
                                    </div>
                                    <span class="badge ${getPriorityClass(ticket.priority)}">${ticket.priority}</span>
                                </div>
                                <small class="text-muted">${ticket.status} - ${timeAgo(ticket.created_at)}</small>
                            `;
                            resultsDiv.appendChild(item);
                        });
                    }
                });

                if (!resultsFound) {
                    const noResults = document.createElement('div');
                    noResults.className = 'dropdown-item text-muted';
                    noResults.textContent = 'No tickets found';
                    resultsDiv.appendChild(noResults);
                }

                resultsDiv.style.display = 'block';
            });
        }, 300);
    });

    function searchTicket() {
        const searchTerm = document.getElementById('quickTicketSearch').value.trim();
        if (searchTerm) {
            window.location.href = `tickets.php?id=${searchTerm}`;
        }
    }

    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        const searchContainer = document.querySelector('.quick-search');
        const searchResults = document.getElementById('quickSearchResults');
        if (!searchContainer.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Add keyboard navigation for search
    document.getElementById('quickTicketSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            searchTicket();
        } else if (e.key === 'Escape') {
            document.getElementById('quickSearchResults').style.display = 'none';
        }
    });
</script>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="main-title">Tickets</h2>
                <div class="header-actions">
                    <div class="d-flex gap-2">
                        <!-- Quick Ticket Search -->
                        <div class="quick-search" style="position:relative;">
                            <div class="input-group">
                                <input type="text" id="quickTicketSearch" class="form-control" placeholder="Search ticket by ID..." style="min-width:200px;">
                                <button class="btn btn-primary" type="button" onclick="searchTicket()">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div id="quickSearchResults" class="dropdown-menu" style="width:100%;display:none;max-height:300px;overflow-y:auto;">
                            </div>
                        </div>
                        <?php if ($isAdmin): ?>
                            <button class="btn btn-primary" onclick="showNewTicketModal()">
                                <i class="bi bi-plus-lg"></i> New Ticket
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <ul class="nav nav-tabs mb-4" id="ticketTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="open-tab" data-bs-toggle="tab" data-bs-target="#open" type="button" role="tab" aria-controls="open" aria-selected="true">Open Tickets</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="closed-tab" data-bs-toggle="tab" data-bs-target="#closed" type="button" role="tab" aria-controls="closed" aria-selected="false">Closed Tickets</button>
            </li>
        </ul>
        <div class="tab-content" id="ticketTabsContent">
            <div class="tab-pane fade show active" id="open" role="tabpanel" aria-labelledby="open-tab">
                <div class="table-responsive">
                    <table id="openTicketsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Subject</th>
                                <?php if ($isAdmin): ?>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Est. Time</th>
                                    <th>Actions</th>
                                <?php else: ?>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Est. Time</th>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openTickets as $ticket): ?>
                                <tr>
                                    <td><?php echo $ticket['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ms-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($ticket['user_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($ticket['user_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td>
                                        <span class="badge ticket-priority-badge <?php echo getPriorityClass($ticket['priority']); ?>">
                                            <i class="bi bi-flag-fill"></i>
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $ticket['status'] === 'CLOSED' ? 'bg-success' : ($ticket['status'] === 'IN_PROGRESS' ? 'bg-info' : 'bg-warning text-dark'); ?>">
                                            <i class="bi <?php echo $ticket['status'] === 'CLOSED' ? 'bi-check-circle-fill' : ($ticket['status'] === 'IN_PROGRESS' ? 'bi-arrow-repeat' : 'bi-clock-fill'); ?>"></i>
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php
                                        $estimatedTime = $ticket['estimated_time'] ?? getDefaultEstimatedTime($ticket['subject']);
                                        error_log("Ticket ID: " . $ticket['id'] . ", Subject: " . $ticket['subject'] . ", Estimated Time: " . $estimatedTime);
                                        echo htmlspecialchars($estimatedTime);
                                        ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewTicketModal"
                                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                                data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <?php if (
                                                $isAdmin
                                            ): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ticketModal"
                                                    data-ticket-id="<?php echo $ticket['id']; ?>"
                                                    data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                    data-ticket-priority="<?php echo $ticket['priority']; ?>"
                                                    data-ticket-status="<?php echo $ticket['status']; ?>"
                                                    data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                    <i class="bi bi-gear"></i> Manage
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                onclick="window.location.href='timeline.php?id=<?php echo $ticket['id']; ?>&type=<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>'">
                                                <i class="bi bi-clock-history"></i> Timeline
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane fade" id="closed" role="tabpanel" aria-labelledby="closed-tab">
                <div class="table-responsive">
                    <table id="closedTicketsTable" class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <?php if ($isAdmin): ?>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Est. Time</th>
                                    <th>Actions</th>
                                <?php else: ?>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Est. Time</th>
                                    <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closedTickets as $ticket): ?>
                                <tr>
                                    <td><?php echo $ticket['id']; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ms-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($ticket['user_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($ticket['user_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-tag-fill"></i>
                                            <?php echo htmlspecialchars($ticket['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td>
                                        <span class="badge ticket-priority-badge <?php echo getPriorityClass($ticket['priority']); ?>">
                                            <i class="bi bi-flag-fill"></i>
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $ticket['status'] === 'CLOSED' ? 'bg-success' : ($ticket['status'] === 'IN_PROGRESS' ? 'bg-info' : 'bg-warning text-dark'); ?>">
                                            <i class="bi <?php echo $ticket['status'] === 'CLOSED' ? 'bi-check-circle-fill' : ($ticket['status'] === 'IN_PROGRESS' ? 'bi-arrow-repeat' : 'bi-clock-fill'); ?>"></i>
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php
                                        $estimatedTime = $ticket['estimated_time'] ?? getDefaultEstimatedTime($ticket['subject']);
                                        error_log("Ticket ID: " . $ticket['id'] . ", Subject: " . $ticket['subject'] . ", Estimated Time: " . $estimatedTime);
                                        echo htmlspecialchars($estimatedTime);
                                        ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewTicketModal"
                                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                                data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <?php if (
                                                $isAdmin
                                            ): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#ticketModal"
                                                    data-ticket-id="<?php echo $ticket['id']; ?>"
                                                    data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                    data-ticket-priority="<?php echo $ticket['priority']; ?>"
                                                    data-ticket-status="<?php echo $ticket['status']; ?>"
                                                    data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                    <i class="bi bi-gear"></i> Manage
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#timelineModal"
                                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                                data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>">
                                                <i class="bi bi-clock-history"></i> Timeline
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'components/bottom_navbar.php'; ?>

<!-- View Ticket Modal -->
<div class="modal fade" id="viewTicketModal" tabindex="-1" aria-labelledby="viewTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewTicketModalLabel">Ticket Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ticketDetails">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="toast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header">
            <strong class="me-auto" id="toast-title">Notification</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toast-message"></div>
    </div>
</div>

<!-- Ticket Management Modal -->
<?php if ($isAdmin): ?>
    <div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ticketModalLabel">Manage Ticket</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ticketUpdateForm">
                        <input type="hidden" id="ticketId" name="ticket_id">
                        <input type="hidden" id="ticketType" name="ticket_type">

                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" id="ticketPriority" name="priority" required>
                                <option value="LOW">Low</option>
                                <option value="MEDIUM">Medium</option>
                                <option value="HIGH">High</option>
                                <option value="URGENT">Urgent</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="ticketStatus" name="status" required>
                                <option value="OPEN">Open</option>
                                <option value="IN_PROGRESS">In Progress</option>
                                <option value="CLOSED">Closed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="ticketEstimatedTime" class="form-label">Estimated Time</label>
                            <select class="form-select" id="ticketEstimatedTime" name="estimated_time">
                                <option value="">Select time</option>
                                <optgroup label="Minutes">
                                    <option value="5 Minutes">5 Minutes</option>
                                    <option value="10 Minutes">10 Minutes</option>
                                    <option value="15 Minutes">15 Minutes</option>
                                    <option value="20 Minutes">20 Minutes</option>
                                    <option value="25 Minutes">25 Minutes</option>
                                    <option value="30 Minutes">30 Minutes</option>
                                    <option value="35 Minutes">35 Minutes</option>
                                    <option value="40 Minutes">40 Minutes</option>
                                    <option value="45 Minutes">45 Minutes</option>
                                    <option value="50 Minutes">50 Minutes</option>
                                    <option value="55 Minutes">55 Minutes</option>
                                    <option value="60 Minutes">60 Minutes</option>
                                    <option value="75 Minutes">75 Minutes</option>
                                    <option value="90 Minutes">90 Minutes</option>
                                </optgroup>
                                <optgroup label="Days">
                                    <option value="1 Day">1 Day</option>
                                    <option value="2 Days">2 Days</option>
                                    <option value="3 Days">3 Days</option>
                                    <option value="4 Days">4 Days</option>
                                    <option value="5 Days">5 Days</option>
                                    <option value="7 Days">7 Days</option>
                                    <option value="14 Days">14 Days</option>
                                    <option value="30 Days">30 Days</option>
                                    <option value="45 Days">45 Days</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Add Comment</label>
                            <textarea class="form-control" id="ticketComment" name="comment" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="updateTicketBtn">Update Ticket</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php html_end(); ?>

<!-- Add these in the head section -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<!-- Add these before the closing body tag -->
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>