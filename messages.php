<?php

/**
 * Messages System
 * Handles user-admin communication interface
 * 
 * @package CRM
 * @author Your Company
 * @version 1.0.0
 */

require_once 'vendor/autoload.php';
require_once 'function.php';
require_once 'database.php';

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use GuzzleHttp\Client;
use Auth0\SDK\Store\SessionStore;

// Initialize session with secure settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Constants
const MESSAGES_PER_PAGE = 15;
const MAX_MESSAGE_LENGTH = 1000;
const messagesLimit = 15;

// Initialize HTTP client with proper security settings
$httpClient = new Client([
    'verify' => true, // Enable SSL verification in production
    'timeout' => 30,
    'connect_timeout' => 10
]);

// Auth0 Configuration
$config = new SdkConfiguration(
    domain: 'fayyaztravels.us.auth0.com',
    clientId: 'tgqsr8C26IrvLpq7z5h4fKEeVkEEkLGC',
    clientSecret: 'CGN13kuWTHq7YYGUSj6fJkryAfw-FXJGcGDMp-UHejly5tk4KFP9N64PvuWz1MdO',
    redirectUri: 'http://localhost/crm/callback.php',
    cookieSecret: 'your-secret-key-here',
    httpClient: $httpClient
);

$sessionStore = new SessionStore($config);
$auth0 = new Auth0($config);

/**
 * Validate and sanitize user input
 * @param string $input
 * @return string
 */
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Log system errors
 * @param Exception $e
 * @return void
 */
