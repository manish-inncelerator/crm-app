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

try {
    $user = $auth0->getUser();
    if (!$user) { header('Location: login.php'); exit; }
    $dbUser = $database->get('users', '*', ['auth0_id' => $user['sub']]);
    if (!$dbUser) { header('Location: login.php'); exit; }
    
    $ticketId = $_GET['id'] ?? null;
    if (!$ticketId) { header('Location: tickets.php'); exit; }

    $ticket = $database->get('tickets_unified', '*', ['id' => $ticketId]);
    if (!$ticket) { die("Ticket not found."); }
    
    $isAdmin = (bool)($dbUser['is_admin'] ?? false);
    if (!$isAdmin && $ticket['user_id'] != $dbUser['id']) { die("Unauthorized access."); }

    $meta = json_decode($ticket['metadata'], true) ?? [];
    
    // Handle form submission for comment / status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ticket') {
        $newStatus = $_POST['status'] ?? $ticket['status'];
        $newPriority = $_POST['priority'] ?? $ticket['priority'];
        $commentText = trim($_POST['comment'] ?? '');
        
        $updates = [];
        $changes = [];
        if ($isAdmin) {
            if ($newStatus !== $ticket['status']) {
                $updates['status'] = $newStatus;
                $changes[] = "Status changed to " . $newStatus;
            }
            if ($newPriority !== $ticket['priority']) {
                $updates['priority'] = $newPriority;
                $changes[] = "Priority changed to " . $newPriority;
            }
        }
        
        if (!empty($updates)) {
            $database->update('tickets_unified', $updates, ['id' => $ticketId]);
            $ticket = array_merge($ticket, $updates); // refresh local object
        }
        
        if (!empty($commentText) || !empty($changes)) {
            $fullComment = implode(" | ", $changes);
            if (!empty($fullComment) && !empty($commentText)) $fullComment .= "<br><br>";
            $fullComment .= nl2br(htmlspecialchars($commentText));
            
            $database->insert('ticket_comments', [
                'ticket_id' => $ticketId,
                'ticket_type' => $ticket['type'], // Used in old structure, keeping it
                'user_id' => $dbUser['id'],
                'comment' => $fullComment,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Reload page to show new comment
            header("Location: ticket-details.php?id=$ticketId");
            exit;
        }
    }

    // Fetch comments
    // In migrated systems, old comments might point to old_id.
    // We check ticket_migration_map to get old_id if it exists.
    $mapping = $database->get('ticket_migration_map', ['old_id', 'old_type'], ['new_id' => $ticketId]);
    if ($mapping) {
        $comments = $database->select('ticket_comments', '*', [
            'OR' => [
                'AND' => ['ticket_id' => $ticketId, 'ticket_type' => $ticket['type']],
                'AND' => ['ticket_id' => $mapping['old_id'], 'ticket_type' => $mapping['old_type']]
            ],
            'ORDER' => ['created_at' => 'ASC']
        ]);
    } else {
        $comments = $database->select('ticket_comments', '*', [
            'ticket_id' => $ticketId,
            'ticket_type' => $ticket['type'],
            'ORDER' => ['created_at' => 'ASC']
        ]);
    }
    
    $commentUserIds = array_unique(array_column($comments, 'user_id'));
    $commentUserIds[] = $ticket['user_id'];
    $commentUserIds[] = $ticket['owner_id'];
    $usersData = $database->select('users', ['id', 'name', 'is_admin'], ['id' => array_filter(array_unique($commentUserIds))]);
    $users = [];
    foreach ($usersData as $u) $users[$u['id']] = $u;

} catch (\Exception $e) {
    die("Error: " . $e->getMessage());
}

function getPriorityBadge($priority) {
    switch ($priority) {
        case 'URGENT': return 'bg-danger';
        case 'HIGH': return 'bg-warning text-dark';
        case 'MEDIUM': return 'bg-info text-dark';
        case 'LOW': return 'bg-success';
        default: return 'bg-secondary';
    }
}

html_start('Ticket #' . $ticketId);
?>
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
    .ticket-view { max-width: 1200px; margin: 2rem auto; }
    .thread-box { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden; }
    .dark-mode .thread-box { background: #1f2937; border-color: #374151; }
    
    .comment-bubble { padding: 1.5rem; border-bottom: 1px solid #e5e7eb; }
    .dark-mode .comment-bubble { border-color: #374151; }
    .comment-bubble:last-child { border-bottom: none; }
    .comment-bubble.is-admin { background: rgba(37, 99, 235, 0.03); }
    .dark-mode .comment-bubble.is-admin { background: rgba(37, 99, 235, 0.1); }
    
    .avatar { width: 40px; height: 40px; border-radius: 50%; background: #2563eb; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; }
    .meta-sidebar { background: #f9fafb; border-radius: 12px; padding: 1.5rem; border: 1px solid #e5e7eb; }
    .dark-mode .meta-sidebar { background: #111827; border-color: #374151; }
    
    .meta-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; margin-bottom: 0.25rem; font-weight: 600; }
    .meta-value { font-size: 0.95rem; font-weight: 500; margin-bottom: 1rem; color: #111827; }
    .dark-mode .meta-value { color: #f9fafb; }
    
    .reply-box { padding: 1.5rem; background: #f8fafc; border-top: 1px solid #e5e7eb; }
    .dark-mode .reply-box { background: #111827; border-color: #374151; }
</style>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div class="ticket-view px-4">
            <div class="mb-4">
                <a href="tickets.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Back to Tickets</a>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="fw-bold mb-0">Ticket #<?= str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($ticket['subtype']) ?></h2>
                <div>
                    <span class="badge <?= getPriorityBadge($ticket['priority']) ?> fs-6 me-2"><?= $ticket['priority'] ?></span>
                    <span class="badge bg-secondary fs-6"><?= $ticket['status'] ?></span>
                </div>
            </div>

            <div class="row g-4">
                <!-- Main Thread -->
                <div class="col-lg-8">
                    <div class="thread-box mb-4">
                        <!-- Initial Request Content -->
                        <div class="comment-bubble">
                            <div class="d-flex mb-3">
                                <div class="avatar me-3">
                                    <?= substr($users[$ticket['user_id']]['name'] ?? 'U', 0, 1) ?>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($users[$ticket['user_id']]['name'] ?? 'Unknown User') ?> <span class="badge bg-light text-dark fw-normal ms-2">Requester</span></div>
                                    <div class="text-muted small"><?= date('F j, Y g:i A', strtotime($ticket['created_at'])) ?></div>
                                </div>
                            </div>
                            
                            <div class="ticket-description">
                                <?php if (!empty($ticket['description'])): ?>
                                    <div class="mb-4"><?= nl2br(htmlspecialchars($ticket['description'])) ?></div>
                                <?php endif; ?>
                                
                                <!-- Render specific metadata fields -->
                                <?php if (!empty($meta)): ?>
                                    <div class="p-3 bg-light rounded border">
                                        <h6 class="mb-3 text-muted"><i class="bi bi-journal-text"></i> Provided Details</h6>
                                        <div class="row g-3">
                                            <?php foreach ($meta as $k => $v): ?>
                                                <?php if ($v !== null && $v !== '' && !str_ends_with($k, '_path')): ?>
                                                    <div class="col-sm-6">
                                                        <div class="text-muted small text-capitalize"><?= str_replace('_', ' ', $k) ?></div>
                                                        <div class="fw-medium"><?= htmlspecialchars($v) ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Attachments -->
                                <?php
                                $attachments = [];
                                foreach ($meta as $k => $v) {
                                    if (str_ends_with($k, '_path') && !empty($v)) {
                                        $folder = str_replace('_path', 's', $k);
                                        $attachments[] = ['label' => ucwords(str_replace('_path', '', str_replace('_', ' ', $k))), 'url' => "uploads/$folder/$v"];
                                    }
                                }
                                ?>
                                <?php if (!empty($attachments)): ?>
                                    <div class="mt-4">
                                        <h6 class="text-muted mb-2"><i class="bi bi-paperclip"></i> Attachments</h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($attachments as $att): ?>
                                                <a href="<?= $att['url'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-file-earmark-text"></i> <?= $att['label'] ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Comments Thread -->
                        <?php foreach ($comments as $c): ?>
                            <?php $isCommentAdmin = $users[$c['user_id']]['is_admin'] ?? false; ?>
                            <div class="comment-bubble <?= $isCommentAdmin ? 'is-admin' : '' ?>">
                                <div class="d-flex mb-3">
                                    <div class="avatar me-3 <?= $isCommentAdmin ? 'bg-dark' : 'bg-primary' ?>">
                                        <?= substr($users[$c['user_id']]['name'] ?? 'U', 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold">
                                            <?= htmlspecialchars($users[$c['user_id']]['name'] ?? 'System') ?>
                                            <?php if ($isCommentAdmin): ?>
                                                <span class="badge bg-purple ms-2">Admin</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small"><?= date('F j, Y g:i A', strtotime($c['created_at'])) ?></div>
                                    </div>
                                </div>
                                <div class="comment-content">
                                    <?= $c['comment'] // HTML is allowed because we formatted it before inserting ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Reply Box -->
                        <div class="reply-box">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_ticket">
                                
                                <?php if ($isAdmin): ?>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Update Status</label>
                                            <select name="status" class="form-select">
                                                <?php
                                                $statuses = ['SUBMITTED', 'OPEN', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED', 'PROCESSED', 'IN_PROGRESS', 'RESOLVED', 'REJECTED', 'CLOSED'];
                                                foreach ($statuses as $s) {
                                                    $sel = ($ticket['status'] === $s) ? 'selected' : '';
                                                    echo "<option value=\"$s\" $sel>$s</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Update Priority</label>
                                            <select name="priority" class="form-select">
                                                <?php
                                                $priorities = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
                                                foreach ($priorities as $p) {
                                                    $sel = ($ticket['priority'] === $p) ? 'selected' : '';
                                                    echo "<option value=\"$p\" $sel>$p</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Leave a reply</label>
                                    <textarea name="comment" class="form-control" rows="4" placeholder="Type your message here..."></textarea>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Submit Reply</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="col-lg-4">
                    <div class="meta-sidebar">
                        <div class="meta-label">Ticket Type</div>
                        <div class="meta-value"><?= ucfirst($ticket['type']) ?></div>
                        
                        <div class="meta-label">Booking Reference</div>
                        <div class="meta-value">
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($ticket['booking_reference'] ?: 'N/A') ?></span>
                        </div>
                        
                        <div class="meta-label">Created By</div>
                        <div class="meta-value"><?= htmlspecialchars($users[$ticket['user_id']]['name'] ?? 'Unknown') ?></div>
                        
                        <div class="meta-label">Assigned Owner</div>
                        <div class="meta-value">
                            <?php if ($isAdmin): ?>
                                <a href="#" class="text-decoration-none fw-bold"><i class="bi bi-person-fill-gear"></i> <?= htmlspecialchars($users[$ticket['owner_id']]['name'] ?? 'Unassigned') ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($users[$ticket['owner_id']]['name'] ?? 'Unassigned') ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="meta-label">Expected Timeline</div>
                        <div class="meta-value">
                            <?= $ticket['expected_timeline'] ? date('M d, Y h:i A', strtotime($ticket['expected_timeline'])) : '<span class="text-muted small">Not set</span>' ?>
                        </div>
                        
                        <div class="meta-label">Delay Reason</div>
                        <div class="meta-value">
                            <?= $ticket['delay_reason'] ? htmlspecialchars($ticket['delay_reason']) : '<span class="text-muted small">None</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
