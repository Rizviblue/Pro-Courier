<?php
session_start();
// Check if the user is logged in and is an agent
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'agent') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Update Profile Information ---
    if (isset($_POST['save_profile'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $fullname; // Update session variable
            $_SESSION['message'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating profile.";
        }
        $stmt->close();
        header("Location: settings.php");
        exit;
    }
}

// --- Fetch current agent data ---
$stmt = $conn->prepare("SELECT u.name, u.email, u.phone, a.working_hours, a.max_daily_orders, a.availability, a.auto_assign, c.name as city_name 
                        FROM users u 
                        JOIN agents a ON u.id = a.user_id 
                        LEFT JOIN cities c ON a.city_id = c.id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$agent_data = $result->fetch_assoc();
$stmt->close();

// --- Fetch Performance Overview ---
$rating_query = $conn->query("SELECT rating FROM agents WHERE user_id = $user_id");
$rating = $rating_query ? $rating_query->fetch_assoc()['rating'] : 'N/A';

$total_deliveries_query = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = (SELECT id FROM agents WHERE user_id = $user_id)");
$total_deliveries = $total_deliveries_query ? $total_deliveries_query->fetch_assoc()['count'] : 0;

$success_rate_query = $conn->query("SELECT success_rate FROM agents WHERE user_id = $user_id");
$success_rate = $success_rate_query ? $success_rate_query->fetch_assoc()['success_rate'] : 0;

$avg_delivery_time = "2.3h"; // Placeholder

include '../includes/header.php';
?>

<body>
    <?php include '../includes/sidebar_agent.php'; ?>

    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>

        <div class="page-header">
            <div>
                <h2>Agent Settings</h2>
                <p>Manage your profile and work preferences</p>
            </div>
        </div>
        
        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-user-circle"></i> Profile Settings</h4></div>
                    <div class="card-body">
                        <form action="settings.php" method="post">
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullName" name="fullname" value="<?php echo htmlspecialchars($agent_data['name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($agent_data['email']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($agent_data['phone']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="workingCity" class="form-label">Working City</label>
                                <input type="text" class="form-control" id="workingCity" value="<?php echo htmlspecialchars($agent_data['city_name']); ?>" readonly>
                            </div>
                            <button type="submit" name="save_profile" class="btn btn-primary w-100">Save Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-briefcase"></i> Work Preferences</h4></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="workingHours" class="form-label">Working Hours</label>
                            <select id="workingHours" class="form-select">
                                <option <?php echo ($agent_data['working_hours'] == '9to5') ? 'selected' : ''; ?>>9 AM - 5 PM</option>
                                <option <?php echo ($agent_data['working_hours'] == '10to6') ? 'selected' : ''; ?>>10 AM - 6 PM</option>
                            </select>
                        </div>
                        <div class="setting-item">
                            <span>Currently Available</span>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" <?php echo ($agent_data['availability']) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="setting-item">
                            <span>Auto-assign Orders</span>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" <?php echo ($agent_data['auto_assign']) ? 'checked' : ''; ?>>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="maxOrders" class="form-label">Max Daily Orders</label>
                            <select id="maxOrders" class="form-select">
                                <option <?php echo ($agent_data['max_daily_orders'] == 20) ? 'selected' : ''; ?>>20 orders</option>
                                <option <?php echo ($agent_data['max_daily_orders'] == 30) ? 'selected' : ''; ?>>30 orders</option>
                                <option <?php echo ($agent_data['max_daily_orders'] == 40) ? 'selected' : ''; ?>>40 orders</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">Save Preferences</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card performance-overview">
            <div class="card-header"><h4><i class="fas fa-chart-line"></i> Performance Overview</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="stat-card border-0">
                            <div class="stat-value"><?php echo $rating; ?></div>
                            <div class="stat-label">Rating</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card border-0">
                            <div class="stat-value"><?php echo $total_deliveries; ?></div>
                            <div class="stat-label">Total Deliveries</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card border-0">
                            <div class="stat-value"><?php echo round($success_rate, 1); ?>%</div>
                            <div class="stat-label">Success Rate</div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card border-0">
                            <div class="stat-value"><?php echo $avg_delivery_time; ?></div>
                            <div class="stat-label">Avg. Delivery Time</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
