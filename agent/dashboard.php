<?php
session_start();
// Check if the user is logged in and is an agent
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'agent') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

// Get the agent_id from the user_id stored in session
$agent_id_query = $conn->prepare("SELECT id FROM agents WHERE user_id = ?");
$agent_id_query->bind_param("i", $_SESSION['user_id']);
$agent_id_query->execute();
$agent_id_result = $agent_id_query->get_result();
$agent = $agent_id_result->fetch_assoc();
$agent_id = $agent['id'];

// Fetch statistics for the logged-in agent
$my_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id")->fetch_assoc()['count'];
$in_transit = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'in_transit'")->fetch_assoc()['count'];
$delivered = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'delivered'")->fetch_assoc()['count'];
$cancelled = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'cancelled'")->fetch_assoc()['count'];

// Fetch recent couriers for the logged-in agent
$recent_couriers_result = $conn->query("SELECT * FROM couriers WHERE assigned_agent_id = $agent_id ORDER BY created_at DESC LIMIT 5");

include '../includes/header.php';
?>

<body>
    <?php include '../includes/sidebar_agent.php'; ?>

    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>

        <div class="dashboard-header">
            <div>
                <h2>Agent Dashboard</h2>
                <p>Manage your assigned courier operations</p>
            </div>
            <div>
                <a href="add_courier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Courier</a>
            </div>
        </div>

        <!-- Stat Cards (modern, consistent with admin dashboard) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total couriers assigned to you.">
                    <div class="stat-info">
                        <h3><?php echo $my_couriers; ?></h3>
                        <p>My Couriers</p>
                        <span class="stat-sublabel text-success">+3% this week</span>
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
                        <span class="stat-sublabel text-primary">+1 today</span>
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
                        <span class="stat-sublabel text-success">+5 this week</span>
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
                        <span class="stat-sublabel text-danger">-1 this week</span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4>My Recent Couriers</h4>
                        <a href="courier_list.php" class="view-all">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <tbody>
                                    <?php while($row = $recent_couriers_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['tracking_number']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($row['sender_name']); ?> -> <?php echo htmlspecialchars($row['receiver_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($row['pickup_city']); ?> -> <?php echo htmlspecialchars($row['delivery_city']); ?>
                                        </td>
                                        <td>
                                            <?php
                                                $status = $row['status'];
                                                $badge_class = '';
                                                switch ($status) {
                                                    case 'in_transit': $badge_class = 'bg-primary'; break;
                                                    case 'delivered': $badge_class = 'bg-success'; break;
                                                    case 'pending': $badge_class = 'bg-warning text-dark'; break;
                                                    case 'cancelled': $badge_class = 'bg-danger'; break;
                                                }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                        </td>
                                        <td><?php echo date('m/d/Y', strtotime($row['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card quick-actions">
                    <div class="card-header">
                        <h4>Quick Actions</h4>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="add_courier.php" class="list-group-item list-group-item-action"><i class="fas fa-plus-circle"></i> Add New Courier</a>
                        <a href="courier_list.php" class="list-group-item list-group-item-action"><i class="fas fa-list-ul"></i> View All Couriers</a>
                        <a href="reports.php" class="list-group-item list-group-item-action"><i class="fas fa-download"></i> Download Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include '../includes/footer.php'; ?>
