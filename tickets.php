<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

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

$sessionStore = new SessionStore($config);
$auth0 = new Auth0($config);

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'just now';
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $str) {
        $val = floor($diff / $secs);
        if ($val >= 1) return $val . ' ' . $str . ($val > 1 ? 's' : '') . ' ago';
    }
    return date('Y-m-d', $time);
}

function getPriorityClass($priority) {
    switch ($priority) {
        case 'URGENT': return 'bg-danger';
        case 'HIGH': return 'bg-warning text-dark';
        case 'MEDIUM': return 'bg-info text-dark';
        case 'LOW': return 'bg-success';
        default: return 'bg-secondary';
    }
}

function getStatusBadge($status) {
    switch ($status) {
        case 'CLOSED':
        case 'APPROVED':
        case 'PROCESSED':
        case 'RESOLVED':
            return '<span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> ' . $status . '</span>';
        case 'IN_PROGRESS':
        case 'UNDER_REVIEW':
            return '<span class="badge bg-info"><i class="bi bi-arrow-repeat"></i> ' . $status . '</span>';
        case 'PENDING_APPROVAL':
            return '<span class="badge bg-orange text-white" style="background-color: #fd7e14;"><i class="bi bi-hourglass-split"></i> ' . $status . '</span>';
        case 'REJECTED':
            return '<span class="badge bg-danger"><i class="bi bi-x-circle-fill"></i> ' . $status . '</span>';
        case 'SUBMITTED':
        case 'OPEN':
        default:
            return '<span class="badge bg-warning text-dark"><i class="bi bi-clock-fill"></i> ' . $status . '</span>';
    }
}

