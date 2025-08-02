<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'user') {
    header("location: ../index.php");
    exit;
}
require_once '../includes/db_connect.php';
$user_id = $_SESSION['user_id'];

// Fetch the customer_id based on user_id safely
$customer_id = null; // Default to null
$customer_id_query = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
$customer_id_query->bind_param("i", $user_id);
$customer_id_query->execute();
$result = $customer_id_query->get_result();
if ($result->num_rows > 0) {
    $customer_data = $result->fetch_assoc();
    $customer_id = $customer_data['id'];
}
$customer_id_query->close();

// Debug: Check if customer_id is found
if (!$customer_id) {
    // If no customer record found, try to create one or handle the case
    error_log("No customer record found for user_id: " . $user_id);
}

// Handle AJAX tracking request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'track_package') {
    $tracking_number = trim($_POST['tracking_number']);
    
    if (empty($tracking_number)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a tracking number']);
        exit;
    }
    
    // Search for the package
    $track_query = $conn->prepare("SELECT c.*, cu.name as customer_name FROM couriers c 
                                  LEFT JOIN customers cu ON c.customer_id = cu.id 
                                  WHERE c.tracking_number = ?");
    $track_query->bind_param("s", $tracking_number);
    $track_query->execute();
    $track_result = $track_query->get_result();
    
    if ($track_result->num_rows > 0) {
        $package = $track_result->fetch_assoc();
        
        // Get tracking history
        $history_query = $conn->prepare("SELECT * FROM courier_tracking_history WHERE courier_id = ? ORDER BY created_at DESC");
        $history_query->bind_param("i", $package['id']);
        $history_query->execute();
        $history_result = $history_query->get_result();
        $tracking_history = [];
        while ($history = $history_result->fetch_assoc()) {
            $tracking_history[] = $history;
        }
        
        $response = [
            'success' => true,
            'package' => $package,
            'tracking_history' => $tracking_history
        ];
    } else {
        $response = ['success' => false, 'message' => 'Package not found. Please check the tracking number.'];
    }
    
    echo json_encode($response);
    exit;
}

// Fetch recent packages for the logged-in user
$recent_packages = [];
if ($customer_id) {
    $recent_packages_query = $conn->prepare("SELECT * FROM couriers WHERE customer_id = ? ORDER BY created_at DESC LIMIT 3");
    $recent_packages_query->bind_param("i", $customer_id);
    $recent_packages_query->execute();
    $recent_packages_result = $recent_packages_query->get_result();
    if ($recent_packages_result) {
        $recent_packages = $recent_packages_result->fetch_all(MYSQLI_ASSOC);
    }
    $recent_packages_query->close();
}

