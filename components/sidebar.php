<?php /* Sidebar component */ ?>
<style>
    /* Sidebar styles moved from dashboard.php */
    :root {
        --sidebar-width: 210px;
        --sidebar-bg-glass: linear-gradient(135deg, rgba(255, 255, 255, 0.85) 60%, #f3e7db 100%);
        --sidebar-accent: #4E1F00;
        --sidebar-accent-light: #a97c50;
        --sidebar-pill-bg: rgba(78, 31, 0, 0.85);
        --sidebar-pill-shadow: 0 4px 16px rgba(78, 31, 0, 0.10);
        --sidebar-icon-inactive: #a97c50;
        --sidebar-icon-active: #fff;
        --sidebar-text-inactive: #7a5a3a;
        --sidebar-text-active: #fff;
        --sidebar-user-card-bg: rgba(255, 255, 255, 0.75);
        --sidebar-user-card-shadow: 0 2px 8px rgba(78, 31, 0, 0.07);
        --sidebar-btn-bg: rgba(255, 255, 255, 0.85);
        --sidebar-btn-shadow: 0 2px 8px rgba(78, 31, 0, 0.10);
        --sidebar-btn-hover: #4E1F00;
        --sidebar-btn-hover-text: #fff;
        --sidebar-divider: 1.5px solid #e7d7c3;
        --sidebar-font: 'Inter', 'Segoe UI', 'Roboto', Arial, sans-serif;
    }

    body.dark-mode {
        --sidebar-bg-glass: linear-gradient(135deg, rgba(34, 37, 38, 0.98) 60%, #2d1a0d 100%);
        --sidebar-accent: #fff;
        --sidebar-accent-light: #a97c50;
        --sidebar-pill-bg: rgba(78, 31, 0, 0.92);
        --sidebar-pill-shadow: 0 4px 16px rgba(78, 31, 0, 0.18);
        --sidebar-icon-inactive: #e7d7c3;
        --sidebar-icon-active: #fff;
        --sidebar-text-inactive: #e7d7c3;
        --sidebar-text-active: #fff;
        --sidebar-user-card-bg: rgba(34, 37, 38, 0.92);
        --sidebar-user-card-shadow: 0 2px 8px rgba(78, 31, 0, 0.18);
        --sidebar-btn-bg: rgba(34, 37, 38, 0.98);
        --sidebar-btn-shadow: 0 2px 8px rgba(78, 31, 0, 0.18);
        --sidebar-btn-hover: #fff;
        --sidebar-btn-hover-text: #4E1F00;
        --sidebar-divider: 1.5px solid #4E1F00;
        --sidebar-font: 'Inter', 'Segoe UI', 'Roboto', Arial, sans-serif;
    }

    .sidebar {
        width: var(--sidebar-width);
        min-width: var(--sidebar-width);
        background: var(--sidebar-bg-glass);
        box-shadow: 0 8px 32px rgba(78, 31, 0, 0.13), 0 1.5px 8px rgba(0, 0, 0, 0.04);
        border-radius: 32px;
        margin: 24px 0 24px 24px;
        padding: 24px 0 24px 0;
        border: none;
        backdrop-filter: blur(18px);
        display: flex;
        flex-direction: column;
        align-items: stretch;
        z-index: 1000;
        position: fixed;
        top: 0;
        left: 0;
        height: calc(100vh - 48px);
        font-family: var(--sidebar-font);
        transition: box-shadow 0.3s, background 0.3s, color 0.3s, left 0.3s;
        overflow: visible;
    }

    .sidebar-logo {
        width: 100px;
        height: 100px;
        border-radius: 0;
        margin: 36px auto 18px auto;
        background: #fff;
        object-fit: contain;
        display: block;
        box-shadow: none;
    }

    .sidebar-divider {
        width: 80%;
        height: 1.5px;
        background: #e7d7c3;
        margin: 18px auto 18px auto;
        border-radius: 2px;
        border: none;
        opacity: 0.7;
    }

    .sidebar-nav {
        gap: 10px;
        padding: 0 0 0 0;
        display: flex;
        flex-direction: column;
        align-items: stretch;
    }

    .sidebar-nav-link {
        width: 90%;
        min-width: 0;
        height: 46px;
        padding: 0 18px;
        gap: 18px;
        font-size: 1.08rem;
        align-items: center;
        display: flex;
        border-radius: 22px;
        font-family: var(--sidebar-font);
        color: var(--sidebar-text-inactive);
        background: transparent;
        font-weight: 500;
        letter-spacing: 0.01em;
        margin: 0 auto;
        transition: background 0.18s, color 0.18s, box-shadow 0.18s;
        box-shadow: none;
        text-decoration: none;
        border: none;
    }

    .sidebar-nav-link.active {
        background: #4E1F00;
        color: #fff;
        box-shadow: var(--sidebar-pill-shadow);
    }

    .sidebar-nav-link.active .sidebar-nav-icon {
        color: #fff;
        background: transparent;
        font-weight: 700;
    }

    .sidebar-nav-link.active .sidebar-nav-label {
        color: #fff;
        font-weight: 600;
    }

    .sidebar-nav-link .sidebar-nav-icon {
        font-size: 1.35rem;
        width: 32px;
        min-width: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--sidebar-icon-inactive);
        transition: color 0.18s;
    }

    .sidebar-nav-link .sidebar-nav-label {
        font-size: 1.08rem;
        margin-left: 0;
        max-width: 140px;
        color: var(--sidebar-text-inactive);
        font-family: var(--sidebar-font);
        font-weight: 500;
        opacity: 1;
        pointer-events: auto;
        transition: color 0.18s;
        text-decoration: none;
    }

    .sidebar-nav-link:hover {
        background: rgba(78, 31, 0, 0.08);
        color: var(--sidebar-accent);
        box-shadow: 0 2px 12px rgba(78, 31, 0, 0.06);
    }

    .sidebar-nav-link:hover .sidebar-nav-icon {
        color: var(--sidebar-accent);
    }

    .sidebar-nav-link:hover .sidebar-nav-label {
        color: var(--sidebar-accent);
    }

    .sidebar-bottom {
        gap: 18px;
        margin-bottom: 18px;
        padding-bottom: 18px;
        border-top: var(--sidebar-divider);
        width: 80%;
        margin-left: auto;
        margin-right: auto;
        padding-top: 18px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .sidebar-user {
        background: var(--sidebar-user-card-bg);
        box-shadow: var(--sidebar-user-card-shadow);
        border-radius: 18px;
        padding: 18px 8px 12px 8px;
        gap: 6px;
        margin-bottom: 2px;
        align-items: center;
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    .sidebar-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        background: #fff;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(78, 31, 0, 0.10);
        margin-bottom: 4px;
    }

    .sidebar-user-name {
        font-size: 1.08rem;
        font-weight: 600;
        color: #4E1F00;
        text-align: center;
        margin: 0;
        letter-spacing: 0.01em;
        font-family: var(--sidebar-font);
    }

    .sidebar-user-email {
        font-size: 0.92rem;
        color: #a98b6d;
        text-align: center;
        margin: 0;
        letter-spacing: 0.01em;
        font-family: var(--sidebar-font);
        word-break: break-all;
        max-width: 90%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: block;
        margin-left: auto;
        margin-right: auto;
    }

    .sidebar-mode,
    .sidebar-logout {
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: var(--sidebar-btn-bg);
        color: #4E1F00;
        font-size: 1.3rem;
        border: none;
        cursor: pointer;
        transition: background 0.18s, color 0.18s, box-shadow 0.18s;
        margin-bottom: 0;
        box-shadow: var(--sidebar-btn-shadow);
        margin-top: 0;
    }

    .sidebar-mode:hover,
    .sidebar-logout:hover {
        background: var(--sidebar-btn-hover);
        color: var(--sidebar-btn-hover-text);
        box-shadow: 0 4px 16px rgba(78, 31, 0, 0.13);
    }

    /* Hamburger styles */
    .sidebar-hamburger {
        display: none;
    }

    @media (max-width: 900px) {
        .sidebar {
            display: none !important;
        }
    }

    body.dark-mode .sidebar-logout {
        background: #ff8a80 !important;
        color: #4E1F00 !important;
    }

    body.dark-mode .sidebar-logout:hover {
        background: #ff5252 !important;
        color: #fff !important;
    }
</style>
<div class="sidebar" id="sidebar">
    <img src="https://fayyaztravels.com/visa/assets/images/main-logo.png" alt="Fayyaz Travels" class="sidebar-logo">
    <hr class="sidebar-divider">
    <div class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-nav-link<?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo ' active'; ?>" title="Dashboard" onclick="closeSidebarOnNav()">
            <span class="sidebar-nav-icon"><i class="fas fa-home"></i></span>
            <span class="sidebar-nav-label">Dashboard</span>
        </a>
        <a href="tickets.php" class="sidebar-nav-link<?php if (basename($_SERVER['PHP_SELF']) == 'tickets.php') echo ' active'; ?>" title="My Tickets" onclick="closeSidebarOnNav()">
            <span class="sidebar-nav-icon"><i class="fas fa-ticket-alt"></i></span>
            <span class="sidebar-nav-label">Tickets</span>
        </a>
        <a href="messages.php" class="sidebar-nav-link<?php if (basename($_SERVER['PHP_SELF']) == 'messages.php') echo ' active'; ?>" title="Messages" onclick="closeSidebarOnNav()">
            <span class="sidebar-nav-icon"><i class="fas fa-envelope"></i></span>
            <span class="sidebar-nav-label">Messages</span>
        </a>
        <a href="notifications.php" class="sidebar-nav-link<?php if (basename($_SERVER['PHP_SELF']) == 'notifications.php') echo ' active'; ?>" title="My Notifications" onclick="closeSidebarOnNav()">
            <span class="sidebar-nav-icon"><i class="fas fa-bell"></i></span>
            <span class="sidebar-nav-label">Notifications</span>
        </a>
    </div>
    <div class="sidebar-bottom">
        <div class="sidebar-user">
            <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>" alt="Profile" class="sidebar-avatar" title="<?php echo htmlspecialchars($user['name'] ?? 'User'); ?>">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></div>
            <div class="sidebar-user-email"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
        </div>
        <button class="sidebar-mode" onclick="toggleDarkMode()" title="Toggle Dark/Light Mode"><span id="mode-icon">🌙</span></button>
        <button class="sidebar-logout" onclick="sidebarLogout()" title="Logout"><i class="fas fa-sign-out-alt"></i></button>
    </div>
</div>
<script>
    function toggleSidebarMobile() {
        var sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('open');
    }
    // Update sidebar mode icon
    function updateSidebarModeIcon() {
        var icon = document.getElementById('mode-icon');
        if (!icon) return;
        if (document.body.classList.contains('dark-mode')) {
            icon.textContent = '☀️';
        } else {
            icon.textContent = '🌙';
        }
    }
    // Patch toggleDarkMode to update sidebar icon
    var origToggleDarkModeSidebar = window.toggleDarkMode;
    window.toggleDarkMode = function() {
        if (origToggleDarkModeSidebar) origToggleDarkModeSidebar();
        updateSidebarModeIcon();
    };
    document.addEventListener('DOMContentLoaded', updateSidebarModeIcon);
</script>