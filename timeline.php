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
    'redirectUri' => 'https://crm.fyyz.link/callback.php',
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
if (!$ticketId || !$ticketType) {
    die('Ticket ID and type required.');
}

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

html_start('Ticket Timeline');
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
<style>
    .timeline-widget.list-group-item {
        background: var(--card-bg) !important;
        border-radius: 22px;
        box-shadow: var(--card-shadow);
        padding: 32px 36px 24px 36px;
        min-width: 220px;
        max-width: 700px;
        margin: 0 auto 32px auto;
        display: flex;
        flex-direction: column;
        position: relative;
        border: var(--card-border) !important;
        transition: box-shadow 0.18s, transform 0.18s, background 0.3s, color 0.3s;
        z-index: 1;
    }

    .timeline-widget.list-group-item:hover {
        box-shadow: 0 12px 48px rgba(78, 31, 0, 0.18), 0 2px 12px rgba(0, 0, 0, 0.08);
        transform: translateY(-4px) scale(1.03);
        background: #f3e7db;
        color: #4e1f00;
    }

    .timeline-item-connector-wrapper {
        position: relative;
        max-width: 700px;
        margin: 0 auto;
    }

    .timeline-badge-row {
        align-items: flex-start;
        position: relative;
    }

    .timeline-badge {
        z-index: 2;
        position: relative;
        display: inline-flex;
        align-items: center;
    }

    .timeline-connector-vertical {
        position: absolute;
        left: 50%;
        top: 50%;
        width: 3px;
        height: calc(100% + 32px);
        background: #d1cfcf;
        z-index: 1;
        transform: translateX(-50%);
    }

    .timeline-item-connector-wrapper:last-child .timeline-connector-vertical {
        display: none;
    }

    @media (max-width: 900px) {
        .timeline-widget.list-group-item {
            padding: 18px 8px 12px 8px;
        }

        .timeline-connector-vertical {
            left: 50%;
        }
    }

    .timeline-widget .timeline-badge {
        font-size: 0.95rem;
        padding: 0.4rem 1rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 2rem;
    }

    .timeline-widget .timeline-badge.bg-purple {
        background-color: #9c27b0 !important;
        color: white !important;
    }

    .timeline-widget .timeline-badge.bg-primary {
        background-color: #0d6efd !important;
    }

    .timeline-widget .timeline-badge.bg-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }

    .timeline-widget .timeline-badge.bg-info {
        background-color: #0dcaf0 !important;
        color: #212529 !important;
    }

    .timeline-widget .timeline-badge.bg-success {
        background-color: #198754 !important;
    }

    .timeline-widget .timeline-badge.bg-danger {
        background-color: #dc3545 !important;
    }

    .timeline-widget .timeline-badge i {
        font-size: 1.1em;
    }

    .timeline-widget .timeline-meta {
        color: #6c757d;
        font-size: 0.92rem;
        margin-bottom: 0.5rem;
    }

    .timeline-widget .timeline-comment {
        font-size: 1.05rem;
        color: #23272b;
        background: #f8f9fa;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-top: 0.5rem;
        border: 1px solid #e0e0e0;
    }

    .list-group-flush .list-group-item {
        border-width: 0 0 0 0 !important;
        background: transparent !important;
    }

    .timeline-connector-vertical {
        display: none !important;
    }

    .timeline-meta {
        display: none !important;
    }

    .timeline-card {
        width: 100%;
        max-width: 500px;
        margin: 24px auto;
        box-sizing: border-box;
    }

    /* Timeline step circle */
    .timeline-step-circle {
        width: 56px;
        height: 56px;
        background: var(--sidebar-active, #4e1f00);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.6rem;
        font-weight: bold;
        box-shadow: 0 2px 12px rgba(78, 31, 0, 0.18);
        z-index: 10;
        border: 4px solid var(--sidebar-active-border, #3a1800);
    }

    .step-circle-text {
        font-family: inherit;
        font-size: 1.3em;
        font-weight: 600;
    }
</style>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-header">
            <h2 class="main-title mb-0" style="margin-bottom:0;"><i class="bi bi-clock-history"></i> Ticket Timeline</h2>
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
        <div class="list-group list-group-flush mb-4">
            <?php $count = count($comments);
            foreach ($comments as $i => $comment):
                list($badgeClass, $icon) = getBadgeAndIcon($comment['comment']);
                $stepNumber = $i + 1;
            ?>
                <div class="timeline-card position-relative">
                    <div class="timeline-step-circle position-absolute top-0 start-0 translate-middle-y" style="left:-48px; top:50%;">
                        <span class="step-circle-text">#<?= $stepNumber ?></span>
                    </div>
                    <div class="timeline-item-connector-wrapper position-relative">
                        <div class="list-group-item timeline-widget">
                            <div class="d-flex align-items-center mb-2 timeline-badge-row" style="position:relative;">
                                <span class="timeline-badge <?= $badgeClass ?> me-2 position-relative">
                                    <i class="bi <?= $icon ?>"></i> <?= htmlspecialchars($comment['user_name']) ?>
                                    <?php if ($comment['is_admin']): ?>
                                        <span class="badge bg-dark ms-1">Admin</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="timeline-date mb-2" style="font-size: 0.95em; color: #888;">
                                <?= date('F j, Y', strtotime($comment['created_at'])) ?>
                            </div>
                            <div class="timeline-comment"><?= nl2br(htmlspecialchars($comment['comment'])) ?></div>
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