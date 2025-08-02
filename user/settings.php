<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'user') {
    header("location: ../index.php");
    exit;
}
require_once '../includes/db_connect.php';
$user_id = $_SESSION['user_id'];

// Fetch user data
$user_data_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user_data = $user_data_query->fetch_assoc();

// Fetch customer data
$customer_data = null;
$customer_query = $conn->prepare("SELECT * FROM customers WHERE user_id = ?");
$customer_query->bind_param("i", $user_id);
$customer_query->execute();
$result = $customer_query->get_result();
if ($result->num_rows > 0) {
    $customer_data = $result->fetch_assoc();
}
$customer_query->close();

include '../includes/header.php';
?>
<body>
    <?php include '../includes/sidebar_user.php'; ?>
    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>
        <div class="page-header">
            <div>
                <h2>Account Settings</h2>
                <p>Manage your profile and delivery preferences</p>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-user-circle"></i> Profile Information</h4></div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3"><label for="fullName" class="form-label">Full Name</label><input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($user_data['name']); ?>"></div>
                            <div class="mb-3"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user_data['email']); ?>"></div>
                            <div class="mb-3"><label for="phone" class="form-label">Phone Number</label><input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user_data['phone']); ?>"></div>
                            <div class="mb-3"><label for="language" class="form-label">Language</label><select id="language" class="form-select"><option>English</option><option>Spanish</option></select></div>
                            <div class="d-flex justify-content-between"><button type="submit" class="btn btn-primary">Save Profile</button><a href="../logout.php" class="btn btn-outline-secondary">Logout</a></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h4><i class="fas fa-map-marker-alt"></i> Address Settings</h4></div>
                    <div class="card-body">
                        <form>
                            <div class="mb-3"><label for="defaultAddress" class="form-label">Default Shipping Address</label><input type="text" class="form-control" id="defaultAddress" value="<?php echo isset($customer_data['default_address']) ? htmlspecialchars($customer_data['default_address']) : ''; ?>"></div>
                            <div class="mb-3"><label for="billingAddress" class="form-label">Billing Address</label><input type="text" class="form-control" id="billingAddress" value="<?php echo isset($customer_data['billing_address']) ? htmlspecialchars($customer_data['billing_address']) : ''; ?>"></div>
                            <div class="mb-3"><label for="deliveryTime" class="form-label">Preferred Delivery Time</label><select id="deliveryTime" class="form-select"><option>Anytime</option><option>Morning</option><option>Afternoon</option></select></div>
                            <div class="mb-3"><label for="deliveryInstructions" class="form-label">Special Delivery Instructions</label><input type="text" class="form-control" id="deliveryInstructions" value="<?php echo isset($customer_data['package_instructions']) ? htmlspecialchars($customer_data['package_instructions']) : ''; ?>"></div>
                            <button type="submit" class="btn btn-primary w-100">Save Address Settings</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
