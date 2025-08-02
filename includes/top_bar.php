<?php
// Determine the action page for the search form
$current_page = basename($_SERVER['PHP_SELF']);
$search_action_pages = ['courier_list.php', 'agent_management.php', 'customer_management.php'];
$search_action = in_array($current_page, $search_action_pages) ? $current_page : 'courier_list.php';
?>
<div class="top-bar">
    <form action="<?php echo $search_action; ?>" method="get" class="search-bar">
        <div class="search-icon">
            <i class="fas fa-search"></i>
        </div>
        <input type="text" class="form-control" name="search" placeholder="Search couriers, tracking numbers...">
    </form>
    
    <div class="user-actions">
        <button class="icon-btn" id="theme-toggle-btn">
            <i class="fas fa-moon"></i>
        </button>
        
        <div class="profile-dropdown">
            <a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="modal" data-bs-target="#profileSettingsModal">
                <img src="https://placehold.co/40x40/01468b/c5d9ed?text=<?php echo substr($_SESSION['user_name'], 0, 1); ?>" alt="User Avatar">
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                    <div class="user-role"><?php echo ucfirst(htmlspecialchars($_SESSION['user_role'])); ?></div>
                </div>
            </a>
        </div>
    </div>
</div>
