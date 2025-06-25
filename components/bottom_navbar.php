<div class="mobile-bottom-navbar">
    <button class="mobile-nav-btn<?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo ' active'; ?>" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </button>
    <button class="mobile-nav-btn<?php if (basename($_SERVER['PHP_SELF']) == 'create-ticket.php') echo ' active'; ?>" onclick="window.location.href='create-ticket.php'">
        <i class="fas fa-ticket-alt"></i>
        <span>Tickets</span>
    </button>
    <button class="mobile-nav-btn<?php if (basename($_SERVER['PHP_SELF']) == 'messages.php') echo ' active'; ?>" onclick="window.location.href='messages.php'">
        <i class="fas fa-envelope"></i>
        <span>Messages</span>
    </button>
    <button class="mobile-nav-btn<?php if (basename($_SERVER['PHP_SELF']) == 'notifications.php') echo ' active'; ?>" onclick="window.location.href='notifications.php'">
        <i class="fas fa-bell"></i>
        <span>Notifications</span>
    </button>
    <button class="mobile-nav-btn mode" onclick="toggleDarkMode()" title="Toggle Dark/Light Mode">
        <i class="fas fa-moon"></i>
        <span>Mode</span>
    </button>
    <button class="mobile-nav-btn logout" onclick="window.location.href='logout.php'" title="Logout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </button>
</div>
<script>
    // Show/hide bottom navbar only when at bottom of page
    (function() {
        var lastScrollY = window.scrollY;
        var navbar = document.querySelector('.mobile-bottom-navbar');
        var ticking = false;

        function onScroll() {
            if (!navbar) return;
            var atBottom = (window.innerHeight + window.scrollY) >= (document.body.scrollHeight - 2);
            if (atBottom) {
                // At the very bottom, hide
                navbar.style.transform = 'translateX(-50%) translateY(100px)';
                navbar.style.opacity = '0';
            } else {
                // Otherwise, always show
                navbar.style.transform = 'translateX(-50%) translateY(0)';
                navbar.style.opacity = '1';
            }
            lastScrollY = window.scrollY;
        }
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    onScroll();
                    ticking = false;
                });
                ticking = true;
            }
        });
    })();
</script>