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

try {
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
    $user_id = $dbUser['id'];
    $is_admin = $dbUser['is_admin'] ?? 0;
} catch (\Exception $e) {
    header('Location: login.php');
    exit;
}

html_start('Messages - Fayyaz Travels CRM', ['assets/css/messages.css']);
?>

<div class="dashboard-container">
    <?php include 'components/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'components/navbar.php'; ?>
        
        <div style="padding: 1.5rem;">
            <div style="margin-bottom: 1.5rem;">
                <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--text-main);">Messages</h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem;">Direct communication with the support team</p>
            </div>

            <div class="chat-admin-wrapper">
                <?php if ($is_admin): ?>
                    <div class="admin-user-list">
                        <h4>Conversations</h4>
                        <ul class="user-list-ul" id="user-list-ul">
                            <!-- Users will be loaded here via JS -->
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="chat-container">
                    <div class="chat-messages" id="chat-messages">
                        <!-- Messages will be loaded here via JS -->
                    </div>

                    <div class="chat-input-form">
                        <form id="message-form">
                            <div class="input-group">
                                <label for="chat-attachment" class="chat-attach-label">
                                    <i class="bi bi-paperclip"></i>
                                    <input type="file" id="chat-attachment" style="display:none;">
                                </label>
                                <input type="text" id="message-input" placeholder="Type your message here..." required>
                                <button type="submit" class="btn-send">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </div>
                            <div id="attachment-preview" style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--sidebar-accent);"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
    // Simplified Chat Logic
    const userId = <?php echo $user_id; ?>;
    const isAdmin = <?php echo $is_admin; ?>;
    let selectedChatId = isAdmin ? null : 1; // Default for users

    function loadMessages() {
        // Fetch and render messages
        const mockMessages = [
            { id: 1, sender_id: 2, message: "Hello, how can I help you today?", sent_at: "2024-04-29 10:00:00" },
            { id: 2, sender_id: userId, message: "I have a question about my last ticket.", sent_at: "2024-04-29 10:05:00" }
        ];
        
        const container = $('#chat-messages');
        container.empty();
        
        mockMessages.forEach(msg => {
            const isMe = msg.sender_id === userId;
            const html = `
                <div class="chat-row ${isMe ? 'user' : ''}">
                    <img src="assets/images/default-avatar.png" class="chat-avatar">
                    <div class="chat-bubble ${isMe ? 'user' : 'admin'}">
                        <div class="message-content">${msg.message}</div>
                        <div class="chat-meta">
                            <span>${msg.sent_at}</span>
                        </div>
                    </div>
                </div>
            `;
            container.append(html);
        });
        
        container.scrollTop(container[0].scrollHeight);
    }

    $(document).ready(function() {
        loadMessages();
        
        $('#message-form').on('submit', function(e) {
            e.preventDefault();
            const msg = $('#message-input').val();
            if(!msg) return;
            
            // Send logic here
            $('#message-input').val('');
            loadMessages();
        });
    });
</script>

<?php html_end(); ?>