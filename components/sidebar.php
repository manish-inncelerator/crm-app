<?php /* Corporate Professional Sidebar Component */ ?>


<aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <span class="sidebar-logo-icon"><i class="bi bi-layers-fill"></i></span>
            <span class="sidebar-logo-text">Fayyaz CRM</span>
        </div>
        <button class="sidebar-close d-md-none" id="sidebar-close" aria-label="Close sidebar">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="sidebar-nav-label">Main</div>
        <a href="dashboard.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2 sidebar-link-icon"></i>
            <span class="sidebar-link-text">Dashboard</span>
        </a>

        <!-- Tickets Section -->
        <div class="sidebar-nav-label">Work</div>
        <a href="tickets.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'tickets.php' ? 'active' : ''; ?>">
            <i class="bi bi-ticket sidebar-link-icon"></i>
            <span class="sidebar-link-text">Tickets</span>
        </a>
        <?php if (!($is_admin ?? false)): ?>
        <a href="create-ticket.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'create-ticket.php' ? 'active' : ''; ?>">
            <i class="bi bi-plus-circle sidebar-link-icon"></i>
            <span class="sidebar-link-text">New Ticket</span>
        </a>
        <?php endif; ?>

        <!-- Communication -->
        <div class="sidebar-nav-label">Communication</div>
        <a href="messages.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
            <i class="bi bi-chat-left-dots sidebar-link-icon"></i>
            <span class="sidebar-link-text">Messages</span>
        </a>
        <a href="notifications.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'notifications.php' ? 'active' : ''; ?>">
            <i class="bi bi-bell sidebar-link-icon"></i>
            <span class="sidebar-link-text">Notifications</span>
        </a>

        <!-- Other Section -->
        <div class="sidebar-nav-label">Other</div>
        <a href="timeline.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'timeline.php' ? 'active' : ''; ?>">
            <i class="bi bi-hourglass-split sidebar-link-icon"></i>
            <span class="sidebar-link-text">Timeline</span>
        </a>
        <a href="knowledgebase.php"
            class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'knowledgebase.php' ? 'active' : ''; ?>">
            <i class="bi bi-book sidebar-link-icon"></i>
            <span class="sidebar-link-text">Knowledge Base</span>
        </a>
    </nav>

    <!-- Footer with User Info and Controls -->
    <div class="sidebar-footer">
        <!-- User Card -->
        <div class="user-card">
            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>"
                    alt="<?php echo htmlspecialchars($user['name'] ?? 'User'); ?>" class="user-avatar" style="margin-bottom: 0;">
                <div style="display: flex; flex-direction: column;">
                    <span class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'Guest User'); ?></span>
                    <span class="user-email"><?php echo htmlspecialchars($user['email'] ?? 'user@example.com'); ?></span>
                </div>
            </div>
            <?php if (!($is_admin ?? false)): ?>
                <span class="user-role">Employee</span>
            <?php else: ?>
                <span class="user-role">Admin</span>
            <?php endif; ?>
        </div>

        <!-- Controls -->
        <div class="sidebar-controls">
            <button class="sidebar-btn" id="mode-toggle-btn" onclick="toggleDarkMode()" title="Toggle dark mode">
                <i class="bi bi-moon-stars" id="mode-icon"></i>
            </button>
            <a href="logout.php" class="sidebar-btn logout-btn" title="Logout">
                <i class="bi bi-power"></i>
            </a>
        </div>
    </div>
</aside>

<script>
    // Update mode icon when toggled
    const updateModeIcon = () => {
        const icon = document.getElementById('mode-icon');
        if (icon) {
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'bi bi-sun';
            } else {
                icon.className = 'bi bi-moon-stars';
            }
        }
    };

    // Patch toggleDarkMode to update icon
    const origToggleDarkMode = window.toggleDarkMode;
    window.toggleDarkMode = function () {
        if (origToggleDarkMode) origToggleDarkMode();
        updateModeIcon();
    };

    // Close sidebar on mobile when clicking outside
    const sidebar = document.getElementById('sidebar');
    const closeBtn = document.getElementById('sidebar-close');

    if (closeBtn) {
        closeBtn.addEventListener('click', closeSidebar);
    }

    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 900 && sidebar && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !e.target.classList.contains('sidebar-hamburger')) {
                closeSidebar();
            }
        }
    });

    // Control sidebar visibility on resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 900 && sidebar) {
            sidebar.classList.remove('show');
        }
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.remove('show');
        }
    }

    function toggleSidebarMobile() {
        toggleSidebar();
    }

    function toggleNavbarDropdown() {
        const dropdown = document.getElementById('navbar-dropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }

    // Update mode icon on page load
    document.addEventListener('DOMContentLoaded', updateModeIcon);
</script>