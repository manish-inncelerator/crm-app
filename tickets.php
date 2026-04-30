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
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
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
    if ($diff < 60)
        return 'just now';
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

    // Fetch active users for owner reassignment
    $activeUsers = $database->select('users', ['id', 'name'], [
        'is_ex_employee' => 0,
        'ORDER' => ['name' => 'ASC']
    ]);

    // Fetch tickets based on user role
    $isAdmin = (bool)($dbUser['is_admin'] ?? false);

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
            // Get owner info
            $owner = $database->get('users', ['name'], ['id' => $ticket['owner_id']]);
            $ticket['owner_name'] = $owner ? $owner['name'] : 'Unassigned';
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
            // Get owner info
            $owner = $database->get('users', ['name'], ['id' => $ticket['owner_id']]);
            $ticket['owner_name'] = $owner ? $owner['name'] : 'Unassigned';
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
            // Get owner info
            $owner = $database->get('users', ['name'], ['id' => $ticket['owner_id']]);
            $ticket['owner_name'] = $owner ? $owner['name'] : 'Unassigned';
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
            // Get owner info
            $owner = $database->get('users', ['name'], ['id' => $ticket['owner_id']]);
            $ticket['owner_name'] = $owner ? $owner['name'] : 'Unassigned';
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
            // Get owner info
            $owner = $database->get('users', ['name'], ['id' => $ticket['owner_id']]);
            $ticket['owner_name'] = $owner ? $owner['name'] : 'Unassigned';
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
            // Get owner info
            $owner = $database->get('users', ['name'], ['id' => $ticket['owner_id']]);
            $ticket['owner_name'] = $owner ? $owner['name'] : 'Unassigned';
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
<link rel="stylesheet" href="assets/css/tickets.css?v=<?= time() ?>">

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

    function sidebarLogout() {
        window.location.href = 'logout.php';
    }

    // Function to handle ticket editing
    async function editTicket(id, type) {
        try {
            const response = await fetch(`api/get-ticket.php?id=${id}&type=${type}`);
            const result = await response.json();

            if (result.success) {
                const ticket = result.ticket;
                document.getElementById('editTicketId').value = ticket.id;
                document.getElementById('editTicketType').value = type;
                document.getElementById('editBookingReference').value = ticket.booking_reference || '';
                document.getElementById('editPriority').value = ticket.priority;
                document.getElementById('editDescription').value = ticket.description || '';

                // Show/hide type-specific fields
                const estimateFields = document.getElementById('editEstimateFields');
                if (type === 'estimate') {
                    estimateFields.style.display = 'block';
                    document.getElementById('editCustomerName').value = ticket.customer_name || '';
                    document.getElementById('editTotalAmount').value = ticket.total_amount || '';
                } else {
                    estimateFields.style.display = 'none';
                }

                const editModal = new bootstrap.Modal(document.getElementById('editTicketModal'));
                editModal.show();
            } else {
                alert('Error fetching ticket data: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while fetching ticket details.');
        }
    }


    // Auto-trim booking reference inputs
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('booking-ref-input')) {
            e.target.value = e.target.value.replace(/\s/g, '');
        }
    });

    $(document).ready(function () {
        // Handle Edit Ticket form submission
        const editTicketForm = document.getElementById('editTicketForm');
        if (editTicketForm) {
            editTicketForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                try {
                    const response = await fetch('api/edit-ticket.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();

                    if (result.success) {
                        showToast('Success', 'Ticket updated successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editTicketModal')).hide();
                        location.reload(); // Reload to show updated data in table
                    } else {
                        showToast('Error', result.error || 'Failed to update ticket', 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Error', 'An unexpected error occurred', 'danger');
                }
            });
        }

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
                targets: 2, // User column
                type: 'string',
                render: function (data, type, row) {
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
                targets: 5, // Priority column
                type: 'string',
                render: function (data, type, row) {
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
                targets: 2, // User column
                type: 'string',
                render: function (data, type, row) {
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
                targets: 6, // Priority column
                type: 'string',
                render: function (data, type, row) {
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
        $('#priorityFilter').on('change', function () {
            const selectedPriority = $(this).val();

            // Custom filtering function for priority
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (!selectedPriority) return true; // Show all if no priority selected

                // Get the priority cell content - index differs between tables
                const priorityIndex = (settings.sTableId === 'closedTicketsTable') ? 4 : 3; 
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
            $('#userFilter').on('change', function () {
                const selectedUser = $(this).val();

                // Custom filtering function for user
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if (!selectedUser) return true; // Show all if no user selected

                    // Get the user cell content (always index 1 for both tables)
                    const userCell = data[1]; 

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
            ticketModal.addEventListener('show.bs.modal', function (event) {
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

                // New fields
                const ownerId = button.getAttribute('data-ticket-owner-id');
                const expectedTimeline = button.getAttribute('data-ticket-expected-timeline');
                const delayReason = button.getAttribute('data-ticket-delay-reason');

                document.getElementById('ticketOwnerId').value = ownerId || '';
                document.getElementById('ticketExpectedTimeline').value = expectedTimeline ? expectedTimeline.replace(' ', 'T') : '';
                document.getElementById('ticketDelayReason').value = delayReason || '';

                // Handle Refund-specific statuses
                const subject = button.closest('tr').querySelector('td:nth-child(4)').textContent.trim().toLowerCase();
                const isRefund = subject.includes('refund');
                const refundContainer = document.getElementById('refundStatusContainer');
                const normalStatusContainer = document.getElementById('ticketStatus').parentElement;

                if (isRefund) {
                    refundContainer.classList.remove('d-none');
                    normalStatusContainer.classList.add('d-none');
                    document.getElementById('refundStatus').value = ticketStatus;
                    document.getElementById('ticketStatus').removeAttribute('required');
                    document.getElementById('refundStatus').setAttribute('required', 'required');
                } else {
                    refundContainer.classList.add('d-none');
                    normalStatusContainer.classList.remove('d-none');
                    document.getElementById('ticketStatus').setAttribute('required', 'required');
                    document.getElementById('refundStatus').removeAttribute('required');
                }

                // Store the original estimated time for change detection
                originalEstimatedTime = ticketEstimatedTime || '';
            });


            // Handle Update Ticket button click
            const updateTicketBtn = document.getElementById('updateTicketBtn');
            if (updateTicketBtn) {
                updateTicketBtn.addEventListener('click', async function () {
                    const form = document.getElementById('ticketUpdateForm');
                    const formData = new FormData(form);
                    const data = Object.fromEntries(formData.entries());

                    // If refund status is used, override normal status
                    if (data.refund_status && !document.getElementById('refundStatusContainer').classList.contains('d-none')) {
                        data.status = data.refund_status;
                    }

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
                                    const priorityIndex = (table.table().node().id === 'closedTicketsTable') ? 6 : 5;
                                    rowData[priorityIndex] = `<span class="badge ticket-priority-badge ${getPriorityClass(data.priority)}">
                                        <i class="bi bi-flag-fill"></i>
                                        ${data.priority}
                                    </span>`;
                                }
                                if (data.status) {
                                    let badgeClass = 'bg-secondary';
                                    let iconClass = 'bi-clock-fill';
                                    
                                    switch(data.status) {
                                        case 'CLOSED':
                                        case 'APPROVED':
                                        case 'PROCESSED':
                                            badgeClass = 'bg-success';
                                            iconClass = 'bi-check-circle-fill';
                                            break;
                                        case 'IN_PROGRESS':
                                        case 'UNDER_REVIEW':
                                            badgeClass = 'bg-info';
                                            iconClass = 'bi-arrow-repeat';
                                            break;
                                        case 'PENDING_APPROVAL':
                                            badgeClass = 'bg-orange';
                                            iconClass = 'bi-hourglass-split';
                                            break;
                                        case 'REJECTED':
                                            badgeClass = 'bg-danger';
                                            iconClass = 'bi-x-circle-fill';
                                            break;
                                        case 'SUBMITTED':
                                        case 'OPEN':
                                            badgeClass = 'bg-warning text-dark';
                                            iconClass = 'bi-clock-fill';
                                            break;
                                    }
                                    
                                    const statusIndex = (table.table().node().id === 'closedTicketsTable') ? 7 : 6;
                                    rowData[statusIndex] = `<span class="badge ${badgeClass}">
                                        <i class="bi ${iconClass}"></i>
                                        ${data.status}
                                    </span>`;
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
        });

        // View Ticket Modal
        const viewTicketModal = document.getElementById('viewTicketModal');
        if (viewTicketModal) {
            viewTicketModal.addEventListener('show.bs.modal', async function (event) {
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
                                                <i class="bi bi-person-check"></i>
                                                Current Owner
                                            </div>
                                            <div class="ticket-detail-value">${ticket.owner_name}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-hash"></i>
                                                Booking Reference
                                            </div>
                                            <div class="ticket-detail-value">
                                                <span class="badge bg-light text-dark border">${ticket.booking_reference || 'N/A'}</span>
                                            </div>
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
                                                Expected Timeline (SLA)
                                            </div>
                                            <div class="ticket-detail-value">
                                                ${ticket.expected_timeline ? new Date(ticket.expected_timeline).toLocaleString('en-US', { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '<span class="text-muted">Not set</span>'}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ticket-detail-item">
                                            <div class="ticket-detail-label">
                                                <i class="bi bi-exclamation-triangle"></i>
                                                Delay Reason
                                            </div>
                                            <div class="ticket-detail-value">
                                                ${ticket.delay_reason || '<span class="text-muted">None</span>'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>`;

                        // Add Approval Progress for Refunds
                        const isRefund = (ticket.ticket_subtype || '').toLowerCase().includes('refund');
                        if (isRefund) {
                            const steps = [
                                { id: 'SUBMITTED', label: 'Submitted', icon: 'bi-send' },
                                { id: 'UNDER_REVIEW', label: 'Review', icon: 'bi-search' },
                                { id: 'PENDING_APPROVAL', label: 'Pending', icon: 'bi-hourglass' },
                                { id: 'APPROVED', label: 'Approved', icon: 'bi-check2-circle' },
                                { id: 'PROCESSED', label: 'Processed', icon: 'bi-flag' }
                            ];
                            
                            let currentStepIndex = steps.findIndex(s => s.id === ticket.status);
                            if (ticket.status === 'REJECTED') currentStepIndex = 3; // Position of Approved/Rejected

                            let progressHtml = `
                                <div class="ticket-details-section">
                                    <h6><i class="bi bi-diagram-3"></i> Approval Flow</h6>
                                    <div class="approval-progress">
                                        ${steps.map((step, index) => {
                                            let statusClass = '';
                                            if (ticket.status === 'REJECTED' && index === 3) statusClass = 'rejected';
                                            else if (index < currentStepIndex) statusClass = 'completed';
                                            else if (index === currentStepIndex) statusClass = 'active';
                                            
                                            const label = (ticket.status === 'REJECTED' && index === 3) ? 'Rejected' : step.label;
                                            const icon = (ticket.status === 'REJECTED' && index === 3) ? 'bi-x-circle' : step.icon;

                                            return `
                                                <div class="approval-step ${statusClass}">
                                                    <div class="step-icon"><i class="bi ${icon}"></i></div>
                                                    <div class="step-label">${label}</div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            `;
                            html = progressHtml + html;
                        }

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
                            ticket.comments.forEach(function (comment) {
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
        // Add these new functions for quick ticket search
        const quickSearch = document.getElementById('quickTicketSearch');
        if (quickSearch) {
            quickSearch.addEventListener('input', function (e) {
                const query = e.target.value.trim();
                const resultsDiv = document.getElementById('quickSearchResults');
                const type = 'all';

                if (query.length < 2) {
                    resultsDiv.classList.remove('show');
                    return;
                }

                fetch(`api/get-ticket.php?action=search&query=${encodeURIComponent(query)}&type=${type}`)
                    .then(response => response.json())
                    .then(data => {
                        resultsDiv.innerHTML = '';
                        let resultsFound = false;

                        if (data.success && data.tickets && data.tickets.length > 0) {
                            resultsFound = true;
                            resultsDiv.classList.add('show');

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
                                    <small class="text-muted">${ticket.status} - ${timeAgoJS(ticket.created_at)}</small>
                                `;
                                resultsDiv.appendChild(item);
                            });
                        }

                        if (!resultsFound) {
                            const noResults = document.createElement('div');
                            noResults.className = 'dropdown-item text-muted';
                            noResults.textContent = 'No tickets found';
                            resultsDiv.appendChild(noResults);
                            resultsDiv.classList.add('show');
                        }
                    });
            });

            quickSearch.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    document.getElementById('quickSearchResults').classList.remove('show');
                } else if (e.key === 'Enter') {
                    searchTicket();
                }
            });
        }
    });

    function searchTicket() {
        const quickSearchInput = document.getElementById('quickTicketSearch');
        if (quickSearchInput) {
            const searchTerm = quickSearchInput.value.trim();
            if (searchTerm) {
                window.location.href = `tickets.php?id=${searchTerm}`;
            }
        }
    }

    // Close search results when clicking outside
    document.addEventListener('click', function (e) {
        const searchContainer = document.querySelector('.quick-search');
        const searchResults = document.getElementById('quickSearchResults');
        if (searchContainer && searchResults && !searchContainer.contains(e.target)) {
            searchResults.classList.remove('show');
        }
    });
</script>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        <div class="content-header mb-4">
            <div class="d-flex justify-content-between align-items-baseline">
                <h2 class="main-title mb-0">Tickets</h2>
                <div class="header-actions">
                    <div class="d-flex gap-2 align-items-center">
                        <!-- Quick Ticket Search -->
                        <div class="quick-search" style="position:relative;">
                            <div class="input-group">
                                <input type="text" id="quickTicketSearch" class="form-control"
                                    placeholder="Search ticket by ID..." style="min-width:200px; border: 1px solid var(--sidebar-border);">
                                <button class="btn btn-primary" type="button" onclick="searchTicket()">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                            <div id="quickSearchResults" class="dropdown-menu"
                                style="width:100%;display:none;max-height:300px;overflow-y:auto;">
                            </div>
                        </div>

                        <!-- Priority Filter -->
                        <select id="priorityFilter" class="form-select" style="width: auto; border: 1px solid var(--sidebar-border);">
                            <option value="">All Priorities</option>
                            <option value="LOW">Low</option>
                            <option value="MEDIUM">Medium</option>
                            <option value="HIGH">High</option>
                            <option value="URGENT">Urgent</option>
                        </select>

                        <!-- User Filter (Admin Only) -->
                        <?php if ($isAdmin && !empty($allUsers)): ?>
                            <select id="userFilter" class="form-select" style="width: auto; border: 1px solid var(--sidebar-border);">
                                <option value="">All Users</option>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?php echo htmlspecialchars($u['name']); ?>">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <button class="btn btn-primary" onclick="showNewTicketModal()">
                                <i class="bi bi-plus-lg"></i> New Ticket
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="ticket-card-glass">
            <ul class="nav nav-tabs border-0 mb-4" id="ticketTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-4 py-2 me-2 border-0 fw-semibold" id="open-tab" data-bs-toggle="tab" data-bs-target="#open" type="button"
                        role="tab" aria-controls="open" aria-selected="true" style="background-color: rgba(78, 31, 0, 0.05); color: #4e1f00;">
                        <i class="bi bi-envelope-open me-2"></i>Open Tickets
                        <span class="badge bg-primary ms-2 rounded-pill"><?php echo count($openTickets); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4 py-2 border-0 fw-semibold" id="closed-tab" data-bs-toggle="tab" data-bs-target="#closed" type="button"
                        role="tab" aria-controls="closed" aria-selected="false" style="background-color: rgba(78, 31, 0, 0.05); color: #6c757d;">
                        <i class="bi bi-check-circle me-2"></i>Closed Tickets
                        <span class="badge bg-secondary ms-2 rounded-pill"><?php echo count($closedTickets); ?></span>
                    </button>
                </li>
            </ul>
            <div class="tab-content" id="ticketTabsContent">
                <div class="tab-pane fade show active" id="open" role="tabpanel" aria-labelledby="open-tab">
                    <div class="table-responsive">
                        <table id="openTicketsTable" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Booking Ref</th>
                                <th>User</th>
                                <th>Owner</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($openTickets as $ticket): ?>
                                <tr>
                                    <td><?php echo $ticket['id']; ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ticket['booking_reference'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ms-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($ticket['user_name']); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($ticket['user_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="bi bi-person-check"></i>
                                            <?php echo htmlspecialchars($ticket['owner_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td>
                                        <span
                                            class="badge ticket-priority-badge <?php echo getPriorityClass($ticket['priority']); ?>">
                                            <i class="bi bi-flag-fill"></i>
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php 
                                                echo match($ticket['status']) {
                                                    'CLOSED', 'APPROVED', 'PROCESSED' => 'bg-success',
                                                    'IN_PROGRESS', 'UNDER_REVIEW' => 'bg-info',
                                                    'PENDING_APPROVAL' => 'bg-orange',
                                                    'REJECTED' => 'bg-danger',
                                                    'SUBMITTED', 'OPEN' => 'bg-warning text-dark',
                                                    default => 'bg-secondary'
                                                };
                                            ?>">
                                            <i
                                                class="bi <?php 
                                                    echo match($ticket['status']) {
                                                        'CLOSED', 'APPROVED', 'PROCESSED' => 'bi-check-circle-fill',
                                                        'IN_PROGRESS', 'UNDER_REVIEW' => 'bi-arrow-repeat',
                                                        'PENDING_APPROVAL' => 'bi-hourglass-split',
                                                        'REJECTED' => 'bi-x-circle-fill',
                                                        default => 'bi-clock-fill'
                                                    };
                                                ?>"></i>
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#viewTicketModal"
                                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                                data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                onclick="editTicket(<?php echo $ticket['id']; ?>, '<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>')">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <?php if (
                                                $isAdmin
                                            ): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#ticketModal"
                                                    data-ticket-id="<?php echo $ticket['id']; ?>"
                                                    data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                    data-ticket-priority="<?php echo $ticket['priority']; ?>"
                                                    data-ticket-status="<?php echo $ticket['status']; ?>"
                                                    data-ticket-owner-id="<?php echo htmlspecialchars($ticket['owner_id'] ?? ''); ?>"
                                                    data-ticket-expected-timeline="<?php echo htmlspecialchars($ticket['expected_timeline'] ?? ''); ?>"
                                                    data-ticket-delay-reason="<?php echo htmlspecialchars($ticket['delay_reason'] ?? ''); ?>"
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
                                <th>Booking Ref</th>
                                <th>User</th>
                                <th>Owner</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($closedTickets as $ticket): ?>
                                <tr>
                                    <td><?php echo $ticket['id']; ?></td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ticket['booking_reference'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="ms-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($ticket['user_name']); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($ticket['user_email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="bi bi-person-check"></i>
                                            <?php echo htmlspecialchars($ticket['owner_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">
                                            <i class="bi bi-tag-fill"></i>
                                            <?php echo htmlspecialchars($ticket['type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td>
                                        <span
                                            class="badge ticket-priority-badge <?php echo getPriorityClass($ticket['priority']); ?>">
                                            <i class="bi bi-flag-fill"></i>
                                            <?php echo $ticket['priority']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="badge <?php 
                                                echo match($ticket['status']) {
                                                    'CLOSED', 'APPROVED', 'PROCESSED' => 'bg-success',
                                                    'IN_PROGRESS', 'UNDER_REVIEW' => 'bg-info',
                                                    'PENDING_APPROVAL' => 'bg-orange',
                                                    'REJECTED' => 'bg-danger',
                                                    'SUBMITTED', 'OPEN' => 'bg-warning text-dark',
                                                    default => 'bg-secondary'
                                                };
                                            ?>">
                                            <i
                                                class="bi <?php 
                                                    echo match($ticket['status']) {
                                                        'CLOSED', 'APPROVED', 'PROCESSED' => 'bi-check-circle-fill',
                                                        'IN_PROGRESS', 'UNDER_REVIEW' => 'bi-arrow-repeat',
                                                        'PENDING_APPROVAL' => 'bi-hourglass-split',
                                                        'REJECTED' => 'bi-x-circle-fill',
                                                        default => 'bi-clock-fill'
                                                    };
                                                ?>"></i>
                                            <?php echo $ticket['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#viewTicketModal"
                                                data-ticket-id="<?php echo $ticket['id']; ?>"
                                                data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning"
                                                onclick="editTicket(<?php echo $ticket['id']; ?>, '<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>')">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <?php if (
                                                $isAdmin
                                            ): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal" data-bs-target="#ticketModal"
                                                    data-ticket-id="<?php echo $ticket['id']; ?>"
                                                    data-ticket-type="<?php echo htmlspecialchars($ticket['type_key'] ?? ''); ?>"
                                                    data-ticket-priority="<?php echo $ticket['priority']; ?>"
                                                    data-ticket-status="<?php echo $ticket['status']; ?>"
                                                    data-ticket-owner-id="<?php echo htmlspecialchars($ticket['owner_id'] ?? ''); ?>"
                                                    data-ticket-expected-timeline="<?php echo htmlspecialchars($ticket['expected_timeline'] ?? ''); ?>"
                                                    data-ticket-delay-reason="<?php echo htmlspecialchars($ticket['delay_reason'] ?? ''); ?>"
                                                    data-ticket-estimated-time="<?php echo htmlspecialchars($ticket['estimated_time'] ?? ''); ?>">
                                                    <i class="bi bi-gear"></i> Manage
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal"
                                                data-bs-target="#timelineModal"
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
        </div> <!-- End tab-content -->
        </div> <!-- End ticket-card-glass -->
    </div> <!-- End main-content -->
</div> <!-- End dashboard-container -->
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

<!-- Edit Ticket Modal -->
<div class="modal fade" id="editTicketModal" tabindex="-1" aria-labelledby="editTicketModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTicketModalLabel">Edit Ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTicketForm">
                <input type="hidden" id="editTicketId" name="id">
                <input type="hidden" id="editTicketType" name="type">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Booking / Reference Number <span class="text-danger">*</span></label>
                            <input type="text" id="editBookingReference" name="booking_reference" class="form-control booking-ref-input" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Priority</label>
                            <select id="editPriority" name="priority" class="form-select" required>
                                <option value="LOW">LOW</option>
                                <option value="MEDIUM">MEDIUM</option>
                                <option value="HIGH">HIGH</option>
                                <option value="URGENT">URGENT</option>
                            </select>
                        </div>
                        <div class="col-12" id="editEstimateFields" style="display:none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" id="editCustomerName" name="customer_name" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Total Amount</label>
                                    <input type="number" step="0.01" id="editTotalAmount" name="total_amount" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description / Details</label>
                            <textarea id="editDescription" name="description" class="form-control" rows="6"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Changes</button>
                </div>
            </form>
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
                                <option value="SUBMITTED">Submitted</option>
                                <option value="PENDING_APPROVAL">Pending Approval</option>
                                <option value="APPROVED">Approved</option>
                                <option value="PAID">Paid</option>
                                <option value="OVERDUE">Overdue</option>
                                <option value="CLOSED">Closed</option>
                                <option value="REJECTED">Rejected</option>
                                <option value="UNDER_REVIEW">Under Review</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Assign Owner</label>
                            <select class="form-select" id="ticketOwnerId" name="owner_id">
                                <option value="">Select Owner...</option>
                                <?php foreach ($activeUsers as $au): ?>
                                    <option value="<?php echo $au['id']; ?>"><?php echo htmlspecialchars($au['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Expected Timeline (Accounting SLA)</label>
                            <input type="datetime-local" class="form-control" id="ticketExpectedTimeline" name="expected_timeline">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Delay Reason (if applicable)</label>
                            <textarea class="form-control" id="ticketDelayReason" name="delay_reason" placeholder="Explain why payment/action is delayed..."></textarea>
                        </div>
                        <div class="mb-3 d-none" id="refundStatusContainer">
                            <label class="form-label">Refund Approval Step</label>
                            <select class="form-select" id="refundStatus" name="refund_status">
                                <option value="SUBMITTED">Submitted (Consultant)</option>
                                <option value="UNDER_REVIEW">Under Review (Rupesh)</option>
                                <option value="PENDING_APPROVAL">Pending Approval (Rupesh)</option>
                                <option value="APPROVED">Approved (Sehar / Muhammad)</option>
                                <option value="REJECTED">Rejected (Sehar / Muhammad)</option>
                                <option value="PROCESSED">Processed (Final)</option>
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