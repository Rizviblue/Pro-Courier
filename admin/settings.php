<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Profile Settings Update
    if (isset($_POST['save_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        // Note: Password change should be a separate, more secure form.

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
        if ($stmt->execute()) {
            $_SESSION['user_name'] = $name; // Update session variable
            $_SESSION['message'] = "Profile updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
        header("Location: settings.php");
        exit;
    }

    // Handle Notification Settings Update
    if (isset($_POST['save_notifications'])) {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
        $order_updates = isset($_POST['order_updates']) ? 1 : 0;
        $system_alerts = isset($_POST['system_alerts']) ? 1 : 0;
        $marketing_emails = isset($_POST['marketing_emails']) ? 1 : 0;

        $stmt = $conn->prepare("UPDATE user_preferences SET email_notifications=?, sms_notifications=?, push_notifications=?, order_updates=?, system_alerts=?, marketing_emails=? WHERE user_id=?");
        $stmt->bind_param("iiiiiii", $email_notifications, $sms_notifications, $push_notifications, $order_updates, $system_alerts, $marketing_emails, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Notification settings saved successfully!";
        } else {
            $_SESSION['error'] = "Error saving notification settings: " . $stmt->error;
        }
        $stmt->close();
        header("Location: settings.php");
        exit;
    }
    
    // Handle Security & System Settings Update
    if (isset($_POST['save_system_settings'])) {
        $theme = $_POST['theme'];
        $currency = $_POST['currency'];
        // Other settings like 2FA, session timeout would require more complex implementation
        
        $stmt = $conn->prepare("UPDATE user_preferences SET theme=?, currency=? WHERE user_id=?");
        $stmt->bind_param("ssi", $theme, $currency, $user_id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "System settings saved successfully!";
        } else {
            $_SESSION['error'] = "Error saving system settings: " . $stmt->error;
        }
        $stmt->close();
        header("Location: settings.php");
        exit;
    }
}

// Display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// --- Fetch current user and preferences data ---
$stmt = $conn->prepare("SELECT u.name, u.email, u.phone, p.* FROM users u LEFT JOIN user_preferences p ON u.id = p.user_id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$settings = $result->fetch_assoc();
$stmt->close();

// --- Fetch System Status Data ---
$db_size_query = $conn->query("SELECT table_schema AS `database`, ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS `size_mb` FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' GROUP BY table_schema");
$db_size = $db_size_query ? $db_size_query->fetch_assoc()['size_mb'] : 'N/A';


include '../includes/header.php';
?>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <?php include '../includes/top_bar.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Settings</h2>
                <p>Manage your system preferences and account settings</p>
            </div>
            <button class="btn btn-outline-secondary">Reset to Default</button>
        </div>
        
        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <!-- Settings Forms -->
        <div class="row">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-user-circle"></i> Profile Settings</h4></div>
                    <div class="card-body">
                        <form action="settings.php" method="post">
                            <div class="mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullName" name="name" value="<?php echo htmlspecialchars($settings['name']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($settings['phone']); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select id="timezone" name="timezone" class="form-select">
                                    <option <?php if($settings['timezone'] == 'America/New_York') echo 'selected'; ?>>Eastern Time (UTC-5)</option>
                                    <option <?php if($settings['timezone'] == 'America/Chicago') echo 'selected'; ?>>Central Time (UTC-6)</option>
                                    <option <?php if($settings['timezone'] == 'America/Denver') echo 'selected'; ?>>Mountain Time (UTC-7)</option>
                                    <option <?php if($settings['timezone'] == 'America/Los_Angeles') echo 'selected'; ?>>Pacific Time (UTC-8)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="language" class="form-label">Language</label>
                                <select id="language" name="language" class="form-select">
                                    <option <?php if($settings['language'] == 'en') echo 'selected'; ?>>English</option>
                                    <option <?php if($settings['language'] == 'es') echo 'selected'; ?>>Spanish</option>
                                </select>
                            </div>
                            <button type="submit" name="save_profile" class="btn btn-primary w-100">Save Profile</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-bell"></i> Notifications</h4></div>
                    <div class="card-body">
                        <form action="settings.php" method="post">
                            <div class="setting-item"><span>Email Notifications</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="email_notifications" <?php echo ($settings['email_notifications'] ?? 1) ? 'checked' : ''; ?>></div></div>
                            <div class="setting-item"><span>SMS Notifications</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="sms_notifications" <?php echo ($settings['sms_notifications'] ?? 0) ? 'checked' : ''; ?>></div></div>
                            <div class="setting-item"><span>Push Notifications</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="push_notifications" <?php echo ($settings['push_notifications'] ?? 1) ? 'checked' : ''; ?>></div></div>
                            <div class="setting-item"><span>Order Updates</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="order_updates" <?php echo ($settings['order_updates'] ?? 1) ? 'checked' : ''; ?>></div></div>
                            <div class="setting-item"><span>System Alerts</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="system_alerts" <?php echo ($settings['system_alerts'] ?? 1) ? 'checked' : ''; ?>></div></div>
                            <div class="setting-item"><span>Marketing Emails</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="marketing_emails" <?php echo ($settings['marketing_emails'] ?? 0) ? 'checked' : ''; ?>></div></div>
                            <button type="submit" name="save_notifications" class="btn btn-primary w-100 mt-3">Save Notifications</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-shield-alt"></i> Security & System</h4></div>
                    <div class="card-body">
                        <form action="settings.php" method="post">
                            <div class="setting-item"><span>Two-Factor Authentication</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch"></div></div>
                            <div class="mb-3">
                                <label for="sessionTimeout" class="form-label">Session Timeout (minutes)</label>
                                <select id="sessionTimeout" name="session_timeout" class="form-select"><option>30 minutes</option><option>60 minutes</option><option>Never</option></select>
                            </div>
                            <div class="mb-3">
                                <label for="currency" class="form-label">Currency</label>
                                <select id="currency" name="currency" class="form-select"><option <?php if($settings['currency'] == 'USD') echo 'selected'; ?>>US Dollar (USD)</option><option <?php if($settings['currency'] == 'EUR') echo 'selected'; ?>>Euro (EUR)</option></select>
                            </div>
                             <div class="mb-3">
                                <label for="theme" class="form-label">Theme</label>
                                <select id="theme" name="theme" class="form-select"><option <?php if($settings['theme'] == 'light') echo 'selected'; ?>>Light</option><option <?php if($settings['theme'] == 'dark') echo 'selected'; ?>>Dark</option></select>
                            </div>
                             <div class="setting-item"><span>Auto-Backup</span><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked></div></div>
                            <button type="submit" name="save_system_settings" class="btn btn-primary w-100 mt-3">Save Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="card">
            <div class="card-header"><h4><i class="fas fa-server"></i> System Status</h4></div>
            <div class="card-body">
                <div class="row">
                    <div class="col"><div class="system-status-card"><div class="status-value text-success">Online</div><div class="status-label">System Status</div></div></div>
                    <div class="col"><div class="system-status-card"><div class="status-value">99.9%</div><div class="status-label">Uptime</div></div></div>
                    <div class="col"><div class="system-status-card"><div class="status-value"><?php echo $db_size; ?> MB</div><div class="status-label">Database Size</div></div></div>
                    <div class="col"><div class="system-status-card"><div class="status-value">v2.1.0</div><div class="status-label">Version</div></div></div>
                </div>
            </div>
        </div>

    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
