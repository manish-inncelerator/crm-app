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

$user = $auth0->getUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
if (!$dbUser || !($dbUser['is_admin'] ?? false)) {
    header('Location: dashboard.php');
    exit;
}

$isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);
$canViewFinancials = (bool)($dbUser['can_view_financials'] ?? false);

if (!$canViewFinancials && !$isMasterAdmin) {
    die("Unauthorized. Finance access required.");
}

// Handle Bulk Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $ticketIds = $_POST['ticket_ids'] ?? [];
    $newStatus = $_POST['new_status'] ?? '';
    
    if (!empty($ticketIds) && !empty($newStatus)) {
        $count = 0;
        foreach ($ticketIds as $tId) {
            $t = $database->get('tickets_unified', '*', ['id' => $tId]);
            if (!$t) continue;
            
            // Enforce multi-tier rules
            if ($t['type'] === 'refund' && !$isMasterAdmin && in_array($newStatus, ['APPROVED', 'REJECTED']) && floatval($t['amount']) >= 500) {
                continue; // Skip unauthorized
            }
            
            if ($t['status'] !== $newStatus) {
                $database->update('tickets_unified', ['status' => $newStatus], ['id' => $tId]);
                $database->insert('ticket_comments', [
                    'ticket_id' => $tId,
                    'ticket_type' => $t['type'],
                    'user_id' => $dbUser['id'],
                    'comment' => "Status changed to $newStatus (via Bulk Update)",
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $count++;
            }
        }
        $msg = "Successfully updated $count tickets.";
    } else {
        $msg = "Please select tickets and a status.";
    }
}

// Fetch pending tickets
$tickets = $database->select('tickets_unified', [
    '[>]users' => ['user_id' => 'id']
], [
    'tickets_unified.id',
    'tickets_unified.type',
    'tickets_unified.subtype',
    'tickets_unified.status',
    'tickets_unified.amount',
    'tickets_unified.currency',
    'tickets_unified.created_at',
    'users.name(client_name)'
], [
    'tickets_unified.status' => ['OPEN', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED'],
    'ORDER' => ['tickets_unified.created_at' => 'DESC']
]);

html_start('Bulk Operations');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .bulk-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .dark-mode .bulk-card { background: #1f2937; border-color: #374151; }
    
    .table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .dark-mode .table thead th { background: #111827; border-color: #374151; color: #94a3b8; }
    
    .table-hover tbody tr:hover { background-color: #f1f5f9; cursor: pointer; }
    .dark-mode .table-hover tbody tr:hover { background-color: #374151; }
    
    .form-check-input { width: 1.25em; height: 1.25em; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="mb-4 d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1 fw-bold"><i class="bi bi-ui-checks-grid text-primary me-2"></i> Bulk Operations</h2>
                    <p class="text-muted">Select multiple tickets to update their status simultaneously.</p>
                </div>
                <a href="finance-dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Finance Dashboard</a>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-info shadow-sm mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <div class="bulk-card p-0">
                <form method="POST" id="bulkForm">
                    <input type="hidden" name="action" value="bulk_update">
                    
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center bg-light rounded-top">
                        <div class="d-flex align-items-center gap-3">
                            <span class="fw-bold" id="selectedCount">0 selected</span>
                            <select name="new_status" class="form-select form-select-sm w-auto fw-bold" required>
                                <option value="">Select New Status...</option>
                                <option value="APPROVED">Approve Selected</option>
                                <option value="PROCESSED">Mark as Processed</option>
                                <option value="REJECTED">Reject Selected</option>
                                <option value="UNDER_REVIEW">Move to Review</option>
                            </select>
                            <button type="button" onclick="confirmBulkAction()" class="btn btn-primary btn-sm fw-bold">Update Selected</button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3" style="width: 50px;">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                    </th>
                                    <th class="py-3">Ticket ID</th>
                                    <th class="py-3">Client</th>
                                    <th class="py-3">Type</th>
                                    <th class="py-3">Current Status</th>
                                    <th class="py-3">Amount</th>
                                    <th class="py-3">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $t): ?>
                                    <tr onclick="toggleRow(this, event)">
                                        <td class="px-4">
                                            <input class="form-check-input ticket-checkbox" type="checkbox" name="ticket_ids[]" value="<?php echo $t['id']; ?>" onclick="event.stopPropagation()">
                                        </td>
                                        <td class="fw-bold">
                                            <a href="ticket-details.php?id=<?php echo $t['id']; ?>" class="text-decoration-none" onclick="event.stopPropagation()">#<?php echo str_pad($t['id'], 5, '0', STR_PAD_LEFT); ?></a>
                                        </td>
                                        <td><?php echo htmlspecialchars($t['client_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo htmlspecialchars($t['subtype']); ?></td>
                                        <td><span class="badge bg-secondary"><?php echo $t['status']; ?></span></td>
                                        <td class="fw-bold text-success">$<?php echo number_format($t['amount'], 2); ?></td>
                                        <td class="text-muted small"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tickets)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox-fill fs-1 d-block mb-3"></i>
                                            No pending tickets found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.ticket-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(cb => {
                    cb.checked = this.checked;
                    updateRowClass(cb);
                });
                updateCount();
            });
        }
        
        checkboxes.forEach(cb => {
            cb.addEventListener('change', function(e) {
                e.stopPropagation();
                updateRowClass(this);
                updateCount();
                
                if (!this.checked && selectAll) selectAll.checked = false;
                if (this.checked && document.querySelectorAll('.ticket-checkbox:checked').length === checkboxes.length) {
                    if (selectAll) selectAll.checked = true;
                }
            });
        });
    });
    
    function toggleRow(tr, event) {
        // Prevent toggle if clicking on a link
        if (event.target.tagName.toLowerCase() === 'a') return;
        
        const cb = tr.querySelector('.ticket-checkbox');
        if (cb) {
            cb.checked = !cb.checked;
            cb.dispatchEvent(new Event('change'));
        }
    }
    
    function updateRowClass(checkbox) {
        const tr = checkbox.closest('tr');
        if (checkbox.checked) {
            tr.classList.add('table-primary');
        } else {
            tr.classList.remove('table-primary');
        }
    }
    
    function updateCount() {
        const count = document.querySelectorAll('.ticket-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = count + (count === 1 ? ' selected' : ' selected');
    }
    
    function confirmBulkAction() {
        const count = document.querySelectorAll('.ticket-checkbox:checked').length;
        const status = document.querySelector('select[name="new_status"]').value;
        
        if (count === 0) {
            alert('Please select at least one ticket.');
            return;
        }
        if (!status) {
            alert('Please select a new status.');
            return;
        }
        
        if (confirm(`Are you sure you want to update ${count} tickets to ${status}?`)) {
            document.getElementById('bulkForm').submit();
        }
    }
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
