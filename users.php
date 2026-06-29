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

// Fetch all users
$users = $database->select('users', '*', ['ORDER' => ['name' => 'ASC']]);

html_start('Manage Users');
?>

<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .user-card {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 15px;
        padding: 1.5rem;
        transition: all 0.2s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    }
    .user-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
    }
    .dark-mode .user-card {
        background: #1f2937;
        border-color: #374151;
    }
    .user-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin-bottom: 1rem;
        object-fit: cover;
        border: 3px solid #2563eb;
    }
    .status-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        margin-bottom: 0.5rem;
        font-weight: 600;
    }
    .status-active { background: #dcfce7; color: #166534; }
    .status-ex { background: #fee2e2; color: #991b1b; }
    
    .role-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        margin: 0.25rem;
        display: inline-block;
    }
    .role-master { background: #4f46e5; color: white; }
    .role-admin { background: #2563eb; color: white; }
    .role-finance { background: #059669; color: white; }
    .role-user { background: #6b7280; color: white; }
    
    .actions-container {
        width: 100%;
        margin-top: auto;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .dark-mode .actions-container { border-color: #374151; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1 fw-bold">Team Management</h2>
                    <p class="text-muted">Manage roles, financial access, and active status.</p>
                </div>
            </div>

            <div class="row g-4">
                <?php foreach ($users as $u): ?>
                    <?php 
                        $isEx = (bool)($u['is_ex_employee'] ?? false);
                        $uIsAdmin = (bool)($u['is_admin'] ?? false);
                        $uIsMasterAdmin = (bool)($u['is_master_admin'] ?? false);
                        $uCanViewFinancials = (bool)($u['can_view_financials'] ?? false);
                        $uReceiveEmails = (bool)($u['receive_emails'] ?? false);
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="user-card">
                            <img src="<?php echo htmlspecialchars($u['picture'] ?: 'https://via.placeholder.com/80'); ?>" alt="" class="user-avatar">
                            <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($u['name']); ?></h5>
                            <p class="text-muted small mb-3"><?php echo htmlspecialchars($u['email']); ?></p>
                            
                            <div class="mb-3">
                                <?php if ($isEx): ?>
                                    <div class="status-badge status-ex">Ex-Employee</div>
                                <?php else: ?>
                                    <div class="status-badge status-active">Active</div>
                                <?php endif; ?>
                                
                                <div>
                                    <?php if ($uIsMasterAdmin): ?>
                                        <span class="role-badge role-master"><i class="bi bi-star-fill"></i> Master Admin</span>
                                    <?php elseif ($uIsAdmin): ?>
                                        <span class="role-badge role-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="role-badge role-user">User</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($uCanViewFinancials): ?>
                                        <span class="role-badge role-finance"><i class="bi bi-currency-dollar"></i> Finance Access</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="actions-container">
                                <!-- Status Toggle -->
                                <?php if ($isEx): ?>
                                    <button class="btn btn-outline-success btn-sm w-100" onclick="toggleStatus(<?php echo $u['id']; ?>, 0)">
                                        <i class="bi bi-person-check me-1"></i> Mark Active
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-danger btn-sm w-100" onclick="toggleStatus(<?php echo $u['id']; ?>, 1)">
                                        <i class="bi bi-person-x me-1"></i> Mark Ex-Employee
                                    </button>
                                <?php endif; ?>

                                <?php if ($isMasterAdmin): ?>
                                    <!-- Super Admin Controls -->
                                    <?php if ($u['id'] != $dbUser['id']): ?>
                                        <button class="btn btn-light border btn-sm w-100 mb-1" onclick="updateRole(<?= $u['id'] ?>, 'admin', <?= $uIsAdmin ? '0' : '1' ?>)">
                                            <i class="bi bi-shield"></i> <?= $uIsAdmin ? 'Demote to User' : 'Make Admin' ?>
                                        </button>
                                        <button class="btn btn-light border btn-sm w-100 mb-1" onclick="updateRole(<?= $u['id'] ?>, 'master', <?= $uIsMasterAdmin ? '0' : '1' ?>)">
                                            <i class="bi bi-star"></i> <?= $uIsMasterAdmin ? 'Remove Super Admin' : 'Make Super Admin' ?>
                                        </button>
                                        <button class="btn btn-light border btn-sm w-100 mb-1" onclick="updateRole(<?= $u['id'] ?>, 'finance', <?= $uCanViewFinancials ? '0' : '1' ?>)">
                                            <i class="bi bi-currency-dollar"></i> <?= $uCanViewFinancials ? 'Revoke Finance Access' : 'Grant Finance Access' ?>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-light border btn-sm w-100" onclick="toggleEmailPref(<?= $u['id'] ?>, <?= $uReceiveEmails ? '0' : '1' ?>)">
                                        <i class="bi <?= $uReceiveEmails ? 'bi-bell-slash' : 'bi-bell' ?>"></i> <?= $uReceiveEmails ? 'Disable Email Alerts' : 'Enable Email Alerts' ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Replacement Modal -->
<div class="modal fade" id="replacementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">Mark as Ex-Employee</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p>You are marking this employee as <strong>Ex-Employee</strong>. Please select a replacement consultant to whom all their ongoing tickets/leads will be reassigned.</p>
                <input type="hidden" id="targetUserId">
                <div class="mb-3">
                    <label class="form-label">Replacement Employee</label>
                    <select class="form-select" id="replacementUserId" required>
                        <option value="">Select Replacement...</option>
                        <?php foreach ($users as $u_opt): ?>
                            <?php if (!($u_opt['is_ex_employee'] ?? false)): ?>
                                <option value="<?php echo $u_opt['id']; ?>"><?php echo htmlspecialchars($u_opt['name']); ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmExEmployee()">Confirm & Reassign</button>
            </div>
        </div>
    </div>
</div>

<script>
let currentActionUserId = null;
let replacementModal = null;

document.addEventListener('DOMContentLoaded', function() {
    replacementModal = new bootstrap.Modal(document.getElementById('replacementModal'));
});

function toggleStatus(userId, status) {
    if (status === 1) {
        currentActionUserId = userId;
        document.getElementById('targetUserId').value = userId;
        document.getElementById('replacementUserId').value = '';
        replacementModal.show();
    } else {
        if (!confirm('Are you sure you want to mark this user as active?')) return;
        submitToggle(userId, 0);
    }
}

async function confirmExEmployee() {
    const userId = document.getElementById('targetUserId').value;
    const replacementId = document.getElementById('replacementUserId').value;
    
    if (!replacementId) { alert('Please select a replacement employee.'); return; }
    if (userId == replacementId) { alert('You cannot select the same employee as their own replacement.'); return; }
    
    replacementModal.hide();
    submitToggle(userId, 1, replacementId);
}

async function submitToggle(userId, status, replacementId = null) {
    try {
        const response = await fetch('api/toggle-ex-employee.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: userId, 
                is_ex_employee: status,
                replacement_user_id: replacementId
            })
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

async function updateRole(userId, roleType, actionValue) {
    if (!confirm('Are you sure you want to update this user\'s permissions?')) return;
    try {
        const response = await fetch('api/update-role.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: userId, 
                role_type: roleType,
                action_value: actionValue
            })
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to update role');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred');
    }
}

async function toggleEmailPref(userId, receiveValue) {
    if (!confirm('Are you sure you want to change email alert preferences for this user?')) return;
    try {
        const response = await fetch('api/toggle-receive-emails.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                user_id: userId, 
                receive_emails: receiveValue
            })
        });
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.error || 'Failed to update email preferences');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred');
    }
}
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>
