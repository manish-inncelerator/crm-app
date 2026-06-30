<?php
require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

session_start();

// Check admin authentication (assume Auth0 and $database are set up as in tickets.php)
$auth0 = new Auth0\SDK\Auth0([
    'domain' => 'fayyaztravels.us.auth0.com',
    'clientId' => 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    'clientSecret' => 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    'redirectUri' => 'https://crm.fayyaz.travel/callback.php',
    'cookieSecret' => 'your-secret-key-here'
]);

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
$is_admin = (bool)($dbUser['is_admin'] ?? false);

// Get ticket id and type from GET
$ticketId = $_GET['id'] ?? null;
$ticketType = $_GET['type'] ?? null;

if ($ticketId && !$ticketType) {
    $unified = $database->get('tickets_unified', ['type'], ['id' => $ticketId]);
    if ($unified) $ticketType = $unified['type'];
}

$isGlobal = (!$ticketId || !$ticketType);

// Fetch tickets for dropdown
$allTickets = [];
if ($is_admin) {
    $allTickets = $database->select('tickets_unified', [
        '[>]users' => ['owner_id' => 'id']
    ], [
        'tickets_unified.id', 'tickets_unified.subtype', 'tickets_unified.type', 'tickets_unified.created_at',
        'users.name(consultant_name)'
    ], ['ORDER' => ['tickets_unified.id' => 'DESC']]);
} else {
    $allTickets = $database->select('tickets_unified', [
        '[>]users' => ['owner_id' => 'id']
    ], [
        'tickets_unified.id', 'tickets_unified.subtype', 'tickets_unified.type', 'tickets_unified.created_at',
        'users.name(consultant_name)'
    ], ['tickets_unified.user_id' => $dbUser['id'], 'ORDER' => ['tickets_unified.id' => 'DESC']]);
}

if ($isGlobal) {
    // We will just show an empty state if they need to select a ticket
    $comments = [];
    $ticket = null;
    $pageTitle = 'Select a Ticket to View Timeline';
} else {
    // Fetch all comments for this ticket, oldest first
    $comments = $database->select('ticket_comments', [
        '[>]users' => ['user_id' => 'id']
    ], [
        'ticket_comments.comment',
        'ticket_comments.created_at',
        'users.name(user_name)',
        'users.is_admin'
    ], [
        'ticket_comments.ticket_id' => $ticketId,
        'ticket_comments.ticket_type' => $ticketType,
        'ORDER' => ['ticket_comments.created_at' => 'ASC']
    ]);

    // Get ticket details for the header
    $ticketTable = $ticketType . '_tickets';
    $ticket = $database->get($ticketTable, '*', ['id' => $ticketId]);
    $pageTitle = 'Ticket Timeline';
}

if (!$comments) $comments = [];

function getBadgeAndIcon($comment)
{
    if (strpos($comment, 'Priority changed') === 0) {
        return ['bg-warning', 'bi-flag'];
    } elseif (strpos($comment, 'Status changed') === 0) {
        return ['bg-info', 'bi-circle'];
    } elseif (strpos($comment, 'Estimated time changed') === 0) {
        return ['bg-purple', 'bi-clock'];
    } elseif (strpos($comment, 'Ticket created') === 0) {
        return ['bg-success', 'bi-plus-circle'];
    }
    return ['bg-primary', 'bi-chat'];
}

html_start($pageTitle);
?>
<script>
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebar-backdrop');
        sidebar.classList.toggle('open');
        if (sidebar.classList.contains('open')) {
            if (!backdrop) {
                var el = document.createElement('div');
                el.className = 'sidebar-backdrop';
                el.id = 'sidebar-backdrop';
                el.onclick = function() {
                    toggleSidebar();
                };
                document.body.appendChild(el);
            }
        } else {
            if (backdrop) backdrop.remove();
        }
    }

    function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('dashboard-dark-mode', document.body.classList.contains('dark-mode'));
        updateMobileModeIcon();
        if (typeof updateSidebarModeIcon === 'function') updateSidebarModeIcon();
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
        if (typeof updateSidebarModeIcon === 'function') updateSidebarModeIcon();
    }

    function sidebarLogout() {
        window.location.href = 'logout.php';
    }
</script>
<link rel="stylesheet" href="assets/css/timeline.css">
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        <div class="content-header">
            <h2 class="main-title mb-0" style="margin-bottom:0;"><i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($pageTitle); ?></h2>
        </div>

        <div class="card mb-4 border-0 shadow-sm mt-3">
            <div class="card-body">
                <form method="GET" action="timeline.php" class="row g-3 align-items-end">
                    <div class="col-md-9">
                        <label class="form-label text-muted small mb-1 fw-bold">Find Ticket Timeline</label>
                        <select name="id" class="form-select">
                            <option value="">-- Choose a Ticket --</option>
                            <?php foreach($allTickets as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] == $ticketId ? 'selected' : '' ?>>
                                    #<?= str_pad($t['id'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($t['subtype']) ?> (<?= ucfirst($t['type']) ?>) | Consultant: <?= htmlspecialchars($t['consultant_name'] ?? 'Unassigned') ?> | Date: <?= date('M d, Y', strtotime($t['created_at'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> View Timeline</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($ticket): ?>
            <div class="text-muted mb-4 text-center">
                <span class="badge bg-secondary rounded-pill fs-6 px-3 py-2">
                    Ticket #<?php echo htmlspecialchars($ticketId); ?> - <?php echo htmlspecialchars(strip_tags($ticket['subject'] ?? $ticket['description'] ?? 'No Subject')); ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if ($isGlobal): ?>
            <div class="alert alert-info text-center shadow-sm border-0"><i class="bi bi-info-circle"></i> Please select a ticket from the dropdown above to view its history timeline.</div>
        <?php elseif (empty($comments)): ?>
            <div class="alert alert-warning text-center shadow-sm border-0"><i class="bi bi-exclamation-triangle"></i> No timeline events found for this ticket.</div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="list-group list-group-flush">
                    <?php 
                    $count = count($comments);
                    foreach ($comments as $i => $comment):
                        list($badgeClass, $icon) = getBadgeAndIcon($comment['comment']);
                        $textColor = str_replace('bg-', 'text-', $badgeClass);
                        if ($textColor === 'text-purple') $textColor = 'text-primary';
                    ?>
                        <div class="list-group-item py-4 px-4 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 fw-bold <?= $textColor ?>"><i class="bi <?= $icon ?> me-2"></i> <?= htmlspecialchars($comment['user_name']) ?>
                                    <?php if ($comment['is_admin']): ?>
                                        <span class="badge bg-dark ms-2" style="font-size:0.7rem; border-radius:12px;">Admin</span>
                                    <?php endif; ?>
                                </h6>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i> <?= date('M d, Y g:i A', strtotime($comment['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 text-secondary ps-4 ms-2 mt-2" style="border-left: 2px solid #e9ecef;"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <div class="d-flex justify-content-center mt-4">
            <a href="tickets.php" class="btn btn-outline-secondary btn-lg px-4 py-2 shadow-sm rounded-pill">
                <i class="bi bi-arrow-left"></i> Back to Tickets
            </a>
        </div>
    </div>
</div>
<?php html_end(); ?>