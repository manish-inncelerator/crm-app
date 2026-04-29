<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;

session_start();

$httpClient = new Client(['verify' => false]);
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'https://crm.fayyaz.travel/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

$auth0 = new Auth0($config);

try {
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }

    $isAdmin = isset($dbUser['is_admin']) && $dbUser['is_admin'] == 1;
    $conditions = $isAdmin ? [] : ['user_id' => $dbUser['id']];

    $ticketTypes = ['estimate', 'supplier', 'general'];
    $allTickets = [];

    foreach ($ticketTypes as $type) {
        $tickets = $database->select($type . '_tickets', '*', array_merge($conditions, ['ORDER' => ['updated_at' => 'DESC']]));
        foreach ($tickets as $t) {
            $t['type_label'] = ucfirst($type);
            $t['type_key'] = $type;
            
            // Get user info
            $ticketUser = $database->get('users', ['name'], ['id' => $t['user_id']]);
            $t['user_name'] = $ticketUser ? $ticketUser['name'] : 'Unknown';
            
            $allTickets[] = $t;
        }
    }

    // Sort by updated_at
    usort($allTickets, function($a, $b) {
        return strtotime($b['updated_at'] ?? $b['created_at']) - strtotime($a['updated_at'] ?? $a['created_at']);
    });

} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Tickets - Fayyaz Travels CRM', ['assets/css/tickets.css']);
?>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="tickets-container">
            <div class="tickets-header">
                <div class="tickets-title">
                    <h1>Tickets</h1>
                    <p>Manage and track all support requests</p>
                </div>
                <div class="header-actions">
                    <a href="create-ticket.php" class="btn-create" style="padding: 0.6rem 1.2rem;">
                        <i class="bi bi-plus-lg"></i> Create New Ticket
                    </a>
                </div>
            </div>

            <!-- Filter Bar -->
            <div class="tickets-filter-bar">
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="">All Statuses</option>
                        <option value="OPEN">Open</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="RESOLVED">Resolved</option>
                        <option value="CLOSED">Closed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priority</label>
                    <select id="priorityFilter">
                        <option value="">All Priorities</option>
                        <option value="URGENT">Urgent</option>
                        <option value="HIGH">High</option>
                        <option value="MEDIUM">Medium</option>
                        <option value="LOW">Low</option>
                    </select>
                </div>
                <?php if ($isAdmin): ?>
                <div class="filter-group">
                    <label>Agent</label>
                    <select id="agentFilter">
                        <option value="">All Agents</option>
                        <?php 
                            $agents = $database->select('users', ['id', 'name']);
                            foreach ($agents as $agent) {
                                echo '<option value="' . htmlspecialchars($agent['name']) . '">' . htmlspecialchars($agent['name']) . '</option>';
                            }
                        ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="filter-group" style="margin-left: auto;">
                    <input type="text" id="ticketSearch" placeholder="Search subject, ID...">
                </div>
            </div>

            <div class="tickets-table-area">
                <div class="tab-nav">
                    <div class="tab-item active" data-filter="active">Active Tickets</div>
                    <div class="tab-item" data-filter="CLOSED">Closed Archives</div>
                </div>
                
                <table id="ticketsTable" class="dense-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>Subject</th>
                            <th>Requester</th>
                            <th style="width: 100px;">Priority</th>
                            <th style="width: 120px;">Status</th>
                            <th style="width: 150px;">Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allTickets as $ticket): ?>
                        <tr data-status="<?php echo $ticket['status']; ?>" onclick="window.location.href='messages.php?ticket_id=<?php echo $ticket['id']; ?>&type=<?php echo $ticket['type_key']; ?>'" style="cursor: pointer;">
                            <td class="ticket-id">#<?php echo $ticket['id']; ?></td>
                            <td>
                                <div class="ticket-subject"><?php echo htmlspecialchars($ticket['subject'] ?? ($ticket['type_label'] . ' Request')); ?></div>
                                <div class="ticket-meta"><?php echo $ticket['type_label']; ?> &bull; <?php echo htmlspecialchars($ticket['ticket_subtype'] ?? 'N/A'); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['user_name']); ?></td>
                            <td>
                                <?php 
                                    $prio = strtoupper($ticket['priority'] ?? 'MEDIUM');
                                    $prioClass = strtolower($prio);
                                    echo '<span class="priority-indicator ' . $prioClass . '"></span>' . $prio;
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $statusClass = strtolower($ticket['status'] === 'IN_PROGRESS' ? 'progress' : $ticket['status']);
                                    echo '<span class="status-badge ' . $statusClass . '">' . str_replace('_', ' ', $ticket['status']) . '</span>';
                                ?>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-secondary);">
                                <?php echo date('M d, Y H:i', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
$(document).ready(function() {
    const table = $('#ticketsTable').DataTable({
        dom: 'rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        pageLength: 15,
        ordering: true,
        order: [[5, 'desc']],
        language: {
            paginate: {
                previous: '<i class="bi bi-chevron-left"></i>',
                next: '<i class="bi bi-chevron-right"></i>'
            }
        }
    });

    // Custom filtering
    $('#ticketSearch').on('keyup', function() {
        table.search(this.value).draw();
    });

    $('#statusFilter').on('change', function() {
        table.column(4).search(this.value).draw();
    });

    $('#priorityFilter').on('change', function() {
        table.column(3).search(this.value).draw();
    });

    <?php if ($isAdmin): ?>
    $('#agentFilter').on('change', function() {
        table.column(2).search(this.value).draw();
    });
    <?php endif; ?>

    // Tab filtering
    $('.tab-item').on('click', function() {
        $('.tab-item').removeClass('active');
        $(this).addClass('active');
        
        const filter = $(this).data('filter');
        if (filter === 'CLOSED') {
            table.column(4).search('CLOSED').draw();
        } else {
            table.column(4).search('^(?!CLOSED).*$', true, false).draw();
        }
    });

    // Initial filter for active tickets
    table.column(4).search('^(?!CLOSED).*$', true, false).draw();
});
</script>

<?php html_end(); ?>