include '../includes/header.php';
?>
<body>
    <?php include '../includes/sidebar_user.php'; ?>
    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>
        <div class="page-header">
            <div>
                <h2>Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
            </div>
        </div>
        
        <!-- Track Package Section -->
        <div class="card tracking-card">
            <div class="card-body">
                <h4><i class="fas fa-search"></i> Track a Package</h4>
                <form class="mt-3 tracking-form" id="trackPackageForm">
                    <label for="trackingNumber" class="form-label">Tracking Number</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="trackingNumber" name="tracking_number" placeholder="Enter tracking number (e.g., CMS001234)" value="<?php echo isset($_GET['track']) ? htmlspecialchars($_GET['track']) : ''; ?>" required>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Track</button>
                    </div>
                </form>
                
                <!-- Loading Spinner -->
                <div id="trackingLoader" class="tracking-loader mt-3" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Searching for your package...</p>
                </div>
                
                <!-- Tracking Results -->
                <div id="trackingResults" class="mt-4" style="display: none;">
                    <div class="card package-details-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-box"></i> Package Details</h5>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshTracking()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="printTracking()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body" id="packageDetails">
                            <!-- Package details will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Tracking Timeline -->
                    <div class="card mt-3 package-details-card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-route"></i> Tracking Timeline</h5>
                        </div>
                        <div class="card-body" id="trackingTimeline">
                            <!-- Tracking timeline will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <!-- Error Message -->
                <div id="trackingError" class="alert tracking-error mt-3" style="display: none;">
                    <!-- Error messages will be displayed here -->
                </div>
            </div>
        </div>
        
        <!-- Recent Packages Section -->
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-history"></i> My Recent Packages</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_packages)): ?>
                    <?php foreach ($recent_packages as $row): ?>
                    <div class="package-item">
                        <div class="package-details">
                            <p class="package-id"><?php echo htmlspecialchars($row['tracking_number']); ?> 
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
                            <p class="package-info"><?php echo htmlspecialchars($row['pickup_city']); ?> &rarr; <?php echo htmlspecialchars($row['delivery_city']); ?> <br> Type: <?php echo ucfirst($row['courier_type']); ?> • Weight: <?php echo $row['weight']; ?> kg</p>
                        </div>
                        <div class="package-location">
                            <p><?php echo htmlspecialchars($row['delivery_city']); ?></p>
                            <p class="date"><?php echo date('m/d/Y', strtotime($row['created_at'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center">
                        No recent packages found.
                        <?php if (!$customer_id): ?>
                            <br><small class="text-muted">(Customer ID not found for user ID: <?php echo $user_id; ?>)</small>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Print Modal -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Print Tracking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="printContent">
                    <!-- Print content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        let currentTrackingNumber = '';
        
        // Handle form submission
        document.getElementById('trackPackageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const trackingNumber = document.getElementById('trackingNumber').value.trim();
            
            if (!trackingNumber) {
                showError('Please enter a tracking number');
                return;
            }
            
            currentTrackingNumber = trackingNumber;
            trackPackage(trackingNumber);
        });
        
        // Track package function
        function trackPackage(trackingNumber) {
            // Show loader
            document.getElementById('trackingLoader').style.display = 'block';
            document.getElementById('trackingResults').style.display = 'none';
            document.getElementById('trackingError').style.display = 'none';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'track_package');
            formData.append('tracking_number', trackingNumber);
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('trackingLoader').style.display = 'none';
                
                if (data.success) {
                    displayTrackingResults(data.package, data.tracking_history);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                document.getElementById('trackingLoader').style.display = 'none';
                showError('An error occurred while tracking the package. Please try again.');
                console.error('Error:', error);
            });
        }
        
        // Display tracking results
        function displayTrackingResults(package, trackingHistory) {
            const packageDetails = document.getElementById('packageDetails');
            const trackingTimeline = document.getElementById('trackingTimeline');
            
            // Package details HTML
            packageDetails.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Package Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>Tracking Number:</strong></td><td>${package.tracking_number}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge ${getStatusBadgeClass(package.status)}">${formatStatus(package.status)}</span></td></tr>
                            <tr><td><strong>Type:</strong></td><td>${package.courier_type.charAt(0).toUpperCase() + package.courier_type.slice(1)}</td></tr>
                            <tr><td><strong>Weight:</strong></td><td>${package.weight} kg</td></tr>
                            <tr><td><strong>Package Value:</strong></td><td>$${parseFloat(package.package_value).toFixed(2)}</td></tr>
                            <tr><td><strong>Delivery Fee:</strong></td><td>$${parseFloat(package.delivery_fee).toFixed(2)}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Route Information</h6>
                        <table class="table table-sm">
                            <tr><td><strong>From:</strong></td><td>${package.pickup_city}</td></tr>
                            <tr><td><strong>To:</strong></td><td>${package.delivery_city}</td></tr>
                            <tr><td><strong>Created:</strong></td><td>${formatDate(package.created_at)}</td></tr>
                            <tr><td><strong>Expected Delivery:</strong></td><td>${formatDate(package.delivery_date)}</td></tr>
                        </table>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Contact Information</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Sender:</strong><br>
                                ${package.sender_name}<br>
                                ${package.sender_phone}<br>
                                ${package.sender_address}
                            </div>
                            <div class="col-md-6">
                                <strong>Receiver:</strong><br>
                                ${package.receiver_name}<br>
                                ${package.receiver_phone}<br>
                                ${package.receiver_address}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Tracking timeline HTML
            if (trackingHistory.length > 0) {
                let timelineHTML = '<div class="timeline">';
                trackingHistory.forEach((event, index) => {
                    timelineHTML += `
                        <div class="timeline-item ${index === 0 ? 'active' : ''}">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <h6>${event.status}</h6>
                                <p>${event.description}</p>
                                <small class="text-muted">${formatDate(event.created_at)}</small>
                            </div>
                        </div>
                    `;
                });
                timelineHTML += '</div>';
                trackingTimeline.innerHTML = timelineHTML;
            } else {
                trackingTimeline.innerHTML = '<p class="text-muted">No tracking updates available yet.</p>';
            }
            
            // Show results
            document.getElementById('trackingResults').style.display = 'block';
        }
        
        // Show error message
        function showError(message) {
            const errorDiv = document.getElementById('trackingError');
            errorDiv.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${message}`;
            errorDiv.style.display = 'block';
        }
        
        // Refresh tracking
        function refreshTracking() {
            if (currentTrackingNumber) {
                trackPackage(currentTrackingNumber);
            }
        }
        
        // Print tracking details
        function printTracking() {
            const printContent = document.getElementById('printContent');
            const packageDetails = document.getElementById('packageDetails').innerHTML;
            const trackingTimeline = document.getElementById('trackingTimeline').innerHTML;
            
            printContent.innerHTML = `
                <div class="print-header">
                    <h3>Package Tracking Details</h3>
                    <p><strong>Tracking Number:</strong> ${currentTrackingNumber}</p>
                    <p><strong>Printed on:</strong> ${new Date().toLocaleString()}</p>
                </div>
                <hr>
                <div class="print-package-details">
                    ${packageDetails}
                </div>
                <hr>
                <div class="print-tracking-timeline">
                    <h5>Tracking Timeline</h5>
                    ${trackingTimeline}
                </div>
            `;
            
            const printModal = new bootstrap.Modal(document.getElementById('printModal'));
            printModal.show();
        }
        
        // Helper functions
        function getStatusBadgeClass(status) {
            switch(status) {
                case 'in_transit': return 'bg-primary';
                case 'delivered': return 'bg-success';
                case 'pending': return 'bg-warning text-dark';
                case 'cancelled': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }
        
        function formatStatus(status) {
            return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
        }
        
        function formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Auto-track package if tracking number is provided in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const trackParam = urlParams.get('track');
            
            if (trackParam) {
                document.getElementById('trackingNumber').value = trackParam;
                trackPackage(trackParam);
            }
        });
    </script>
    
    <style>
        /* Timeline Styles */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
        }
        
        .timeline-item.active .timeline-marker {
            background: #007bff;
            border-color: #007bff;
        }
        
        .timeline-marker {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #dee2e6;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #dee2e6;
        }
        
        .timeline-item.active .timeline-content {
            border-left-color: #007bff;
            background: #e3f2fd;
        }
        
        /* Enhanced Tracking Styles */
        .tracking-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .tracking-card .card-header {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 1.5rem;
        }
        
        .tracking-card .card-body {
            background: rgba(255,255,255,0.95);
            padding: 2rem;
        }
        
        .tracking-form {
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
        }
        
        .tracking-form .form-control {
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .tracking-form .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .tracking-form .btn {
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .tracking-form .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* Package Details Styling */
        .package-details-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .package-details-card .card-header {
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
        }
        
        .package-details-card .card-body {
            background: rgba(255,255,255,0.95);
        }
        
        /* Timeline Enhancement */
        .timeline-item {
            transition: all 0.3s ease;
        }
        
        .timeline-item:hover {
            transform: translateX(5px);
        }
        
        .timeline-content h6 {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .timeline-content p {
            color: #4a5568;
            margin-bottom: 0.5rem;
        }
        
        .timeline-content small {
            color: #718096;
            font-size: 0.875rem;
        }
        
        /* Loading Animation */
        .tracking-loader {
            background: rgba(255,255,255,0.9);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
        }
        
        .tracking-loader .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Error Styling */
        .tracking-error {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            border: none;
            border-radius: 12px;
            color: white;
        }
        
        /* Print Styles */
        @media print {
            .modal-header, .modal-footer, .btn {
                display: none !important;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .print-package-details, .print-tracking-timeline {
                margin-bottom: 20px;
            }
            
            .tracking-card, .package-details-card {
                background: white !important;
                box-shadow: none !important;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .tracking-card .card-body {
                padding: 1rem;
            }
            
            .timeline {
                padding-left: 20px;
            }
            
            .timeline-marker {
                left: -12px;
                width: 8px;
                height: 8px;
            }
        }
    </style>
</body>
</html>
