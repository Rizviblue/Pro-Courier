<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

// Fetch statistics for the dashboard
$total_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers")->fetch_assoc()['count'];
$in_transit = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'in_transit'")->fetch_assoc()['count'];
$delivered = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'delivered'")->fetch_assoc()['count'];
$cancelled = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'cancelled'")->fetch_assoc()['count'];

// Fetch recent couriers
$recent_couriers_result = $conn->query("SELECT * FROM couriers ORDER BY created_at DESC LIMIT 4");

// Fetch Today's Activity
$new_couriers_today = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$deliveries_today = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE DATE(actual_delivery_date) = CURDATE() AND status = 'delivered'")->fetch_assoc()['count'];
$active_agents_today = $conn->query("SELECT COUNT(DISTINCT assigned_agent_id) as count FROM couriers WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];


include '../includes/header.php';
?>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <?php include '../includes/top_bar.php'; ?>

        <!-- Dashboard Header -->
        <div class="page-header">
            <div>
                <h2>Admin Dashboard</h2>
                <p>Monitor and manage your courier operations</p>
            </div>
            <div>
                <a href="analytics.php" class="btn btn-outline-primary"><i class="fas fa-chart-pie"></i> Analytics</a>
                <a href="add_courier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Courier</a>
            </div>
        </div>

        <!-- Stat Cards (modern, consistent with other admin pages) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total number of couriers.">
                    <div class="stat-info">
                        <h3><?php echo $total_couriers; ?></h3>
                        <p>Total Couriers</p>
                        <span class="stat-sublabel text-success">+5% from last month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Couriers currently in transit.">
                    <div class="stat-info">
                        <h3><?php echo $in_transit; ?></h3>
                        <p>In Transit</p>
                        <span class="stat-sublabel text-primary">+8% from yesterday</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Delivered couriers.">
                    <div class="stat-info">
                        <h3><?php echo $delivered; ?></h3>
                        <p>Delivered</p>
                        <span class="stat-sublabel text-success">+15% this week</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Cancelled couriers.">
                    <div class="stat-info">
                        <h3><?php echo $cancelled; ?></h3>
                        <p>Cancelled</p>
                        <span class="stat-sublabel text-danger">-3% from last week</span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Recent Couriers</h4>
                        <a href="courier_list.php" class="view-all">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    <?php while($row = $recent_couriers_result->fetch_assoc()): ?>
                                    <tr class="courier-item">
                                        <td>
                                            <div class="courier-details">
                                                <p class="courier-id mb-1 fw-bold"><?php echo htmlspecialchars($row['tracking_number']); ?>
                                                    <span class="badge 
                                                        <?php 
                                                            switch($row['status']) {
                                                                case 'in_transit': echo 'bg-primary'; break;
                                                                case 'delivered': echo 'bg-success'; break;
                                                                case 'pending': echo 'bg-warning text-dark'; break;
                                                                case 'cancelled': echo 'bg-danger'; break;
                                                            }
                                                        ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                                    </span>
                                                </p>
                                                <p class="courier-route text-muted mb-0"><?php echo htmlspecialchars($row['sender_name']); ?> → <?php echo htmlspecialchars($row['receiver_name']); ?> <br> <?php echo htmlspecialchars($row['pickup_city']); ?> to <?php echo htmlspecialchars($row['delivery_city']); ?></p>
                                            </div>
                                        </td>
                                        <td class="courier-location">
                                            <p class="mb-1"><?php echo htmlspecialchars($row['delivery_city']); ?></p>
                                            <p class="date text-muted mb-0"><?php echo date('m/d/Y', strtotime($row['created_at'])); ?></p>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card quick-actions mb-4">
                    <div class="card-header">
                        <h4>Quick Actions</h4>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="add_courier.php" class="list-group-item list-group-item-action"><i class="fas fa-plus-circle"></i> Add New Courier</a>
                        <a href="agent_management.php" class="list-group-item list-group-item-action"><i class="fas fa-users"></i> Manage Agents</a>
                        <a href="reports.php" class="list-group-item list-group-item-action"><i class="fas fa-chart-line"></i> View Reports</a>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h4>Today's Activity</h4>
                    </div>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item activity-item d-flex justify-content-between align-items-center">
                            <span>New Couriers</span>
                            <span class="badge bg-primary rounded-pill"><?php echo $new_couriers_today; ?></span>
                        </div>
                        <div class="list-group-item activity-item d-flex justify-content-between align-items-center">
                            <span>Deliveries</span>
                            <span class="badge bg-success rounded-pill"><?php echo $deliveries_today; ?></span>
                        </div>
                        <div class="list-group-item activity-item d-flex justify-content-between align-items-center">
                            <span>Active Agents</span>
                            <span class="badge bg-info rounded-pill"><?php echo $active_agents_today; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Profile Settings Modal -->
    <?php include '../includes/profile_modal.php'; ?>

<?php include '../includes/footer.php'; ?>
