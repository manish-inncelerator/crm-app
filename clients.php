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

// Fetch clients (non-admins or everyone if they have tickets)
$clients = $database->select('users', '*', [
    'is_admin' => 0,
    'ORDER' => ['name' => 'ASC']
]);

// Enhance clients with financial stats
foreach ($clients as &$client) {
    $client['total_tickets'] = $database->count('tickets_unified', ['user_id' => $client['id']]);
    
    // Sum of amounts processed for this client
    $totalProcessed = $database->sum('tickets_unified', 'amount', [
        'user_id' => $client['id'],
        'status' => 'PROCESSED'
    ]);
    $client['total_processed'] = $totalProcessed ?: 0.00;
}
unset($client);

html_start('Client Accounts & Ledgers');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .client-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 1.5rem;
        transition: all 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .client-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        border-color: #cbd5e1;
    }
    .dark-mode .client-card {
        background: #1f2937;
        border-color: #374151;
    }
    .client-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e2e8f0;
    }
    .dark-mode .client-avatar { border-color: #4b5563; }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
    }
    .dark-mode .stat-row { border-color: #374151; }
    .stat-row:last-child { border-bottom: none; }
    
    .balance-badge {
        font-size: 1.1rem;
        font-weight: 700;
        padding: 0.25rem 0.75rem;
        border-radius: 8px;
    }
    .balance-positive { background: #dcfce7; color: #166534; }
    .balance-negative { background: #fee2e2; color: #991b1b; }
    .balance-zero { background: #f1f5f9; color: #475569; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1 fw-bold"><i class="bi bi-people-fill text-primary me-2"></i> Client Accounts</h2>
                    <p class="text-muted">Manage client ledgers, view historical value, and access financial profiles.</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($clients as $c): ?>
                    <?php 
                        $balance = floatval($c['account_balance'] ?? 0);
                        if ($balance > 0) $balClass = 'balance-positive';
                        elseif ($balance < 0) $balClass = 'balance-negative';
                        else $balClass = 'balance-zero';
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="client-card">
                            <div class="d-flex align-items-center mb-4">
                                <img src="<?php echo htmlspecialchars($c['picture'] ?: 'https://via.placeholder.com/60'); ?>" alt="" class="client-avatar me-3">
                                <div>
                                    <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($c['name']); ?></h5>
                                    <span class="text-muted small"><?php echo htmlspecialchars($c['email']); ?></span>
                                </div>
                            </div>
                            
                            <div class="flex-grow-1">
                                <div class="stat-row">
                                    <span class="text-muted">Account Balance</span>
                                    <span class="balance-badge <?php echo $balClass; ?>">
                                        $<?php echo number_format($balance, 2); ?>
                                    </span>
                                </div>
                                <div class="stat-row">
                                    <span class="text-muted">Total Processed Value</span>
                                    <span class="fw-bold text-dark">$<?php echo number_format($c['total_processed'], 2); ?></span>
                                </div>
                                <div class="stat-row">
                                    <span class="text-muted">Total Tickets</span>
                                    <span class="fw-bold text-dark"><?php echo $c['total_tickets']; ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-top">
                                <a href="client-details.php?id=<?php echo $c['id']; ?>" class="btn btn-outline-primary w-100 fw-bold">
                                    <i class="bi bi-journal-text me-1"></i> View Ledger
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($clients)): ?>
                    <div class="col-12 text-center py-5">
                        <div class="text-muted mb-3"><i class="bi bi-person-x" style="font-size: 3rem;"></i></div>
                        <h4 class="fw-bold">No Clients Found</h4>
                        <p class="text-muted">No non-admin users have been added to the system yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
