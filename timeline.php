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

// Get ticket id and type from GET
$ticketId = $_GET['id'] ?? null;
$ticketType = $_GET['type'] ?? null;

$isGlobal = (!$ticketId || !$ticketType);

if ($isGlobal) {
    // Fetch all comments across all tickets, newest first
    $comments = $database->select('ticket_comments', [
        '[>]users' => ['user_id' => 'id']
    ], [
        'ticket_comments.comment',
        'ticket_comments.created_at',
        'ticket_comments.ticket_id',
        'ticket_comments.ticket_type',
        'users.name(user_name)',
        'users.is_admin'
    ], [
        'ORDER' => ['ticket_comments.created_at' => 'DESC'],
        'LIMIT' => 50
    ]);
    $ticket = null;
    $pageTitle = 'Recent Activity';
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
        <?php if ($ticket): ?>
            <div class="text-muted mb-4" style="text-align:center;">
                Ticket #<?php echo htmlspecialchars($ticketId); ?> -
                <?php echo htmlspecialchars(strip_tags($ticket['subject'] ?? $ticket['description'] ?? 'No Subject')); ?>
            </div>
        <?php endif; ?>
        <?php if (empty($comments)): ?>
            <div class="alert alert-info" style="max-width:600px;margin:0 auto;">No timeline events found for this ticket.</div>
        <?php endif; ?>
        <div class="timeline-container">
            <?php $count = count($comments);
            foreach ($comments as $i => $comment):
                list($badgeClass, $icon) = getBadgeAndIcon($comment['comment']);
                $stepNumber = $i + 1;
            ?>
                <div class="timeline-card">
                    <div class="timeline-step-circle">
                        <span class="step-circle-text">#<?= $stepNumber ?></span>
                    </div>
                    <div class="timeline-item-connector-wrapper">
                        <div class="timeline-widget">
                            <div class="d-flex flex-wrap align-items-center mb-2 timeline-badge-row gap-2">
                                <span class="timeline-badge <?= $badgeClass ?>">
                                    <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($comment['user_name']) ?>
                                    <?php if ($comment['is_admin']): ?>
                                        <span class="badge bg-dark ms-1" style="font-size:0.75rem; border-radius:12px;">Admin</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="timeline-date">
                                <?= date('F j, Y g:i A', strtotime($comment['created_at'])) ?>
                            </div>
                            <div class="timeline-comment"><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>
                            <?php if (isset($isGlobal) && $isGlobal): ?>
                                <div class="mt-3 pt-2 border-top text-muted d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size:0.8rem;">
                                    <span class="text-truncate" style="max-width: 100%;">
                                        <i class="bi bi-ticket-detailed"></i> Ticket #<?= htmlspecialchars($comment['ticket_id']) ?> (<?= htmlspecialchars(ucfirst($comment['ticket_type'])) ?>)
                                    </span>
                                    <a href="timeline.php?id=<?= htmlspecialchars($comment['ticket_id']) ?>&type=<?= htmlspecialchars($comment['ticket_type']) ?>" class="btn btn-sm btn-outline-primary py-0 px-2 flex-shrink-0" style="font-size:0.75rem; border-radius:12px;">
                                        View
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-center mt-4">
            <a href="tickets.php" class="btn btn-outline-secondary btn-lg px-4 py-2 shadow-sm rounded-pill">
                <i class="bi bi-arrow-left"></i> Back to Tickets
            </a>
        </div>
    </div>
</div>
<?php html_end(); ?>