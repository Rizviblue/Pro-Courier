<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div>
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="logo-text">
                CourierPro
                <span>Agent Panel</span>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'add_courier.php') ? 'active' : ''; ?>" href="add_courier.php"><i class="fas fa-plus-circle"></i> Add Courier</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'courier_list.php') ? 'active' : ''; ?>" href="courier_list.php"><i class="fas fa-list-ul"></i> Courier List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php"><i class="fas fa-chart-line"></i> Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            </li>
        </ul>
    </div>
    <div class="user-profile">
        <img src="https://placehold.co/40x40/01468b/c5d9ed?text=<?php echo substr($_SESSION['user_name'], 0, 1); ?>" alt="User Avatar">
        <div class="user-info">
            <h6><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
            <p><?php echo htmlspecialchars($_SESSION['user_role']); ?></p>
        </div>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>