try {
    $user = $auth0->getUser();
    if (!$user) { header('Location: login.php'); exit; }
    
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) { header('Location: login.php'); exit; }

    $isAdmin = (bool)($dbUser['is_admin'] ?? false);
    $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);

    $tableExists = $database->query("SHOW TABLES LIKE 'tickets_unified'")->fetchAll();
    if (empty($tableExists)) die("Please run the migration script first.");

    $conditions = [];
    if (!$isAdmin) {
        $conditions['user_id'] = $dbUser['id'];
    }

    $openStatuses = ['SUBMITTED', 'OPEN', 'IN_PROGRESS', 'UNDER_REVIEW', 'PENDING_APPROVAL'];
    $closedStatuses = ['RESOLVED', 'CLOSED', 'APPROVED', 'PROCESSED', 'REJECTED'];

    // Fetch Open Tickets
    $openConditions = array_merge($conditions, ['status' => $openStatuses]);
    $openTickets = $database->select('tickets_unified', '*', array_merge($openConditions, ['ORDER' => ['id' => 'DESC']]));

    // Fetch Recent Closed Tickets (Limit 500 to save memory)
    $closedConditions = array_merge($conditions, ['status' => $closedStatuses]);
    $closedTickets = $database->select('tickets_unified', '*', array_merge($closedConditions, ['ORDER' => ['id' => 'DESC'], 'LIMIT' => 500]));

    // Combine for Kanban and User Extraction
    $allLoadedTickets = array_merge($openTickets, $closedTickets);

    $userIds = array_unique(array_merge(array_column($allLoadedTickets, 'user_id'), array_column($allLoadedTickets, 'owner_id')));
    $users = [];
    if (!empty($userIds)) {
        $userRows = $database->select('users', ['id', 'name', 'email'], ['id' => array_filter($userIds)]);
        foreach ($userRows as $u) $users[$u['id']] = $u;
    }
    $allUsersList = $isAdmin ? $database->select('users', ['id', 'name'], ['ORDER' => ['name' => 'ASC']]) : [];

} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Tickets Dashboard');
?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .ticket-row { cursor: pointer; transition: background-color 0.2s; }
    .ticket-row:hover { background-color: rgba(37, 99, 235, 0.05); }
    .ticket-meta { font-size: 0.8rem; color: #6c757d; }
    .ticket-title { font-weight: 600; color: #111827; }
    .dark-mode .ticket-title { color: #f9fafb; }
    .nav-tabs .nav-link { color: #6c757d; font-weight: 500; border: none; border-bottom: 2px solid transparent; padding: 1rem 1.5rem; }
    .nav-tabs .nav-link.active { color: #2563eb; border-bottom-color: #2563eb; background: transparent; }
    .filters-bar { background: #fff; padding: 1rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem; }
    .dark-mode .filters-bar { background: #1f2937; }
    
    /* Kanban Styles */
    .kanban-board { display: flex; gap: 1.5rem; overflow-x: auto; padding-bottom: 1rem; }
    .kanban-column { flex: 0 0 350px; background: #f3f4f6; border-radius: 12px; padding: 1rem; display: flex; flex-direction: column; }
    .dark-mode .kanban-column { background: #1f2937; }
    .kanban-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e5e7eb; }
    .dark-mode .kanban-header { border-color: #374151; }
    .kanban-title { font-weight: 700; font-size: 1rem; color: #111827; }
    .dark-mode .kanban-title { color: #f9fafb; }
    .kanban-count { background: #e5e7eb; color: #4b5563; padding: 0.1rem 0.5rem; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
    .dark-mode .kanban-count { background: #374151; color: #d1d5db; }
    
    .kanban-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; cursor: grab; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .kanban-card:active { cursor: grabbing; }
    .kanban-card:hover { transform: translateY(-3px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    .dark-mode .kanban-card { background: #111827; border-color: #374151; }
    .kanban-card-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 0.5rem; color: #111827; }
    .dark-mode .kanban-card-title { color: #f9fafb; }
    .kanban-card-meta { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #6b7280; }
    
    .view-toggle { display: flex; gap: 0.5rem; }
    .view-btn { padding: 0.5rem 1rem; border: 1px solid #e5e7eb; background: #fff; color: #4b5563; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.2s; }
    .dark-mode .view-btn { background: #1f2937; border-color: #374151; color: #d1d5db; }
    .view-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
    
    .kanban-cards { min-height: 200px; flex-grow: 1; padding-bottom: 2rem; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid px-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1 text-gray-800">Support Tickets</h1>
                    <?php if ($isMasterAdmin): ?>
                        <span class="badge bg-purple text-white"><i class="bi bi-star-fill"></i> Master Admin View</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <div class="view-toggle">
                        <button class="view-btn active" onclick="switchView('list')" id="btn-list"><i class="bi bi-list-task"></i> List</button>
                        <button class="view-btn" onclick="switchView('kanban')" id="btn-kanban"><i class="bi bi-kanban"></i> Board</button>
                    </div>
                    <a href="create-ticket.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Ticket</a>
                </div>
            </div>

            <!-- List View Container -->
            <div id="listView">
                <!-- Filters -->
                <div class="filters-bar row g-3 align-items-center">
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Priority Filter</label>
                        <select id="priorityFilter" class="form-select form-select-sm">
                            <option value="">All Priorities</option>
                            <option value="URGENT">Urgent</option>
                            <option value="HIGH">High</option>
                            <option value="MEDIUM">Medium</option>
                            <option value="LOW">Low</option>
                        </select>
                    </div>
                    <?php if ($isAdmin): ?>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">User Filter</label>
                        <select id="userFilter" class="form-select form-select-sm">
                            <option value="">All Users</option>
                            <?php foreach ($allUsersList as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['name']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="ticketTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="open-tab" data-bs-toggle="tab" data-bs-target="#open" type="button" role="tab">
                            <i class="bi bi-inbox me-2"></i>Open Tickets
                            <span class="badge bg-primary ms-2 rounded-pill"><?= count($openTickets) ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="closed-tab" data-bs-toggle="tab" data-bs-target="#closed" type="button" role="tab">
                            <i class="bi bi-archive me-2"></i>Closed Tickets
                            <span class="badge bg-secondary ms-2 rounded-pill"><?= count($closedTickets) ?></span>
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="ticketTabsContent">
                    <div class="tab-pane fade show active" id="open" role="tabpanel">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="openTicketsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Requested By</th>
                                                <th>Subject / Type</th>
                                                <th>Ref #</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($openTickets as $t): ?>
                                                <?php $creator = $users[$t['user_id']] ?? ['name'=>'Unknown','email'=>'']; ?>
                                                <tr class="ticket-row" onclick="window.location='ticket-details.php?id=<?= $t['id'] ?>'">
                                                    <td><span class="text-muted fw-bold">#<?= str_pad($t['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="ms-2">
                                                                <div class="fw-bold"><?= htmlspecialchars($creator['name']) ?></div>
                                                                <div class="ticket-meta"><?= htmlspecialchars($creator['email']) ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="ticket-title"><?= htmlspecialchars($t['subtype']) ?></div>
                                                        <div class="ticket-meta"><?= ucfirst($t['type']) ?> Request</div>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['booking_reference'] ?: 'N/A') ?></span></td>
                                                    <td><span class="badge <?= getPriorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                                                    <td><?= getStatusBadge($t['status']) ?></td>
                                                    <td>
                                                        <div><?= date('M d, Y', strtotime($t['created_at'])) ?></div>
                                                        <div class="ticket-meta"><?= timeAgo($t['created_at']) ?></div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="closed" role="tabpanel">
                        <!-- Closed tickets table similar to above... omitted for brevity, keeping simple -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="closedTicketsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Requested By</th>
                                                <th>Subject / Type</th>
                                                <th>Ref #</th>
                                                <th>Priority</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($closedTickets as $t): ?>
                                                <?php $creator = $users[$t['user_id']] ?? ['name'=>'Unknown','email'=>'']; ?>
                                                <tr class="ticket-row" onclick="window.location='ticket-details.php?id=<?= $t['id'] ?>'">
                                                    <td><span class="text-muted fw-bold">#<?= str_pad($t['id'], 5, '0', STR_PAD_LEFT) ?></span></td>
                                                    <td><div class="fw-bold"><?= htmlspecialchars($creator['name']) ?></div></td>
                                                    <td><div class="ticket-title"><?= htmlspecialchars($t['subtype']) ?></div></td>
                                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['booking_reference'] ?: 'N/A') ?></span></td>
                                                    <td><span class="badge <?= getPriorityClass($t['priority']) ?>"><?= $t['priority'] ?></span></td>
                                                    <td><?= getStatusBadge($t['status']) ?></td>
                                                    <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kanban View Container -->
            <div id="kanbanView" style="display: none;">
                <?php 
                    $columns = [
                        'To Do' => ['SUBMITTED', 'OPEN'],
                        'In Progress' => ['IN_PROGRESS', 'UNDER_REVIEW', 'PENDING_APPROVAL'],
                        'Done' => ['APPROVED', 'PROCESSED', 'RESOLVED', 'CLOSED', 'REJECTED']
                    ];
                ?>
                <div class="kanban-board">
                    <?php foreach ($columns as $colName => $statuses): ?>
                        <?php 
                            $colTickets = array_filter($allLoadedTickets, function($t) use ($statuses) {
                                return in_array($t['status'], $statuses);
                            });
                        ?>
                        <div class="kanban-column">
                            <div class="kanban-header">
                                <div class="kanban-title"><?= $colName ?></div>
                                <div class="kanban-count"><?= count($colTickets) ?></div>
                            </div>
                            
                            <div class="kanban-cards" data-column-status="<?= $statuses[0] ?>">
                                <?php foreach ($colTickets as $t): ?>
                                    <div class="kanban-card" data-ticket-id="<?= $t['id'] ?>" data-ticket-type="<?= $t['type'] ?>">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small fw-bold">#<?= $t['id'] ?></span>
                                            <span class="badge <?= getPriorityClass($t['priority']) ?>" style="font-size: 0.65rem;"><?= $t['priority'] ?></span>
                                        </div>
                                        <div class="kanban-card-title">
                                            <a href="ticket-details.php?id=<?= $t['id'] ?>" class="text-decoration-none" style="color: inherit;"><?= htmlspecialchars($t['subtype']) ?></a>
                                        </div>
                                        <div class="mb-2"><?= getStatusBadge($t['status']) ?></div>
                                        <div class="kanban-card-meta mt-3 pt-2 border-top">
                                            <div title="<?= htmlspecialchars($users[$t['user_id']]['name'] ?? '') ?>">
                                                <i class="bi bi-person-circle"></i> <?= substr(htmlspecialchars($users[$t['user_id']]['name'] ?? 'U'), 0, 10) ?>...
                                            </div>
                                            <div>
                                                <i class="bi bi-clock"></i> <?= timeAgo($t['created_at']) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end([
    'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
    'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js',
    'https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js'
]); ?>
<script>
    $(document).ready(function() {
        const tableOptions = {
            dom: '<"row p-3"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row p-3"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 10,
            ordering: true,
            order: [[0, 'desc']],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search tickets..."
            }
        };

        const openTable = $('#openTicketsTable').DataTable(tableOptions);
        const closedTable = $('#closedTicketsTable').DataTable(tableOptions);

        $('#priorityFilter').on('change', function () {
            const val = $.fn.dataTable.util.escapeRegex($(this).val());
            openTable.column(4).search(val ? val : '', true, false).draw();
        });
        
        <?php if ($isAdmin): ?>
        $('#userFilter').on('change', function () {
            const val = $.fn.dataTable.util.escapeRegex($(this).val());
            openTable.column(1).search(val ? val : '', true, false).draw();
        });
        <?php endif; ?>
    });

    function switchView(view) {
        document.getElementById('btn-list').classList.remove('active');
        document.getElementById('btn-kanban').classList.remove('active');
        document.getElementById('btn-' + view).classList.add('active');
        
        if (view === 'list') {
            document.getElementById('listView').style.display = 'block';
            document.getElementById('kanbanView').style.display = 'none';
        } else {
            document.getElementById('listView').style.display = 'none';
            document.getElementById('kanbanView').style.display = 'block';
        }
    }

    // Initialize Sortable for Kanban Drag and Drop
    $(document).ready(function() {
        document.querySelectorAll('.kanban-cards').forEach(function(column) {
            new Sortable(column, {
                group: 'kanban',
                animation: 150,
                ghostClass: 'bg-light',
                onEnd: function(evt) {
                    const itemEl = evt.item; // The dragged card
                    const toList = evt.to;   // The target column
                    const fromList = evt.from;
                    
                    if (toList !== fromList) {
                        const ticketId = itemEl.getAttribute('data-ticket-id');
                        const ticketType = itemEl.getAttribute('data-ticket-type');
                        const newStatus = toList.getAttribute('data-column-status');
                        
                        // Call API to update ticket status
                        fetch('api/update-ticket.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                ticket_id: ticketId,
                                ticket_type: ticketType,
                                status: newStatus
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (!data.success) {
                                alert('Failed to update status: ' + (data.error || 'Unknown error'));
                                // Optional: Revert the card to the original list if failed
                            } else {
                                // Update count badges
                                const fromCountEl = fromList.previousElementSibling.querySelector('.kanban-count');
                                const toCountEl = toList.previousElementSibling.querySelector('.kanban-count');
                                fromCountEl.textContent = parseInt(fromCountEl.textContent) - 1;
                                toCountEl.textContent = parseInt(toCountEl.textContent) + 1;
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Network error while updating status.');
                        });
                    }
                }
            });
        });
    });
</script>

<!-- Footer included above scripts -->