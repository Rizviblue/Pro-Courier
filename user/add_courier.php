<?php
session_start();

// Check if the user is logged in and is a user
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'user') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';
$message = '';
$error = '';
$user_id = $_SESSION['user_id'];

// Fetch cities for dropdowns
$cities_result = $conn->query("SELECT name FROM cities ORDER BY name ASC");
$cities = [];
while ($row = $cities_result->fetch_assoc()) {
    $cities[] = $row['name'];
}

// Fetch current user's data to pre-fill sender info
$customer_data = null;
$customer_id = null;
$customer_query = $conn->prepare("SELECT id, name, phone, default_address FROM customers WHERE user_id = ?");
$customer_query->bind_param("i", $user_id);
$customer_query->execute();
$result = $customer_query->get_result();
if ($result->num_rows > 0) {
    $customer_data = $result->fetch_assoc();
    if ($customer_data) {
        $customer_id = $customer_data['id'];
    }
}
$customer_query->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form data
    $sender_name = $_POST['sender_name'];
    $sender_phone = $_POST['sender_phone'];
    $sender_address = $_POST['sender_address'];
    $pickup_city = $_POST['pickup_city'];

    $receiver_name = $_POST['receiver_name'];
    $receiver_phone = $_POST['receiver_phone'];
    $receiver_address = $_POST['receiver_address'];
    $delivery_city = $_POST['delivery_city'];

    $courier_type = $_POST['courier_type'];
    $weight = floatval($_POST['weight']);
    $package_value = isset($_POST['package_value']) ? floatval($_POST['package_value']) : 0.00;
    $delivery_date = $_POST['delivery_date'];
    $notes = $_POST['notes'];

    $created_by = $user_id;

    // Call the stored procedure
    $stmt = $conn->prepare("CALL CreateCourier(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @p_tracking_number, @p_courier_id)");

    $stmt->bind_param(
        "ssssssssssdssii",
        $sender_name, $sender_phone, $sender_address,
        $receiver_name, $receiver_phone, $receiver_address,
        $pickup_city, $delivery_city,
        $courier_type, $weight, $package_value, $delivery_date, $notes, $created_by, $customer_id
    );

    if ($stmt->execute()) {
        $result = $conn->query("SELECT @p_tracking_number AS tracking_number, @p_courier_id AS courier_id");
        $output = $result->fetch_assoc();
        $new_tracking_number = $output['tracking_number'];
        $message = "Courier created successfully! Tracking Number: <strong>" . htmlspecialchars($new_tracking_number) . "</strong>";
    } else {
        $error = "Error creating courier: " . $stmt->error;
    }
    $stmt->close();
}

include '../includes/header.php';
?>

