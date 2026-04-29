<style>
    /* Ticketing System Professional Navbar */
    .navbar {
        background: var(--navbar-bg, #ffffff);
        border-bottom: 1px solid var(--card-border, #e2e8f0);
        padding: 0;
        position: sticky;
        top: 0;
        z-index: 1001;
        width: 100%;
        height: 56px;
        box-shadow: none;
    }

    body.dark-mode .navbar {
        background: var(--navbar-bg, #0f172a);
        border-bottom-color: var(--card-border, #1e293b);
    }

    .navbar-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
        height: 100%;
        max-width: none;
        gap: 1.5rem;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .sidebar-hamburger {
        background: none;
        border: none;
        font-size: 1.2rem;
        color: var(--text-secondary, #64748b);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 4px;
        display: none;
        align-items: center;
        justify-content: center;
    }

    .sidebar-hamburger:hover {
        background: var(--sidebar-hover, #f1f5f9);
        color: var(--text-main, #0f172a);
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        color: var(--text-main, #0f172a);
        text-decoration: none;
        font-size: 1.1rem;
    }

    .navbar-brand-icon {
        font-size: 1.2rem;
    }

    .navbar-center {
        flex: 1;
        display: flex;
        justify-content: flex-start;
        align-items: center;
    }

    .navbar-search {
        display: flex;
        align-items: center;
        background: var(--sidebar-hover, #f1f5f9);
        border-radius: 4px;
        padding: 0.4rem 0.8rem;
        width: 300px;
        border: 1px solid transparent;
        transition: all 0.2s;
    }
    
    .navbar-search:focus-within {
        border-color: var(--sidebar-accent, #6366f1);
        background: var(--card-bg, #ffffff);
    }

    .navbar-search input {
        border: none;
        background: transparent;
        outline: none;
        padding-left: 0.5rem;
        width: 100%;
        color: var(--text-main, #0f172a);
        font-size: 0.9rem;
    }

    .navbar-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        color: var(--text-secondary, #64748b);
    }

    .navbar-breadcrumb a {
        color: var(--text-main, #0f172a);
        text-decoration: none;
        font-weight: 500;
    }

    .navbar-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .navbar-action-btn {
        background: transparent;
        border: none;
        color: var(--text-secondary, #64748b);
        font-size: 1.1rem;
        cursor: pointer;
        padding: 0.4rem;
        border-radius: 4px;
    }
    .navbar-action-btn:hover {
        background: var(--sidebar-hover, #f1f5f9);
        color: var(--text-main, #0f172a);
    }

    .navbar-user {
        position: relative;
    }

    .navbar-avatar {
        width: 32px;
        height: 32px;
        border-radius: 4px;
        cursor: pointer;
        object-fit: cover;
    }

    .dropdown {
        position: absolute;
        top: calc(100% + 0.25rem);
        right: 0;
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--card-border, #e2e8f0);
        border-radius: 4px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        display: none;
        min-width: 180px;
        z-index: 1000;
    }

    .dropdown.show {
        display: block;
    }

    .dropdown a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1rem;
        color: var(--text-main, #334155);
        text-decoration: none;
        font-size: 0.9rem;
        border-bottom: 1px solid var(--sidebar-hover, #f1f5f9);
    }
    .dropdown a:last-child {
        border-bottom: none;
    }
    .dropdown a:hover {
        background: var(--sidebar-hover, #f8fafc);
    }

    .logout-btn {
        color: var(--logout-bg, #ef4444) !important;
    }
    .logout-btn:hover {
        background: var(--logout-bg-hover, #fee2e2) !important;
    }

    .btn-create {
        background: var(--sidebar-accent, #6366f1);
        color: #ffffff;
        border: none;
        padding: 0.4rem 0.8rem;
        border-radius: 4px;
        font-size: 0.9rem;
        font-weight: 500;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .btn-create:hover {
        opacity: 0.9;
        color: #fff;
    }

    @media (max-width: 900px) {
        .sidebar-hamburger {
            display: flex !important;
        }
        .navbar-search {
            display: none;
        }
        .navbar-content {
            padding: 0 1rem;
        }
    }
</style>

<nav class="navbar">
    <div class="navbar-content">
        <div class="navbar-left">
            <button class="sidebar-hamburger" id="mobile-hamburger" aria-label="Menu">
                <i class="bi bi-list"></i>
            </button>
            <a href="dashboard.php" class="navbar-brand">
                <i class="bi bi-layers-fill navbar-brand-icon"></i>
                <span>Fayyaz CRM</span>
            </a>
        </div>

        <div class="navbar-center">
            <div class="navbar-search">
                <i class="bi bi-search" style="color: var(--text-secondary);"></i>
                <input type="text" placeholder="Search tickets, users, or articles...">
            </div>
        </div>

        <div class="navbar-actions">
            <a href="create-ticket.php" class="btn-create">
                <i class="bi bi-plus-lg"></i> Create
            </a>
            <button class="navbar-action-btn" title="Notifications" onclick="window.location.href='notifications.php'">
                <i class="bi bi-bell"></i>
            </button>
            <button class="navbar-action-btn" title="Toggle Dark Mode" onclick="toggleDarkMode(); return false;">
                <i class="bi bi-moon-stars" id="mode-icon-navbar"></i>
            </button>
            <div class="navbar-user">
                <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>"
                    alt="Profile" class="navbar-avatar" onclick="toggleNavbarDropdown()"
                    title="<?php echo htmlspecialchars($user['name'] ?? 'User'); ?>">
                <div class="dropdown" id="navbar-dropdown">
                    <a href="dashboard.php"><i class="bi bi-person"></i> Profile</a>
                    <a href="logout.php" class="logout-btn"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<script>
    function updateNavbarState() {
        const page = window.location.pathname.split('/').pop() || 'dashboard.php';
        const pageNames = {
            'dashboard.php': 'Dashboard',
            'tickets.php': 'Tickets',
            'notifications.php': 'Notifications',
            'messages.php': 'Messages'
        };

        const label = document.getElementById('current-page-label');
        if (label) {
            label.textContent = pageNames[page] || 'Current Page';
        }

        const modeIcon = document.getElementById('mode-icon-navbar');
        const modeText = document.getElementById('mode-text-navbar');
        if (modeIcon && modeText) {
            if (document.body.classList.contains('dark-mode')) {
                modeIcon.className = 'bi bi-sun';
                modeText.textContent = 'Light Mode';
            } else {
                modeIcon.className = 'bi bi-moon-stars';
                modeText.textContent = 'Dark Mode';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', updateNavbarState);

    const origToggleDarkMode = window.toggleDarkMode;
    window.toggleDarkMode = function () {
        if (origToggleDarkMode) origToggleDarkMode();
        updateNavbarState();
    };

    const mobileHamburger = document.getElementById('mobile-hamburger');
    if (mobileHamburger) {
        mobileHamburger.addEventListener('click', toggleSidebar);
    }

    // ----------------------------------------------------------------------
    // Browser Notifications Polling Logic
    // ----------------------------------------------------------------------
    let lastNotifiedId = parseInt(localStorage.getItem('crm_last_notified_id') || '0', 10);

    function requestNotificationPermission() {
        if (!("Notification" in window)) {
            console.log("This browser does not support desktop notification");
        } else if (Notification.permission !== "denied" && Notification.permission !== "granted") {
            Notification.requestPermission();
        }
    }

    function spawnNotification(title, body, icon) {
        if (Notification.permission === "granted") {
            const options = {
                body: body,
                icon: icon || 'assets/images/main-logo.png' // Use your CRM logo if available
            };
            const n = new Notification(title, options);
            n.onclick = function() {
                window.focus();
                this.close();
            };
        }
    }

    async function checkNewNotifications() {
        if (Notification.permission !== "granted") return;

        try {
            const response = await fetch(`api/check_new_notifications.php?last_id=${lastNotifiedId}`);
            if (!response.ok) return;

            const data = await response.json();
            if (data.success && data.notifications && data.notifications.length > 0) {
                let maxId = lastNotifiedId;
                
                data.notifications.forEach(notification => {
                    spawnNotification(notification.title || "New Notification", notification.message || "You have a new update.");
                    if (parseInt(notification.id) > maxId) {
                        maxId = parseInt(notification.id);
                    }
                });

                // Update the last notified ID to avoid duplicate alerts
                lastNotifiedId = maxId;
                localStorage.setItem('crm_last_notified_id', maxId.toString());
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
        }
    }

    // Run permission request and set up polling on load
    document.addEventListener('DOMContentLoaded', () => {
        requestNotificationPermission();
        
        // Initial check and then poll every 30 seconds
        setTimeout(checkNewNotifications, 2000); // 2 second delay to let page load completely
        setInterval(checkNewNotifications, 30000); 
    });
</script>