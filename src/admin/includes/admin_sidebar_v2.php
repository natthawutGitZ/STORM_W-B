<div class="admin-sidebar">
    <div class="user-profile">
        <div class="avatar-container">
            <img src="/<?php echo htmlspecialchars($_SESSION['user']['avatar'] ?? 'assets/images/default_avatar.png'); ?>"
                alt="User Avatar">
        </div>
        <div class="user-info">
            <h3><?php echo htmlspecialchars($_SESSION['user']['personaname'] ?? 'Admin'); ?></h3>
            <p style="font-family: monospace; color: #64748b; font-size: 0.75rem;">
                <?php echo htmlspecialchars($_SESSION['user']['steamid'] ?? 'Steam ID Not Found'); ?>
            </p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="dashboard.php"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> <span>Members</span>
                </a>
            </li>
            <li>
                <a href="manage_tags.php"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_tags.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tags"></i> <span>Tags</span>
                </a>
            </li>
            <li>
                <a href="manage_media.php"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_media.php' ? 'active' : ''; ?>">
                    <i class="fas fa-photo-video"></i> <span>Media</span>
                </a>
            </li>
            <li>
                <a href="applications.php"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'applications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> <span>Applications</span>
                </a>
            </li>
            <li>
                <a href="chain_of_command.php"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'chain_of_command.php' ? 'active' : ''; ?>">
                    <i class="fas fa-sitemap"></i> <span>Chain of Command</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <a href="/profile" class="back-link">
                <i class="fas fa-arrow-left"></i> <span>Back to Profile</span>
            </a>
            <a href="/logout" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </nav>
</div>

<style>
    .admin-layout {
        display: flex;
        min-height: 100vh;
    }

    .admin-sidebar {
        width: 280px;
        background: #0f172a;
        /* Dark blue/slate background */
        color: #fff;
        display: flex;
        flex-direction: column;
        padding: 20px 0;
        position: sticky;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        border-right: 1px solid rgba(255, 255, 255, 0.1);
        flex-shrink: 0;
    }

    .user-profile {
        text-align: center;
        padding: 20px;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .avatar-container {
        width: 80px;
        height: 80px;
        margin: 0 auto 15px;
        border-radius: 50%;
        overflow: hidden;
        border: 3px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .avatar-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .user-info h3 {
        margin: 0 0 5px;
        font-size: 1.1rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .user-info p {
        margin: 0;
        font-size: 0.8rem;
        color: #94a3b8;
    }

    .sidebar-nav {
        flex: 1;
        padding: 0 15px;
    }

    .sidebar-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-nav li {
        margin-bottom: 5px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #cbd5e1;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
    }

    .sidebar-nav a:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
        transform: translateX(5px);
    }

    .sidebar-nav a.active {
        background: linear-gradient(90deg, rgba(33, 150, 243, 0.2), rgba(33, 150, 243, 0));
        color: #2196f3;
        border-left: 3px solid #2196f3;
    }

    .sidebar-nav i {
        width: 25px;
        text-align: center;
        margin-right: 10px;
        font-size: 1.1rem;
    }

    .sidebar-footer {
        padding: 20px 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: auto;
    }

    .sidebar-footer a {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        color: #94a3b8;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.2s;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .sidebar-footer a:hover {
        color: #fff;
        background: rgba(255, 255, 255, 0.05);
    }

    .sidebar-footer .logout-link:hover {
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
    }

    /* Main Content Area Adjustment */
    .admin-main-content {
        flex: 1;
        background: #0f172a;
        /* Match body background or slightly lighter */
        min-width: 0;
        /* Prevent overflow issues */
    }

    @media (max-width: 768px) {
        .admin-layout {
            flex-direction: column;
        }

        .admin-sidebar {
            width: 100%;
            height: auto;
            position: relative;
            border-right: none;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-profile {
            display: flex;
            align-items: center;
            text-align: left;
            padding: 15px;
        }

        .avatar-container {
            margin: 0 15px 0 0;
            width: 50px;
            height: 50px;
        }
    }
</style>