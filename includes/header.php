<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require '../includes/db_connect.php';
$is_logged_in = isset($_SESSION['user_id']);
?>
<header>
    <h1><a href="index.php" class="header-link">Stock Investment Simulator</a></h1>
    <div class="auth-buttons">
        <?php if ($is_logged_in): ?>
            <!-- User Dropdown -->
            <div class="dropdown">
                <button class="btn user-icon">👤</button>
                <div class="dropdown-content">
                    <a href="dashboard.php">Dashboard</a>
                    <a href="create_order.php">Create Order</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        <?php else: ?>
            <!-- Login/Sign Up Buttons -->
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn">Sign Up</a>
        <?php endif; ?>
    </div>
</header>