<body>
    <!-- Sidebar -->
    <?php include '../includes/sidebar_user.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <?php include '../includes/top_bar.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2>Add New Courier</h2>
                <p>Create a new courier shipment</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Form (modern, consistent layout) -->
        <form action="add_courier.php" method="post">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header"><h4><i class="fas fa-user"></i> Sender Information</h4></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="senderName" class="form-label">Sender Name *</label>
                                <input type="text" class="form-control" id="senderName" name="sender_name"
                                       value="<?php echo isset($customer_data['name']) ? htmlspecialchars($customer_data['name']) : ''; ?>" 
                                       placeholder="Enter sender's full name" required>
                            </div>
                            <div class="mb-3">
                                <label for="senderPhone" class="form-label">Sender Phone</label>
                                <input type="tel" class="form-control" id="senderPhone" name="sender_phone"
                                       value="<?php echo isset($customer_data['phone']) ? htmlspecialchars($customer_data['phone']) : ''; ?>" 
                                       placeholder="Enter phone number">
                            </div>
                            <div class="mb-3">
                                <label for="senderAddress" class="form-label">Sender Address</label>
                                <textarea class="form-control" id="senderAddress" name="sender_address" rows="3" 
                                          placeholder="Enter complete address"><?php echo isset($customer_data['default_address']) ? htmlspecialchars($customer_data['default_address']) : ''; ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="pickupCity" class="form-label">Pickup City *</label>
                                <select class="form-select" id="pickupCity" name="pickup_city" required>
                                    <option selected disabled value="">Select pickup city</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header"><h4><i class="fas fa-box-open"></i> Receiver Information</h4></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="receiverName" class="form-label">Receiver Name *</label>
                                <input type="text" class="form-control" id="receiverName" name="receiver_name" placeholder="Enter receiver's full name" required>
                            </div>
                            <div class="mb-3">
                                <label for="receiverPhone" class="form-label">Receiver Phone</label>
                                <input type="tel" class="form-control" id="receiverPhone" name="receiver_phone" placeholder="Enter phone number">
                            </div>
                            <div class="mb-3">
                                <label for="receiverAddress" class="form-label">Receiver Address</label>
                                <textarea class="form-control" id="receiverAddress" name="receiver_address" rows="3" placeholder="Enter complete address"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="deliveryCity" class="form-label">Delivery City *</label>
                                <select class="form-select" id="deliveryCity" name="delivery_city" required>
                                    <option selected disabled value="">Select delivery city</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-4 form-modern">
                <div class="card-header"><h4><i class="fas fa-box-tissue"></i> Package Details</h4></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="courierType" class="form-label">Courier Type</label>
                            <select class="form-select" name="courier_type" id="courierType">
                                <option value="standard" selected>Standard</option>
                                <option value="express">Express</option>
                                <option value="overnight">Overnight</option>
                                <option value="same-day">Same-day</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="weight" class="form-label">Weight (kg)</label>
                            <input type="number" step="0.1" class="form-control" name="weight" id="weight" placeholder="0.0">
                        </div>
                        <div class="col-md-2">
                            <label for="packageValue" class="form-label">Package Value ($)</label>
                            <input type="number" step="0.01" class="form-control" name="package_value" id="packageValue" placeholder="0.00">
                        </div>
                        <div class="col-md-2">
                            <label for="deliveryDate" class="form-label">Delivery Date</label>
                            <input type="date" class="form-control" name="delivery_date" id="deliveryDate">
                        </div>
                        <div class="col-md-3">
                            <label for="estimatedFee" class="form-label">Estimated Fee</label>
                            <input type="text" class="form-control" id="estimatedFee" readonly placeholder="Calculated automatically">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional information about the shipment..."></textarea>
                    </div>
                </div>
            </div>
            <div class="form-actions d-flex justify-content-end gap-2">
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Courier</button>
            </div>
        </form>

    </div>

<?php include '../includes/footer.php'; ?>

<script>
    // Function to calculate estimated delivery fee based on courier type
    function calculateEstimatedFee() {
        const courierType = document.getElementById('courierType').value;
        const weight = parseFloat(document.getElementById('weight').value) || 0;
        const estimatedFeeField = document.getElementById('estimatedFee');
        
        let baseFee = 0;
        switch(courierType) {
            case 'standard':
                baseFee = 15.99;
                break;
            case 'express':
                baseFee = 25.99;
                break;
            case 'overnight':
                baseFee = 45.99;
                break;
            case 'same-day':
                baseFee = 65.99;
                break;
            default:
                baseFee = 15.99;
        }
        
        // Add weight fee for packages over 5kg
        let weightFee = 0;
        if (weight > 5.0) {
            weightFee = (weight - 5.0) * 2.50;
        }
        
        const totalFee = baseFee + weightFee;
        estimatedFeeField.value = '$' + totalFee.toFixed(2);
    }
    
    // Add event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const courierTypeSelect = document.getElementById('courierType');
        const weightInput = document.getElementById('weight');
        
        courierTypeSelect.addEventListener('change', calculateEstimatedFee);
        weightInput.addEventListener('input', calculateEstimatedFee);
        
        // Calculate initial fee
        calculateEstimatedFee();
    });
</script>
</body>
</html>
