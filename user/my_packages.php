<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'user') {
    header("location: ../index.php");
    exit;
}
require_once '../includes/db_connect.php';
// Pehle logged-in user ki ID session se lein
$user_id = $_SESSION['user_id'];



// Ensure customer record exists for this user
$customer_id_query = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
$customer_id_query->bind_param("i", $user_id);
$customer_id_query->execute();
$result = $customer_id_query->get_result();
$customer_data = $result->fetch_assoc();
if ($customer_data) {
    $customer_id = $customer_data['id'];
} else {
    // Create a new customer record for this user
    $insert_customer = $conn->prepare("INSERT INTO customers (user_id, name, phone, default_address, registered_date) VALUES (?, ?, '', '', CURDATE())");
    $insert_customer->bind_param("is", $user_id, $_SESSION['user_name']);
    $insert_customer->execute();
    $customer_id = $insert_customer->insert_id;
    $insert_customer->close();
}
$customer_id_query->close();

// Initialize stats and packages array
$stats = ['pending' => 0, 'in_transit' => 0, 'delivered' => 0, 'cancelled' => 0];
$packages = [];
$package_count = 0;

// Proceed only if a valid customer_id was found
if ($customer_id) {
    // Fetch stats (include packages created by user or belonging to customer)
    $stats_query = $conn->query("SELECT status, COUNT(*) as count FROM couriers WHERE customer_id = $customer_id OR created_by = $user_id GROUP BY status");
    if ($stats_query) {
        while($row = $stats_query->fetch_assoc()) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = $row['count'];
            }
        }
    }

    // Fetch all packages for the user (either by customer_id or created_by)
    $packages_query = $conn->prepare("SELECT * FROM couriers WHERE customer_id = ? OR created_by = ? ORDER BY created_at DESC");
    $packages_query->bind_param("ii", $customer_id, $user_id);
    $packages_query->execute();
    $packages_result = $packages_query->get_result();
    
    $packages = $packages_result->fetch_all(MYSQLI_ASSOC);
    $package_count = count($packages);
    

}

// --- FIX ENDS HERE ---


