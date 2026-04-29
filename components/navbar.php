<style>
    /* Premium Professional Navbar */
    .navbar {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid #e2e8f0;
        padding: 0;
        position: sticky;
        top: 0;
        z-index: 1000;
        transition: all 0.3s ease;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        margin: -2rem -2rem 2rem -2rem;
        width: auto;
    }

    body.dark-mode .navbar {
        background: rgba(15, 23, 42, 0.95);
        border-bottom-color: #1e293b;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2);
    }

    .navbar-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.8rem 2rem;
        max-width: none;
        gap: 2rem;
        height: 64px;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex: 0 0 auto;
    }

    .sidebar-hamburger {
        background: none;
        border: none;
        font-size: 1.25rem;
        color: #475569;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 8px;
        transition: all 0.2s ease;
        display: none;
        width: 40px;
        height: 40px;
        align-items: center;
        justify-content: center;
    }

    .sidebar-hamburger:hover {
        background: #f1f5f9;
        color: #0f172a;
    }

    body.dark-mode .sidebar-hamburger {
        color: #94a3b8;
    }

    body.dark-mode .sidebar-hamburger:hover {
        background: #1e293b;
        color: #f8fafc;
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 700;
        color: #0f172a;
        text-decoration: none;
        font-size: 1.15rem;
        letter-spacing: -0.01em;
    }

    .navbar-brand:hover {
        color: #4f46e5;
    }

    .navbar-brand-icon {
        font-size: 1.4rem;
    }

    body.dark-mode .navbar-brand {
        color: #f8fafc;
    }

    body.dark-mode .navbar-brand:hover {
        color: #818cf8;
    }

    .navbar-center {
        flex: 1;
        display: flex;
        justify-content: center;
    }

    .navbar-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.95rem;
        color: #64748b;
        font-weight: 500;
    }

    .navbar-breadcrumb a {
        color: #0f172a;
        text-decoration: none;
        transition: color 0.2s ease;
        font-weight: 600;
    }

    .navbar-breadcrumb a:hover {
        color: #4f46e5;
    }

    body.dark-mode .navbar-breadcrumb {
        color: #94a3b8;
    }

    body.dark-mode .navbar-breadcrumb a {
        color: #f8fafc;
    }

    body.dark-mode .navbar-breadcrumb a:hover {
        color: #818cf8;
    }

    .navbar-actions {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        flex: 0 0 auto;
    }

    .navbar-user {
        position: relative;
    }

    .navbar-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        cursor: pointer;
        border: 2px solid #e2e8f0;
        object-fit: cover;
        transition: all 0.2s ease;
    }

    .navbar-avatar:hover {
        border-color: #cbd5e1;
        box-shadow: 0 0 0 4px rgba(241, 245, 249, 1);
    }

    body.dark-mode .navbar-avatar {
        border-color: #334155;
    }

    body.dark-mode .navbar-avatar:hover {
        border-color: #475569;
        box-shadow: 0 0 0 4px rgba(30, 41, 59, 1);
    }

    .dropdown {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        display: none;
        min-width: 200px;
        z-index: 1000;
        overflow: hidden;
    }

    .dropdown.show {
        display: block;
        animation: dropFade 0.2s ease;
    }

    @keyframes dropFade {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    body.dark-mode .dropdown {
        background: #0f172a;
        border-color: #1e293b;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
    }

    .dropdown a {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.8rem 1.2rem;
        color: #334155;
        text-decoration: none;
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.95rem;
        font-weight: 500;
    }

    .dropdown a:last-child {
        border-bottom: none;
    }

    .dropdown a:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    body.dark-mode .dropdown a {
        color: #cbd5e1;
        border-bottom-color: #1e293b;
    }

    body.dark-mode .dropdown a:hover {
        background: #1e293b;
        color: #f8fafc;
    }

    .logout-btn {
        color: #ef4444 !important;
    }

    .logout-btn:hover {
        background: #fef2f2 !important;
        color: #dc2626 !important;
    }

    body.dark-mode .logout-btn {
        color: #f87171 !important;
    }

    body.dark-mode .logout-btn:hover {
        background: rgba(239, 68, 68, 0.1) !important;
        color: #fca5a5 !important;
    }

    .logout-btn-direct {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        color: #ef4444;
        background: transparent;
        border: 1px solid #ef4444;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }
    .logout-btn-direct:hover {
        background: #ef4444;
        color: #ffffff !important;
        box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    }
    body.dark-mode .logout-btn-direct {
        color: #f87171;
        border-color: #f87171;
    }
    body.dark-mode .logout-btn-direct:hover {
        background: #f87171;
        color: #0f172a !important;
    }

    @media (max-width: 900px) {
        .sidebar-hamburger {
            display: flex !important;
        }

        .navbar-center {
            display: none;
        }

        .navbar-content {
            padding: 0.75rem 1rem;
            gap: 1rem;
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
                <span class="navbar-brand-icon">✈️</span>
                <span>Fayyaz CRM</span>
            </a>
        </div>

        <div class="navbar-center">
            <div class="navbar-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span>/</span>
                <span id="current-page-label">Current Page</span>
            </div>
        </div>

        <div class="navbar-actions">
            <a href="logout.php" class="logout-btn-direct">
                <i class="bi bi-power"></i> Logout
            </a>
            <div class="navbar-user">
                <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>"
                    alt="Profile" class="navbar-avatar" onclick="toggleNavbarDropdown()"
                    title="<?php echo htmlspecialchars($user['name'] ?? 'User'); ?>">
                <div class="dropdown" id="navbar-dropdown">
                    <a href="dashboard.php"><i class="bi bi-person"></i> Profile</a>
                    <a href="#" onclick="toggleDarkMode(); return false;"><i class="bi bi-moon-stars"
                            id="mode-icon-navbar"></i> <span id="mode-text-navbar">Dark Mode</span></a>
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

    var origToggleDarkMode = window.toggleDarkMode;
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