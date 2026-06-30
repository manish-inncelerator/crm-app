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
if (!$dbUser) {
    header('Location: login.php');
    exit;
}

// Finance dashboard access check
$canViewFinancials = (bool)($dbUser['can_view_financials'] ?? false);
$isAdmin = (bool)($dbUser['is_admin'] ?? false);

if (!$canViewFinancials && !$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

$clientId = $_GET['id'] ?? null;
if (!$clientId) {
    header('Location: clients.php');
    exit;
}

$client = $database->get('users', '*', ['id' => $clientId]);
if (!$client) {
    die("Client not found.");
}

// Fetch financial stats
$tickets = $database->select('tickets_unified', '*', [
    'user_id' => $clientId,
    'ORDER' => ['created_at' => 'DESC']
]);

$invoices = $database->select('invoices', '*', [
    'user_id' => $clientId,
    'ORDER' => ['created_at' => 'DESC']
]);

$totalProcessed = $database->sum('tickets_unified', 'amount', [
    'user_id' => $clientId,
    'status' => 'PROCESSED'
]);
$totalProcessed = $totalProcessed ?: 0.00;

// Handle manual balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_balance') {
    $adjustment = floatval($_POST['amount'] ?? 0);
    $type = $_POST['type'] ?? 'add';
    
    $currentBalance = floatval($client['account_balance']);
    $newBalance = ($type === 'add') ? $currentBalance + $adjustment : $currentBalance - $adjustment;
    
    $database->update('users', ['account_balance' => $newBalance], ['id' => $clientId]);
    
    // Refresh
    header("Location: client-details.php?id=$clientId&msg=balance_updated");
    exit;
}

$balance = floatval($client['account_balance'] ?? 0);
if ($balance > 0) $balClass = 'text-success bg-success bg-opacity-10';
elseif ($balance < 0) $balClass = 'text-danger bg-danger bg-opacity-10';
else $balClass = 'text-secondary bg-light';

html_start('Client Profile - ' . htmlspecialchars($client['name']));
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .ledger-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .dark-mode .ledger-card { background: #1f2937; border-color: #374151; }
    
    .nav-tabs .nav-link {
        color: #64748b;
        font-weight: 600;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 1rem 1.5rem;
    }
    .nav-tabs .nav-link:hover { border-color: #cbd5e1; }
    .nav-tabs .nav-link.active {
        color: #2563eb;
        background: transparent;
        border-bottom: 2px solid #2563eb;
    }
    .dark-mode .nav-tabs .nav-link.active { color: #60a5fa; border-color: #60a5fa; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="mb-4">
                <a href="clients.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Back to Clients</a>
            </div>

            <!-- Client Header -->
            <div class="ledger-card p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-6 d-flex align-items-center mb-3 mb-md-0">
                        <img src="<?php echo htmlspecialchars($client['picture'] ?: 'https://via.placeholder.com/80'); ?>" alt="" class="rounded-circle border me-4" style="width: 80px; height: 80px; object-fit: cover;">
                        <div>
                            <h3 class="mb-1 fw-bold"><?php echo htmlspecialchars($client['name']); ?></h3>
                            <p class="text-muted mb-0"><i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($client['email']); ?></p>
                            <p class="text-muted mb-0 small"><i class="bi bi-clock me-1"></i> Member since <?php echo date('M d, Y', strtotime($client['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-md-end gap-4 text-center">
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Lifetime Processed</div>
                                <h4 class="fw-bold mb-0">$<?php echo number_format($totalProcessed, 2); ?></h4>
                            </div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold mb-1">Current Balance</div>
                                <h4 class="fw-bold mb-0 px-3 py-1 rounded <?php echo $balClass; ?>">
                                    $<?php echo number_format($balance, 2); ?>
                                </h4>
                            </div>
                        </div>
                        <?php if ($canViewFinancials): ?>
                            <div class="text-md-end mt-3">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#adjustBalanceModal">
                                    <i class="bi bi-plus-slash-minus"></i> Adjust Balance
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="ledger-card mb-4">
                <ul class="nav nav-tabs px-3 border-bottom" id="clientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tickets-tab" data-bs-toggle="tab" data-bs-target="#tickets" type="button" role="tab">Ticket History</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab">Invoices & Receipts</button>
                    </li>
                </ul>
                
                <div class="tab-content p-4" id="clientTabsContent">
                    <!-- Tickets Tab -->
                    <div class="tab-pane fade show active" id="tickets" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $t): ?>
                                        <tr>
                                            <td class="fw-bold">#<?php echo str_pad($t['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($t['subtype']); ?></td>
                                            <td class="fw-bold">$<?php echo number_format($t['amount'] ?? 0, 2); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo $t['status']; ?></span></td>
                                            <td>
                                                <a href="ticket-details.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-light border">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($tickets)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No tickets found for this client.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Invoices Tab -->
                    <div class="tab-pane fade" id="invoices" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Client Invoices</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>INV #</th>
                                        <th>Ticket Ref</th>
                                        <th>Date Issued</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr>
                                            <td class="fw-bold">INV-<?php echo str_pad($inv['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                            <td><a href="ticket-details.php?id=<?php echo $inv['ticket_id']; ?>">#<?php echo str_pad($inv['ticket_id'], 5, '0', STR_PAD_LEFT); ?></a></td>
                                            <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                                            <td class="fw-bold">$<?php echo number_format($inv['amount'], 2); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo $inv['status']; ?></span></td>
                                            <td>
                                                <a href="generate-invoice.php?id=<?php echo $inv['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i> Print</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($invoices)): ?>
                                        <tr><td colspan="6" class="text-center py-4 text-muted">No invoices generated for this client.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Adjust Balance Modal -->
<div class="modal fade" id="adjustBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="action" value="adjust_balance">
            <div class="modal-header border-0 bg-light">
                <h5 class="modal-title fw-bold">Adjust Client Balance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label text-muted">Adjustment Type</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="addFunds" value="add" checked>
                            <label class="form-check-label fw-bold text-success" for="addFunds">Add Credit (+)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="type" id="deductFunds" value="deduct">
                            <label class="form-check-label fw-bold text-danger" for="deductFunds">Deduct Funds (-)</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted">Amount ($)</label>
                    <input type="number" step="0.01" name="amount" class="form-control" required placeholder="0.00">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Balance</button>
            </div>
        </form>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
