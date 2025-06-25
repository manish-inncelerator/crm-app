<div class="navbar">
    <div class="navbar-content">
        <button class="sidebar-hamburger" onclick="toggleSidebarMobile()" aria-label="Open sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-title">✈️ Fayyaz Travels CRM</div>
        <div class="navbar-actions">
            <div class="navbar-user">
                <img src="<?php echo htmlspecialchars($user['picture'] ?? 'assets/images/default-avatar.png'); ?>" alt="Profile" class="navbar-avatar" onclick="toggleNavbarDropdown()">
                <div class="dropdown" id="navbar-dropdown">
                    <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>