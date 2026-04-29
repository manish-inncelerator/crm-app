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
if (!$dbUser || $dbUser['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Fetch all users
$users = $database->select('users', '*', ['ORDER' => ['name' => 'ASC']]);

html_start('Manage Users');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .user-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .dark-mode .user-card {
        background: rgba(30, 41, 59, 0.7);
    }
    .user-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin-bottom: 1rem;
        object-fit: cover;
        border: 3px solid var(--primary-color);
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        margin-bottom: 1rem;
    }
    .status-active { background: #dcfce7; color: #166534; }
    .status-ex { background: #fee2e2; color: #991b1b; }
    
    .btn-toggle-status {
        width: 100%;
        margin-top: auto;
    }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Employee Management</h2>
                    <p class="text-muted">Manage system users and active/ex-employee status.</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($users as $u): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="user-card">
                            <img src="<?php echo htmlspecialchars($u['picture'] ?: 'https://via.placeholder.com/80'); ?>" alt="" class="user-avatar">
                            <h5 class="mb-1"><?php echo htmlspecialchars($u['name']); ?></h5>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($u['email']); ?></p>
                            <p class="small mb-3">Role: <span class="badge bg-secondary"><?php echo htmlspecialchars($u['role'] ?: 'User'); ?></span></p>
                            
                            <?php if ($u['is_ex_employee']): ?>
                                <span class="status-badge status-ex">Ex-Employee</span>
                                <button class="btn btn-outline-success btn-sm btn-toggle-status" onclick="toggleStatus(<?php echo $u['id']; ?>, 0)">
                                    <i class="bi bi-person-check me-1"></i> Mark as Active
                                </button>
                            <?php else: ?>
                                <span class="status-badge status-active">Active</span>
                                <button class="btn btn-outline-danger btn-sm btn-toggle-status" onclick="toggleStatus(<?php echo $u['id']; ?>, 1)">
                                    <i class="bi bi-person-x me-1"></i> Mark as Ex-Employee
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
async function toggleStatus(userId, status) {
    if (!confirm('Are you sure you want to change this user\'s status?')) return;
    
    try {
        const response = await fetch('api/toggle-ex-employee.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, is_ex_employee: status })
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to update status');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred');
    }
}
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
