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
    
    $isMasterAdmin = (bool)($dbUser['is_master_admin'] ?? false);
    
    // Handle form submission for comment / status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_ticket') {
        $newStatus = $_POST['status'] ?? $ticket['status'];
        $newPriority = $_POST['priority'] ?? $ticket['priority'];
        $commentText = trim($_POST['comment'] ?? '');
        
        $updates = [];
        $changes = [];
        if ($isAdmin) {
            if ($ticket['type'] === 'refund' && !$isMasterAdmin && in_array($newStatus, ['APPROVED', 'REJECTED'])) {
                die("Only Super Admins can Approve or Reject refunds.");
            }
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
            
            // EMAIL NOTIFICATIONS FOR REPLIES
            require_once 'sendMail.php';
            $ticketLink = "https://crm.fayyaz.travel/ticket-details.php?id=$ticketId";
            
            // 1. Email the ticket creator if it wasn't them who replied
            if ($ticket['user_id'] != $dbUser['id']) {
                $creator = $database->get('users', ['email', 'name'], ['id' => $ticket['user_id']]);
                if ($creator && !empty($creator['email'])) {
                    $body = "<p>Hello {$creator['name']},</p><p>A new reply/update has been added to your ticket (<strong>#$ticketId - {$ticket['subtype']}</strong>) by <strong>{$dbUser['name']}</strong>.</p><hr><p>$fullComment</p><hr><p>View your ticket to respond.</p>";
                    sendEmail($creator['email'], "Update on Ticket #$ticketId", 'default', $body, true, $ticketLink);
                }
            }

            // 2. Email admins who opted in (exclude the person making the reply)
            $emailAdmins = $database->select('users', ['email'], [
                'AND' => [
                    'receive_emails' => 1,
                    'id[!]' => $dbUser['id']
                ]
            ]);
            if (!empty($emailAdmins)) {
                $adminEmails = array_column($emailAdmins, 'email');
                $body = "<p>A new reply/update has been added to ticket (<strong>#$ticketId - {$ticket['subtype']}</strong>) by <strong>{$dbUser['name']}</strong>.</p><hr><p>$fullComment</p><hr><p>Please review it in the CRM.</p>";
                sendEmail($adminEmails, "New Reply on Ticket #$ticketId", 'default', $body, true, $ticketLink);
            }
            
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
            'ORDER' => ['created_at' => 'DESC']
        ]);
    } else {
        $comments = $database->select('ticket_comments', '*', [
            'ticket_id' => $ticketId,
            'ticket_type' => $ticket['type'],
            'ORDER' => ['created_at' => 'DESC']
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
    
    /* Timeline Container */
    .timeline-container { 
        position: relative; 
        padding: 2rem 1.5rem; 
        background: #f8fafc; 
        border-radius: 12px; 
        border: 1px solid #e2e8f0; 
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
        max-height: 600px;
        overflow-y: auto;
    }
    .dark-mode .timeline-container { background: #111827; border-color: #374151; }
    
    /* Custom Scrollbar for Timeline */
    .timeline-container::-webkit-scrollbar { width: 6px; }
    .timeline-container::-webkit-scrollbar-track { background: transparent; }
    .timeline-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .timeline-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .dark-mode .timeline-container::-webkit-scrollbar-thumb { background: #4b5563; }
    
    /* The vertical line */
    .timeline-container::before { content: ''; position: absolute; top: 2rem; bottom: 2rem; left: 3.8rem; width: 2px; background: #e2e8f0; z-index: 0; }
    .dark-mode .timeline-container::before { background: #374151; }

    .timeline-item { position: relative; display: flex; gap: 1.5rem; margin-bottom: 2rem; z-index: 1; }
    .timeline-item:last-child { margin-bottom: 0; }
    
    .timeline-avatar { flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.2rem; box-shadow: 0 0 0 6px #f8fafc; z-index: 2; margin-left: 0.25rem; }
    .dark-mode .timeline-avatar { box-shadow: 0 0 0 6px #111827; }
    .timeline-avatar.admin-avatar { background: linear-gradient(135deg, #6366f1, #4338ca); }
    
    .timeline-content-card { flex-grow: 1; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05); position: relative; }
    .dark-mode .timeline-content-card { background: #1f2937; border-color: #374151; }
    
    /* Pointer arrow to avatar */
    .timeline-content-card::before { content: ''; position: absolute; left: -6px; top: 18px; width: 12px; height: 12px; background: #fff; border-left: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; transform: rotate(45deg); }
    .dark-mode .timeline-content-card::before { background: #1f2937; border-color: #374151; }
    
    .timeline-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; }
    .dark-mode .timeline-header { border-color: #374151; }
    
    .timeline-body { font-size: 0.95rem; color: #334155; line-height: 1.6; }
    .dark-mode .timeline-body { color: #cbd5e1; }
    
    /* Admin Card Highlight */
    .is-admin-card { background: #f8fafc; border-color: #bfdbfe; }
    .dark-mode .is-admin-card { background: rgba(37, 99, 235, 0.05); border-color: rgba(37, 99, 235, 0.2); }
    .is-admin-card::before { background: #f8fafc; border-color: #bfdbfe; }
    .dark-mode .is-admin-card::before { background: #1f2937; border-color: rgba(37, 99, 235, 0.2); }
    
    /* Sidebar styling overrides */
    .sticky-col { position: sticky; top: 2rem; height: max-content; align-self: flex-start; z-index: 10; }
    .meta-sidebar { background: #fff; border-radius: 12px; padding: 1.75rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .dark-mode .meta-sidebar { background: #1f2937; border-color: #374151; }
    
    .meta-group { display: flex; flex-direction: column; padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; }
    .dark-mode .meta-group { border-color: #374151; }
    .meta-group:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    
    .meta-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.4rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .dark-mode .meta-label { color: #94a3b8; }
    
    .meta-value { font-size: 1rem; font-weight: 500; color: #0f172a; }
    .dark-mode .meta-value { color: #f8fafc; }
    
    /* Reply Box */
    .reply-box { padding: 1.5rem; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .dark-mode .reply-box { background: #1f2937; border-color: #374151; }
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
                <div>
                    <span class="text-uppercase small fw-bold text-primary mb-1 d-block">Ticket Details</span>
                    <h2 class="fw-bold mb-0 text-dark">#<?= str_pad($ticket['id'], 5, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($ticket['subtype']) ?></h2>
                </div>
                <div class="d-flex gap-2">
                    <span class="badge <?= getPriorityBadge($ticket['priority']) ?> px-3 py-2 rounded-pill shadow-sm fs-6 d-flex align-items-center"><i class="bi bi-flag-fill me-2"></i><?= $ticket['priority'] ?></span>
                    <span class="badge bg-secondary px-3 py-2 rounded-pill shadow-sm fs-6 d-flex align-items-center"><i class="bi bi-circle-fill me-2 small"></i><?= $ticket['status'] ?></span>
                </div>
            </div>

            <div class="row g-4">
                <!-- Main Thread -->
                <div class="col-lg-5 mb-4">
                    <div class="timeline-container">
                        <!-- Initial Request Content -->
                        <div class="timeline-item">
                            <div class="timeline-avatar">
                                <?= substr($users[$ticket['user_id']]['name'] ?? 'U', 0, 1) ?>
                            </div>
                            <div class="timeline-content-card">
                                <div class="timeline-header">
                                    <div>
                                        <span class="fw-bold fs-6"><?= htmlspecialchars($users[$ticket['user_id']]['name'] ?? 'Unknown User') ?></span>
                                        <span class="badge bg-light text-dark border fw-normal ms-2 align-middle">Requester</span>
                                    </div>
                                    <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= date('F j, Y g:i A', strtotime($ticket['created_at'])) ?></div>
                                </div>
                                
                                <div class="timeline-body">
                                    <?php if (!empty($ticket['description'])): ?>
                                        <div class="mb-3"><?= $ticket['description'] // Render HTML natively ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Render specific metadata fields -->
                                    <?php if (!empty($meta)): ?>
                                        <div class="p-3 bg-light rounded border mt-3">
                                            <h6 class="mb-3 text-secondary fw-bold fs-6"><i class="bi bi-journal-text me-2"></i>Provided Details</h6>
                                            <div class="row g-3">
                                                <?php foreach ($meta as $k => $v): ?>
                                                    <?php if ($v !== null && $v !== '' && !str_ends_with($k, '_path')): ?>
                                                        <div class="col-sm-6">
                                                            <div class="text-muted small text-capitalize fw-bold mb-1"><?= str_replace('_', ' ', $k) ?></div>
                                                            <div class="fw-medium text-dark"><?= htmlspecialchars($v) ?></div>
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
                                            <h6 class="text-secondary fw-bold mb-2"><i class="bi bi-paperclip me-2"></i>Attachments</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php foreach ($attachments as $att): ?>
                                                    <a href="<?= $att['url'] ?>" target="_blank" class="btn btn-sm btn-outline-primary shadow-sm rounded-pill px-3">
                                                        <i class="bi bi-file-earmark-text me-1"></i> <?= $att['label'] ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Comments Thread -->
                        <?php foreach ($comments as $c): ?>
                            <?php $isCommentAdmin = $users[$c['user_id']]['is_admin'] ?? false; ?>
                            <div class="timeline-item">
                                <div class="timeline-avatar <?= $isCommentAdmin ? 'admin-avatar' : '' ?>">
                                    <?= substr($users[$c['user_id']]['name'] ?? 'U', 0, 1) ?>
                                </div>
                                <div class="timeline-content-card <?= $isCommentAdmin ? 'is-admin-card' : '' ?>">
                                    <div class="timeline-header">
                                        <div>
                                            <span class="fw-bold fs-6"><?= htmlspecialchars($users[$c['user_id']]['name'] ?? 'System') ?></span>
                                            <?php if ($isCommentAdmin): ?>
                                                <span class="badge bg-warning text-dark ms-2 align-middle px-2 py-1"><i class="bi bi-shield-fill-check me-1"></i>Admin</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small"><i class="bi bi-clock me-1"></i><?= date('F j, Y g:i A', strtotime($c['created_at'])) ?></div>
                                    </div>
                                    <div class="timeline-body">
                                        <?= $c['comment'] ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                        
                <!-- Reply Box -->
                <div class="col-lg-4 sticky-col mb-4">
                    <div class="reply-box">
                            <form method="POST">
                                <input type="hidden" name="action" value="update_ticket">
                                
                                <?php if ($isAdmin): ?>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Update Status</label>
                                            <select name="status" class="form-select">
                                                <?php
                                                if ($ticket['type'] === 'refund') {
                                                    if ($isMasterAdmin) {
                                                        $statuses = ['SUBMITTED', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'PROCESSED'];
                                                    } else {
                                                        $statuses = ['SUBMITTED', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'PROCESSED'];
                                                        if (!in_array($ticket['status'], $statuses)) $statuses[] = $ticket['status'];
                                                    }
                                                } else {
                                                    $statuses = ['SUBMITTED', 'OPEN', 'UNDER_REVIEW', 'PENDING_APPROVAL', 'APPROVED', 'PROCESSED', 'IN_PROGRESS', 'RESOLVED', 'REJECTED', 'CLOSED'];
                                                }
                                                
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
                
                <!-- Sidebar -->
                <div class="col-lg-3 sticky-col">
                    <div class="meta-sidebar">
                        <h5 class="fw-bold mb-4 pb-2 border-bottom"><i class="bi bi-info-circle me-2 text-primary"></i>Ticket Info</h5>
                        
                        <div class="meta-group">
                            <div class="meta-label"><i class="bi bi-tag"></i>Ticket Type</div>
                            <div class="meta-value"><?= ucfirst($ticket['type']) ?></div>
                        </div>
                        
                        <div class="meta-group">
                            <div class="meta-label"><i class="bi bi-hash"></i>Booking Reference</div>
                            <div class="meta-value">
                                <span class="badge bg-light text-dark border px-3 py-2 fs-6 shadow-sm"><?= htmlspecialchars($ticket['booking_reference'] ?: 'N/A') ?></span>
                            </div>
                        </div>
                        
                        <div class="meta-group">
                            <div class="meta-label"><i class="bi bi-person"></i>Created By</div>
                            <div class="meta-value d-flex align-items-center">
                                <div class="avatar me-2" style="width: 32px; height: 32px; font-size: 0.9rem;"><?= substr($users[$ticket['user_id']]['name'] ?? 'U', 0, 1) ?></div>
                                <?= htmlspecialchars($users[$ticket['user_id']]['name'] ?? 'Unknown') ?>
                            </div>
                        </div>
                        
                        <div class="meta-group">
                            <div class="meta-label"><i class="bi bi-person-badge"></i>Assigned Owner</div>
                            <div class="meta-value">
                                <?php if ($isAdmin): ?>
                                    <a href="#" class="text-decoration-none fw-bold text-primary bg-primary bg-opacity-10 px-3 py-2 rounded d-inline-flex align-items-center"><i class="bi bi-person-fill-gear me-2"></i> <?= htmlspecialchars($users[$ticket['owner_id']]['name'] ?? 'Unassigned') ?></a>
                                <?php else: ?>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar admin-avatar me-2" style="width: 32px; height: 32px; font-size: 0.9rem;"><?= substr($users[$ticket['owner_id']]['name'] ?? 'U', 0, 1) ?></div>
                                        <?= htmlspecialchars($users[$ticket['owner_id']]['name'] ?? 'Unassigned') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="meta-group">
                            <div class="meta-label"><i class="bi bi-calendar-event"></i>Expected Timeline</div>
                            <div class="meta-value">
                                <?= $ticket['expected_timeline'] ? '<span class="badge bg-info text-dark rounded-pill px-3 py-2"><i class="bi bi-clock me-1"></i>' . date('M d, Y h:i A', strtotime($ticket['expected_timeline'])) . '</span>' : '<span class="text-muted small fst-italic">Not set</span>' ?>
                            </div>
                        </div>
                        
                        <div class="meta-group">
                            <div class="meta-label"><i class="bi bi-exclamation-triangle"></i>Delay Reason</div>
                            <div class="meta-value">
                                <?= $ticket['delay_reason'] ? '<div class="alert alert-warning p-2 mb-0 small border-warning border-opacity-50">' . htmlspecialchars($ticket['delay_reason']) . '</div>' : '<span class="text-muted small fst-italic">None</span>' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php include 'components/bottom_navbar.php'; ?>