function logError($e)
{
    error_log(date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", 3, "logs/error.log");
}

try {
    $user = $auth0->getUser();
    if (!$user) {
        header('Location: login.php');
        exit;
    }

    $dbUser = $database->get('users', '*', [
        'auth0_id' => $user['sub']
    ]);

    if (!$dbUser) {
        header('Location: login.php');
        exit;
    }
    $user_id = $dbUser['id'];
    $is_admin = $dbUser['is_admin'] ?? 0;
} catch (\Exception $e) {
    logError($e);
    header('Location: login.php');
    exit;
}

// Find admin user
$admin = $database->get('users', '*', ['is_admin' => 1]);
$admin_id = $admin ? $admin['id'] : null;

// Update last_activity for current user
$database->update('users', [
    'last_activity' => date('Y-m-d H:i:s')
], ['id' => $user_id]);

// Helper to check online status (within 2 minutes)
function is_online($last_activity)
{
    if (!$last_activity) return false;
    return (strtotime($last_activity) > (time() - 120));
}

// For admin: get list of users who have messaged admin, including last_activity
$userList = [];
if ($is_admin) {
    $stmt = $database->pdo->prepare(
        "SELECT u.id, u.name, u.picture, u.last_activity, MAX(m.sent_at) as last_message_time, MAX(m.id) as last_message_id, 
                (SELECT message FROM messages WHERE id = MAX(m.id)) as last_message,
                SUM(CASE WHEN m.status = 'sent' AND m.receiver_id = ? THEN 1 ELSE 0 END) as unread_count
         FROM users u
         JOIN messages m ON u.id = m.sender_id
         WHERE m.receiver_id = ? AND u.is_admin = 0
         GROUP BY u.id, u.name, u.picture, u.last_activity
         ORDER BY last_message_time DESC"
    );
    $stmt->execute([$dbUser['id'], $dbUser['id']]);
    $userList = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// For user: get admin last_activity and picture
$admin_last_activity = null;
$admin_picture = 'assets/images/admin-avatar.png';
if ($admin_id) {
    $admin_row = $database->get('users', ['last_activity', 'picture'], ['id' => $admin_id]);
    $admin_last_activity = $admin_row['last_activity'] ?? null;
    if (!empty($admin_row['picture'])) {
        $admin_picture = $admin_row['picture'];
    }
}

// Pass user info to JS
?>
<script>
    window.CRM_USER = {
        user_id: <?php echo json_encode($user_id); ?>,
        admin_id: <?php echo json_encode($admin_id); ?>,
        user_picture: <?php echo json_encode($user['picture'] ?? 'assets/images/default-avatar.png'); ?>,
        user_name: <?php echo json_encode($dbUser['name'] ?? 'You'); ?>,
        is_admin: <?php echo json_encode($is_admin); ?>,
        last_activity: <?php echo json_encode($dbUser['last_activity'] ?? null); ?>,
        admin_last_activity: <?php echo json_encode($admin_last_activity); ?>
    };
    window.CRM_USERLIST = <?php echo json_encode($userList); ?>;
    const adminPicture = <?php echo json_encode($admin_picture); ?>;
    const messagesLimit = <?php echo MESSAGES_PER_PAGE; ?>;
</script>
<?php

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && $admin_id) {
    $msg = sanitizeInput($_POST['message']);

    if (strlen($msg) > 0 && strlen($msg) <= MAX_MESSAGE_LENGTH) {
        try {
            $database->insert('messages', [
                'sender_id' => $user_id,
                'receiver_id' => $admin_id,
                'message' => $msg,
                'is_admin' => 0,
                'status' => 'sent',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Update last activity
            $database->update('users', [
                'last_activity' => date('Y-m-d H:i:s')
            ], ['id' => $user_id]);
        } catch (\Exception $e) {
            logError($e);
        }
    }
    header('Location: messages.php');
    exit;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * MESSAGES_PER_PAGE;

// Fetch messages between user and admin with pagination
$messages = $database->select('messages', '*', [
    'OR' => [
        'AND #1' => [
            'sender_id' => $user_id,
            'receiver_id' => $admin_id
        ],
        'AND #2' => [
            'sender_id' => $admin_id,
            'receiver_id' => $user_id
        ]
    ],
    'ORDER' => ['sent_at' => 'DESC'],
    'LIMIT' => [MESSAGES_PER_PAGE, $offset]
]);

// Get total message count for pagination
$totalMessages = $database->count('messages', [
    'OR' => [
        'AND #1' => [
            'sender_id' => $user_id,
            'receiver_id' => $admin_id
        ],
        'AND #2' => [
            'sender_id' => $admin_id,
            'receiver_id' => $user_id
        ]
    ]
]);

$totalPages = ceil($totalMessages / MESSAGES_PER_PAGE);

// Mark messages as read
$database->update('messages', [
    'status' => 'read'
], [
    'receiver_id' => $user_id,
    'status' => 'sent'
]);

html_start('Messages');
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
    }

    function toggleNavbarDropdown() {
        var dropdown = document.getElementById('navbar-dropdown');
        dropdown.classList.toggle('show');
    }

    function closeSidebarOnNav() {
        if (window.innerWidth <= 900) {
            var sidebar = document.getElementById('sidebar');
            var backdrop = document.getElementById('sidebar-backdrop');
            sidebar.classList.remove('open');
            if (backdrop) backdrop.remove();
        }
    }

    window.onload = function() {
        if (localStorage.getItem('dashboard-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    }

    window.onclick = function(event) {
        if (!event.target.matches('.navbar-avatar')) {
            var dropdown = document.getElementById('navbar-dropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        }
    }

    function sidebarLogout() {
        window.location.href = 'logout.php';
    }

    function toggleSidebarMobile() {
        var sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
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

    var origToggleDarkMode = window.toggleDarkMode;
    window.toggleDarkMode = function() {
        if (origToggleDarkMode) origToggleDarkMode();
        updateMobileModeIcon();
    };
    document.addEventListener('DOMContentLoaded', updateMobileModeIcon);
</script>
<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-content">
        <div class="content-header">
            <h2 class="main-title">Messages<?php if ($is_admin) echo ' (Admin)'; ?> </h2>
            <div class="message-status" id="chat-status-bar">
                <!-- Status will be filled by JS -->
            </div>
        </div>
        <div class="chat-admin-wrapper" style="display:flex;gap:32px;">
            <?php if ($is_admin): ?>
                <div class="admin-user-list" id="admin-user-list" style="width:320px;min-width:220px;max-width:340px;background:var(--card-bg);border-radius:18px;padding:18px 0;box-shadow:0 2px 8px rgba(78,31,0,0.08);overflow-y:auto;">
                    <h4 style="text-align:center;margin-bottom:18px;">Users</h4>
                    <ul style="list-style:none;padding:0;margin:0;" id="user-list-ul"></ul>
                </div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
                <div class="chat-container">
                    <div class="chat-messages" id="chat-messages">
                        <div class="no-messages" id="no-messages" style="display:none;">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet. Start the conversation below!</p>
                        </div>
                    </div>
                    <form class="chat-input-form" id="message-form" autocomplete="off" enctype="multipart/form-data">
                        <div class="input-group" style="align-items: stretch; display: flex; gap: 0;">
                            <label for="chat-attachment" class="chat-attach-label" title="Attach file">
                                <i class="fas fa-paperclip"></i>
                                <input type="file" id="chat-attachment" name="attachment" class="chat-attach-input" style="display:none;" accept="image/*,audio/*,video/*">
                            </label>
                            <input type="text"
                                name="message"
                                class="form-control"
                                placeholder="Type your message..."
                                maxlength="<?php echo MAX_MESSAGE_LENGTH; ?>"
                                required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                <span>Send</span>
                            </button>
                        </div>
                        <div id="attachment-preview" style="margin-top:8px;"></div>
                        <div class="message-counter beautiful-counter">
                            <span id="char-count">0</span><span class="counter-sep">/</span><?php echo MAX_MESSAGE_LENGTH; ?> <span class="counter-label">characters</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const chatMessages = document.getElementById('chat-messages');
    const noMessagesDiv = document.getElementById('no-messages');
    const messageForm = document.getElementById('message-form');
    const messageInput = messageForm.querySelector('input[name="message"]');
    const charCount = document.getElementById('char-count');
    const userId = window.CRM_USER.user_id;
    const adminId = window.CRM_USER.admin_id;
    const userPicture = window.CRM_USER.user_picture;
    const isAdmin = window.CRM_USER.is_admin;
    let selectedUserId = null;
    const userList = window.CRM_USERLIST;
    let evtSource = null;
    const attachmentInput = document.getElementById('chat-attachment');
    const attachmentPreview = document.getElementById('attachment-preview');
    let selectedFile = null;
    let messagesOffset = 0;
    let allMessagesLoaded = false;
    let loadedMessages = [];

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(' ', 'T'));
        return d.toLocaleString(undefined, {
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function isOnline(lastActivity) {
        if (!lastActivity) return false;
        return (new Date() - new Date(lastActivity.replace(' ', 'T'))) < 2 * 60 * 1000;
    }

    function renderUserList(users) {
        const ul = document.getElementById('user-list-ul');
        ul.innerHTML = '';
        if (!users || users.length === 0) {
            ul.innerHTML = '<li style="text-align:center;color:#aaa;">No users yet</li>';
            return;
        }
        // On first render, if no user is selected, select the first user
        if (!selectedUserId && users.length > 0) {
            selectedUserId = users[0].id;
        }
        users.forEach(user => {
            // Remove counter if this user is selected (chat loaded)
            const showCounter = user.unread_count > 0 && user.id != selectedUserId;
            if (user.id == selectedUserId) user.unread_count = 0;
            const li = document.createElement('li');
            li.className = 'user-list-item';
            li.style = 'display:flex;align-items:center;gap:12px;padding:10px 18px;cursor:pointer;border-bottom:1px solid #eee;';
            li.innerHTML = `
                <span class="status-indicator" style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${isOnline(user.last_activity) ? '#4caf50' : '#aaa'};margin-right:8px;"></span>
                <img src="${user.picture || 'assets/images/default-avatar.png'}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                <div style="flex:1;">
                    <div style="font-weight:600;">${escapeHtml(user.name)}</div>
                    <div style="font-size:0.95em;color:#a98b6d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">${escapeHtml(user.last_message || '')}</div>
                </div>
                ${showCounter ? `<span style='background:#ff5252;color:#fff;border-radius:10px;padding:2px 8px;font-size:0.9em;'>${user.unread_count}</span>` : ''}
            `;
            li.onclick = function() {
                document.querySelectorAll('.user-list-item').forEach(el => el.style.background = '');
                li.style.background = 'var(--sidebar-hover)';
                selectedUserId = user.id;
                renderStatusBar();
                fetchMessages();
                startSSE();
                markMessagesAsRead(user.id);
            };
            ul.appendChild(li);
        });
        // Highlight selected user
        Array.from(ul.children).forEach((li, idx) => {
            if (users[idx].id == selectedUserId) {
                li.style.background = 'var(--sidebar-hover)';
            }
        });
        renderStatusBar();
    }

    function renderStatusBar() {
        const bar = document.getElementById('chat-status-bar');
        // Show status for admin
        const adminOnline = isOnline(window.CRM_USER.admin_last_activity);
        bar.innerHTML = `<span class="status-indicator" style="display:inline-block;width:12px;height:12px;border-radius:50%;background:${adminOnline ? '#4caf50' : '#aaa'};margin-right:8px;"></span> Admin <span style='font-size:0.9em;opacity:0.7;'>(${adminOnline ? 'Online' : 'Offline'})</span>`;
    }

    function renderMessages(messages, prepend = false) {
        if (prepend) {
            // Find the load button (should be the first child)
            const loadBtn = chatMessages.querySelector('.btn.btn-outline-primary');
            let afterNode = loadBtn ? loadBtn.nextSibling : null;
            // Insert older messages at the top
            messages.forEach(msg => {
                const row = createMessageRow(msg);
                chatMessages.insertBefore(row, afterNode);
            });
        } else {
            chatMessages.innerHTML = '';
            if (!messages || messages.length === 0) {
                noMessagesDiv.style.display = '';
                chatMessages.appendChild(noMessagesDiv);
                return;
            }
            noMessagesDiv.style.display = 'none';

            // Add load older button at the very top
            if (!allMessagesLoaded) {
                const loadBtn = document.createElement('button');
                loadBtn.textContent = 'Load older messages';
                loadBtn.className = 'btn btn-outline-primary';
                loadBtn.style.marginBottom = '18px';
                loadBtn.onclick = loadOlderMessages;
                chatMessages.appendChild(loadBtn);
            }

            // Display initial messages
            messages.forEach(msg => {
                chatMessages.appendChild(createMessageRow(msg));
            });
        }
        // Only scroll to bottom on initial load
        if (!prepend) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
    }

    function createMessageRow(msg) {
        const isUser = isAdmin ? (msg.sender_id == selectedUserId) : (msg.sender_id == userId);
        const row = document.createElement('div');
        row.className = 'chat-row' + (isUser ? ' user' : '');
        row.setAttribute('data-message-id', msg.id);
        let attachmentHtml = '';
        if (msg.attachment) {
            const ext = msg.attachment.split('.').pop().toLowerCase();
            if (msg.attachment.match(/^assets\/(uploads|user_avatars)\//)) {
                if (msg.attachment.match(/\.(jpg|jpeg|png|gif|webp|bmp)$/i)) {
                    attachmentHtml = `<img src="${msg.attachment}" style="max-width:180px;max-height:120px;border-radius:8px;box-shadow:0 1px 4px #0002;margin-bottom:6px;">`;
                } else if (msg.attachment.match(/\.(mp3|wav|ogg|m4a|aac)$/i)) {
                    attachmentHtml = `<audio controls src="${msg.attachment}" style="max-width:180px;display:block;margin-bottom:6px;"></audio>`;
                } else if (msg.attachment.match(/\.(mp4|webm|ogg|mov|avi)$/i)) {
                    attachmentHtml = `<video controls src="${msg.attachment}" style="max-width:180px;max-height:100px;border-radius:8px;display:block;margin-bottom:6px;"></video>`;
                } else {
                    attachmentHtml = `<a href="${msg.attachment}" target="_blank" style="color:#a97c50;">Download attachment</a>`;
                }
            }
        }
        row.innerHTML = `
            <img src="${isUser
                ? (isAdmin
                    ? (userList.find(u=>u.id==selectedUserId)?.picture||'assets/images/default-avatar.png')
                    : userPicture)
                : adminPicture}"
                alt="${isUser ? (isAdmin ? userList.find(u=>u.id==selectedUserId)?.name : 'You') : 'Admin'}"
                class="chat-avatar" loading="lazy">
            <div class="chat-bubble ${isUser ? 'user' : 'admin'}">
                ${attachmentHtml}
                <div class="message-content">${escapeHtml(msg.message)}</div>
                <div class="chat-meta">
                    <span class="message-time">${formatDate(msg.sent_at)}</span>
                    <span class="message-status">${isUser && !isAdmin ? (msg.status === 'read' ? '✓✓' : '✓') : ''}</span>
                    <span class="message-sender">${isUser ? (isAdmin ? (userList.find(u=>u.id==selectedUserId)?.name||'User') : 'You') : 'Admin'}</span>
                </div>
            </div>
        `;
        return row;
    }

    function fetchMessages(initial = true) {
        let sid, rid;
        if (isAdmin) {
            sid = selectedUserId;
            rid = userId;
        } else {
            sid = userId;
            rid = adminId;
        }
        if (!sid || !rid) return;
        let url = `api/messages_list.php?sender_id=${sid}&receiver_id=${rid}&limit=${messagesLimit}&offset=${messagesOffset}`;
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (initial) {
                        loadedMessages = data.messages;
                        renderMessages(loadedMessages);
                        if (data.messages.length < messagesLimit) allMessagesLoaded = true;
                    } else {
                        if (data.messages.length === 0) {
                            allMessagesLoaded = true;
                            // Optionally hide load button
                            document.querySelector('.btn.btn-outline-primary').style.display = 'none';
                        } else {
                            loadedMessages = data.messages.concat(loadedMessages);
                            renderMessages(data.messages, true);
                        }
                    }
                } else {
                    chatMessages.innerHTML = '<div style="color:red;text-align:center;">Failed to load messages.</div>';
                }
            })
            .catch(() => {
                chatMessages.innerHTML = '<div style="color:red;text-align:center;">Failed to load messages.</div>';
            });
    }

    function loadOlderMessages() {
        // Save current scroll position relative to the first message
        const firstMsg = chatMessages.querySelector('.chat-row');
        const prevOffset = firstMsg ? firstMsg.getBoundingClientRect().top : 0;

        messagesOffset += messagesLimit;
        let sid, rid;
        if (isAdmin) {
            sid = selectedUserId;
            rid = userId;
        } else {
            sid = userId;
            rid = adminId;
        }
        if (!sid || !rid) return;
        let url = `api/messages_list.php?sender_id=${sid}&receiver_id=${rid}&limit=${messagesLimit}&offset=${messagesOffset}`;
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (data.messages.length === 0) {
                        allMessagesLoaded = true;
                        const loadBtn = document.querySelector('.btn.btn-outline-primary');
                        if (loadBtn) {
                            loadBtn.style.display = 'none';
                            const noMoreMsg = document.createElement('div');
                            noMoreMsg.style.textAlign = 'center';
                            noMoreMsg.style.color = '#666';
                            noMoreMsg.style.padding = '10px';
                            noMoreMsg.style.fontSize = '0.9em';
                            noMoreMsg.textContent = "That's all - no further messages";
                            loadBtn.parentNode.insertBefore(noMoreMsg, loadBtn.nextSibling);
                        }
                    } else {
                        loadedMessages = data.messages.concat(loadedMessages);
                        renderMessages(data.messages, true);

                        // Restore scroll position so chat doesn't jump
                        if (firstMsg) {
                            const newFirstMsg = chatMessages.querySelectorAll('.chat-row')[data.messages.length];
                            if (newFirstMsg) {
                                const newOffset = newFirstMsg.getBoundingClientRect().top;
                                chatMessages.scrollTop += (newOffset - prevOffset);
                            }
                        }
                    }
                }
            });
    }

    function startSSE() {
        let sid, rid;
        if (isAdmin) {
            sid = selectedUserId;
            rid = userId;
        } else {
            sid = userId;
            rid = adminId;
        }
        if (!sid || !rid) return;
        if (evtSource) evtSource.close();
        evtSource = new EventSource(`api/messages_sse.php?sender_id=${sid}&receiver_id=${rid}`);
        evtSource.onmessage = function(event) {
            if (!event.data) return;
            const data = JSON.parse(event.data);
            let shouldUpdate = false;
            // If new message, refresh chat
            if (data.new_message) {
                fetchMessages();
                shouldUpdate = true;
            }
            // Update online status for user/admin
            if (isAdmin) {
                const user = userList.find(u => u.id == selectedUserId);
                if (user) {
                    user.last_activity = data.sender_online ? new Date().toISOString() : user.last_activity;
                }
            } else {
                window.CRM_USER.admin_last_activity = data.receiver_online ? new Date().toISOString() : window.CRM_USER.admin_last_activity;
            }
            if (shouldUpdate) renderStatusBar();
        };
        evtSource.onerror = function() {
            // Try to reconnect after a short delay
            setTimeout(startSSE, 2000);
        };
    }

    function fetchUserList() {
        fetch('api/user_list.php')
            .then(res => res.json())
            .then(data => {
                if (data.success && Array.isArray(data.users)) {
                    // Update global userList and re-render
                    userList.length = 0;
                    data.users.forEach(u => userList.push(u));
                    renderUserList(userList);

                    // If no user is selected or selected user is not in the list, select the first user
                    if (userList.length > 0) {
                        if (!selectedUserId || !userList.find(u => u.id == selectedUserId)) {
                            selectedUserId = userList[0].id;
                            // Highlight first user in sidebar
                            setTimeout(() => {
                                const ul = document.getElementById('user-list-ul');
                                if (ul && ul.children.length > 0) {
                                    ul.children[0].style.background = 'var(--sidebar-hover)';
                                }
                            }, 0);
                        }
                        fetchMessages();
                        renderStatusBar();
                        startSSE();
                    }
                }
            });
    }

    function markMessagesAsRead(userId) {
        if (!isAdmin || !userId) return;
        fetch('api/mark_messages_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `sender_id=${encodeURIComponent(userId)}&receiver_id=${encodeURIComponent(window.CRM_USER.user_id)}`
            })
            .then(() => {
                // Set unread_count to 0 for this user in the userList array
                const user = userList.find(u => u.id == userId);
                if (user) user.unread_count = 0;
                renderUserList(userList);
                // Optionally, also fetch the latest user list from the server
                // fetchUserList();
            });
    }

    attachmentInput.addEventListener('change', function() {
        attachmentPreview.innerHTML = '';
        selectedFile = null;
        if (this.files && this.files[0]) {
            const file = this.files[0];
            selectedFile = file;
            const url = URL.createObjectURL(file);
            if (file.type.startsWith('image/')) {
                attachmentPreview.innerHTML = `<img src="${url}" style="max-width:120px;max-height:80px;border-radius:8px;box-shadow:0 1px 4px #0002;">`;
            } else if (file.type.startsWith('audio/')) {
                attachmentPreview.innerHTML = `<audio controls src="${url}" style="max-width:180px;"></audio>`;
            } else if (file.type.startsWith('video/')) {
                attachmentPreview.innerHTML = `<video controls src="${url}" style="max-width:180px;max-height:100px;border-radius:8px;"></video>`;
            } else {
                attachmentPreview.innerHTML = `<span style='color:#a97c50;'>Unsupported file type</span>`;
            }
        }
    });

    messageForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const msg = messageInput.value.trim();
        if (!msg && !selectedFile) return;
        messageInput.disabled = true;
        let sid, rid;
        if (isAdmin) {
            sid = userId;
            rid = selectedUserId;
        } else {
            sid = userId;
            rid = adminId;
        }
        const formData = new FormData();
        formData.append('sender_id', sid);
        formData.append('receiver_id', rid);
        formData.append('message', msg);
        if (selectedFile) {
            formData.append('attachment', selectedFile);
        }
        fetch('api/messages_send.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    charCount.textContent = '0';
                    attachmentInput.value = '';
                    attachmentPreview.innerHTML = '';
                    selectedFile = null;
                    fetchMessages();
                } else {
                    alert('Failed to send message: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(() => {
                alert('Failed to send message.');
            })
            .finally(() => {
                messageInput.disabled = false;
                messageInput.focus();
            });
    });

    // Character counter
    messageInput.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });

    // Initial load
    if (isAdmin) {
        fetchUserList();
        renderStatusBar();
        startSSE();
    } else {
        fetchMessages(true);
        renderStatusBar();
        startSSE();
    }
</script>

<?php include 'components/bottom_navbar.php'; ?>
<?php html_end(); ?>