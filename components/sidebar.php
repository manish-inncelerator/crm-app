<?php /* Professional Ticketing System Sidebar Component */ ?>

<aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <i class="bi bi-layers-fill" style="font-size: 1.5rem; color: var(--sidebar-accent); margin-right: 0.75rem;"></i>
        <span style="font-weight: 700; color: var(--sidebar-text-active); font-size: 1.2rem; letter-spacing: -0.02em;">Fayyaz CRM</span>
        <button class="sidebar-close d-md-none" id="sidebar-close" aria-label="Close sidebar" style="margin-left: auto; background: none; border: none; color: var(--sidebar-text);">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" style="padding: 1rem 0;">
        <div style="padding: 0 1.5rem 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--sidebar-text); opacity: 0.5; letter-spacing: 0.05em;">Main</div>
        
        <a href="dashboard.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
        </a>

        <div style="padding: 1.5rem 1.5rem 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--sidebar-text); opacity: 0.5; letter-spacing: 0.05em;">Tickets</div>
        
        <a href="tickets.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'tickets.php' ? 'active' : ''; ?>">
            <i class="bi bi-ticket-perforated"></i>
            <span>All Tickets</span>
        </a>
        
        <?php if (!($is_admin ?? false)): ?>
        <a href="create-ticket.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'create-ticket.php' ? 'active' : ''; ?>">
            <i class="bi bi-plus-square"></i>
            <span>New Ticket</span>
        </a>
        <?php endif; ?>

        <div style="padding: 1.5rem 1.5rem 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--sidebar-text); opacity: 0.5; letter-spacing: 0.05em;">System</div>

        <a href="messages.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
            <i class="bi bi-chat-dots"></i>
            <span>Messages</span>
        </a>
        
        <a href="notifications.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
            <i class="bi bi-bell"></i>
            <span>Notifications</span>
        </a>

        <a href="timeline.php" class="sidebar-nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'timeline.php' ? 'active' : ''; ?>">
            <i class="bi bi-clock-history"></i>
            <span>Audit Logs</span>
        </a>
    </nav>

    <!-- Footer -->
    <div style="margin-top: auto; padding: 1.5rem; border-top: 1px solid var(--sidebar-divider);">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>"
                alt="User" style="width: 32px; height: 32px; border-radius: 4px;">
            <div style="display: flex; flex-direction: column; overflow: hidden;">
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--sidebar-text-active); white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                    <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>
                </span>
                <span style="font-size: 0.75rem; color: var(--sidebar-text); opacity: 0.7; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;">
                    <?php echo ($is_admin ?? false) ? 'Administrator' : 'Agent'; ?>
                </span>
            </div>
        </div>
        
        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button onclick="toggleDarkMode()" style="flex: 1; background: var(--sidebar-btn-bg); border: none; color: var(--sidebar-text); padding: 0.4rem; border-radius: 4px; cursor: pointer;" title="Toggle Dark Mode">
                <i class="bi bi-moon-stars" id="mode-icon"></i>
            </button>
            <a href="logout.php" style="flex: 1; background: var(--sidebar-logout-bg); color: #fff; text-align: center; padding: 0.4rem; border-radius: 4px; text-decoration: none;" title="Logout">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</aside>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('show');
    }
    
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('show');
    }

    const updateSidebarModeIcon = () => {
        const icon = document.getElementById('mode-icon');
        if (icon) {
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'bi bi-sun';
            } else {
                icon.className = 'bi bi-moon-stars';
            }
        }
    };

    // Integration with global toggleDarkMode
    const originalToggleDarkMode = window.toggleDarkMode;
    window.toggleDarkMode = function() {
        if (originalToggleDarkMode) originalToggleDarkMode();
        updateSidebarModeIcon();
    };

    document.addEventListener('DOMContentLoaded', updateSidebarModeIcon);
</script>