include '../includes/header.php';
?>
<body>
    <?php include '../includes/sidebar_user.php'; ?>
    <div class="main-content">
        <!-- DEBUG TABLE START -->
        <!-- Debug output removed for production -->
        <!-- DEBUG TABLE END -->
        <?php include '../includes/top_bar.php'; ?>
        

        
        <div class="page-header">
            <div>
                <h2>My Packages</h2>
                <p>Track and manage all your shipments</p>
            </div>
        </div>
        <!-- Stat Cards (modern, beautiful) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Pending packages.">
                    <div class="stat-info">
                        <h3><?php echo $stats['pending']; ?></h3>
                        <p>Pending</p>
                        <span class="stat-sublabel text-warning">+1 this week</span>
                    </div>
                    <div class="stat-icon bg-warning-light text-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="In transit packages.">
                    <div class="stat-info">
                        <h3><?php echo $stats['in_transit']; ?></h3>
                        <p>In Transit</p>
                        <span class="stat-sublabel text-primary">+2 this week</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Delivered packages.">
                    <div class="stat-info">
                        <h3><?php echo $stats['delivered']; ?></h3>
                        <p>Delivered</p>
                        <span class="stat-sublabel text-success">+5 this week</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Cancelled packages.">
                    <div class="stat-info">
                        <h3><?php echo $stats['cancelled']; ?></h3>
                        <p>Cancelled</p>
                        <span class="stat-sublabel text-danger">-1 this week</span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Package Table (modern, beautiful) -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4><i class="fas fa-box"></i> My Packages (<?php echo $package_count; ?>)</h4>
                <a href="add_courier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Package</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tracking Number</th>
                                <th>Route</th>
                                <th>Type</th>
                                <th>Weight</th>
                                <th>Package Value</th>
                                <th>Delivery Fee</th>
                                <th>Status</th>
                                <th>Date Shipped</th>
                                <th>Expected Delivery</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($packages)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div style="max-width:320px;margin:0 auto;">
                                            <img src="https://cdn.jsdelivr.net/gh/feathericons/feather@4.28.0/icons/package.svg" alt="No packages" style="width:64px;opacity:0.3;display:block;margin:0 auto 1rem;">
                                            <div style="font-size:1.2rem;color:#888;">You have no packages yet.<br>Click <b>Add Package</b> to create your first shipment!</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($packages as $row): ?>
                                <tr style="transition:background 0.2s;">
                                    <td><strong><?php echo htmlspecialchars($row['tracking_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['pickup_city']); ?> → <?php echo htmlspecialchars($row['delivery_city']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($row['courier_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['weight']); ?> kg</td>
                                    <td>$<?php echo number_format($row['package_value'], 2); ?></td>
                                    <td>$<?php echo number_format($row['delivery_fee'], 2); ?></td>
                                    <td><span class="badge 
                                    <?php 
                                        switch($row['status']) {
                                            case 'in_transit': echo 'bg-primary'; break;
                                            case 'delivered': echo 'bg-success'; break;
                                            case 'pending': echo 'bg-warning text-dark'; break;
                                            case 'cancelled': echo 'bg-danger'; break;
                                        }
                                    ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?></span></td>
                                    <td><?php echo date('m/d/Y', strtotime($row['created_at'])); ?></td>
                                    <td><?php echo date('m/d/Y', strtotime($row['delivery_date'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-icon view-btn" title="View" onclick="viewPackage('<?php echo htmlspecialchars($row['tracking_number']); ?>', '<?php echo htmlspecialchars($row['pickup_city']); ?>', '<?php echo htmlspecialchars($row['delivery_city']); ?>', '<?php echo htmlspecialchars($row['courier_type']); ?>', '<?php echo htmlspecialchars($row['weight']); ?>', '<?php echo htmlspecialchars($row['package_value']); ?>', '<?php echo htmlspecialchars($row['delivery_fee']); ?>', '<?php echo htmlspecialchars($row['status']); ?>', '<?php echo htmlspecialchars($row['sender_name']); ?>', '<?php echo htmlspecialchars($row['sender_phone']); ?>', '<?php echo htmlspecialchars($row['sender_address']); ?>', '<?php echo htmlspecialchars($row['receiver_name']); ?>', '<?php echo htmlspecialchars($row['receiver_phone']); ?>', '<?php echo htmlspecialchars($row['receiver_address']); ?>', '<?php echo htmlspecialchars($row['special_instructions']); ?>', '<?php echo date('m/d/Y', strtotime($row['created_at'])); ?>', '<?php echo date('m/d/Y', strtotime($row['delivery_date'])); ?>')"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Package Details Modal -->
    <div class="modal fade" id="packageDetailsModal" tabindex="-1" aria-labelledby="packageDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="packageDetailsModalLabel">Package Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Package Information</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Tracking Number:</strong></td>
                                    <td id="modal-tracking-number"></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td id="modal-status"></td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td id="modal-type"></td>
                                </tr>
                                <tr>
                                    <td><strong>Weight:</strong></td>
                                    <td id="modal-weight"></td>
                                </tr>
                                <tr>
                                    <td><strong>Package Value:</strong></td>
                                    <td id="modal-package-value"></td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Fee:</strong></td>
                                    <td id="modal-delivery-fee"></td>
                                </tr>
                                <tr>
                                    <td><strong>Route:</strong></td>
                                    <td id="modal-route"></td>
                                </tr>
                                <tr>
                                    <td><strong>Date Shipped:</strong></td>
                                    <td id="modal-date-shipped"></td>
                                </tr>
                                <tr>
                                    <td><strong>Expected Delivery:</strong></td>
                                    <td id="modal-expected-delivery"></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary mb-3">Contact Information</h6>
                            <div class="mb-4">
                                <h6 class="text-success">Sender Details</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="modal-sender-name"></span></p>
                                <p class="mb-1"><strong>Phone:</strong> <span id="modal-sender-phone"></span></p>
                                <p class="mb-1"><strong>Address:</strong> <span id="modal-sender-address"></span></p>
                            </div>
                            <div class="mb-4">
                                <h6 class="text-info">Receiver Details</h6>
                                <p class="mb-1"><strong>Name:</strong> <span id="modal-receiver-name"></span></p>
                                <p class="mb-1"><strong>Phone:</strong> <span id="modal-receiver-phone"></span></p>
                                <p class="mb-1"><strong>Address:</strong> <span id="modal-receiver-address"></span></p>
                            </div>
                            <div class="mb-3">
                                <h6 class="text-warning">Special Instructions</h6>
                                <p class="mb-1" id="modal-special-instructions"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="trackPackage()">Track Package</button>
                </div>
            </div>
        </div>
    </div>
    
    <style>
    .modal-lg {
        max-width: 800px;
    }
    
    .modal-body .table-sm td {
        padding: 0.5rem;
        border: none;
    }
    
    .modal-body .table-sm td:first-child {
        font-weight: 600;
        color: #495057;
        width: 40%;
    }
    
    .modal-body h6 {
        color: #495057;
        font-weight: 600;
        margin-bottom: 1rem;
    }
    
    .modal-body p {
        margin-bottom: 0.5rem;
    }
    
    .modal-body p strong {
        color: #495057;
    }
    
    /* Dark mode support */
    body.dark-mode .modal-body .table-sm td:first-child {
        color: #e2e8f0;
    }
    
    body.dark-mode .modal-body h6 {
        color: #e2e8f0;
    }
    
    body.dark-mode .modal-body p strong {
        color: #e2e8f0;
    }
    </style>
    
    <script>
    function viewPackage(trackingNumber, pickupCity, deliveryCity, courierType, weight, packageValue, deliveryFee, status, senderName, senderPhone, senderAddress, receiverName, receiverPhone, receiverAddress, specialInstructions, dateShipped, expectedDelivery) {
        // Set modal content
        document.getElementById('modal-tracking-number').textContent = trackingNumber;
        document.getElementById('modal-status').innerHTML = getStatusBadge(status);
        document.getElementById('modal-type').textContent = courierType.charAt(0).toUpperCase() + courierType.slice(1);
        document.getElementById('modal-weight').textContent = weight + ' kg';
        document.getElementById('modal-package-value').textContent = '$' + parseFloat(packageValue).toFixed(2);
        document.getElementById('modal-delivery-fee').textContent = '$' + parseFloat(deliveryFee).toFixed(2);
        document.getElementById('modal-route').textContent = pickupCity + ' → ' + deliveryCity;
        document.getElementById('modal-date-shipped').textContent = dateShipped;
        document.getElementById('modal-expected-delivery').textContent = expectedDelivery;
        document.getElementById('modal-sender-name').textContent = senderName;
        document.getElementById('modal-sender-phone').textContent = senderPhone;
        document.getElementById('modal-sender-address').textContent = senderAddress;
        document.getElementById('modal-receiver-name').textContent = receiverName;
        document.getElementById('modal-receiver-phone').textContent = receiverPhone;
        document.getElementById('modal-receiver-address').textContent = receiverAddress;
        document.getElementById('modal-special-instructions').textContent = specialInstructions || 'No special instructions';
        
        // Store tracking number for track button
        window.currentTrackingNumber = trackingNumber;
        
        // Show modal
        var modal = new bootstrap.Modal(document.getElementById('packageDetailsModal'));
        modal.show();
    }
    
    function getStatusBadge(status) {
        let badgeClass = '';
        switch(status) {
            case 'in_transit':
                badgeClass = 'bg-primary';
                break;
            case 'delivered':
                badgeClass = 'bg-success';
                break;
            case 'pending':
                badgeClass = 'bg-warning text-dark';
                break;
            case 'cancelled':
                badgeClass = 'bg-danger';
                break;
        }
        return '<span class="badge ' + badgeClass + '">' + status.replace('_', ' ').charAt(0).toUpperCase() + status.replace('_', ' ').slice(1) + '</span>';
    }
    
    function trackPackage() {
        if (window.currentTrackingNumber) {
            // Close the modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('packageDetailsModal'));
            modal.hide();
            
            // Navigate to dashboard with tracking number
            window.location.href = 'dashboard.php?track=' + window.currentTrackingNumber;
        }
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
