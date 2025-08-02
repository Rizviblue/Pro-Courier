<?php
session_start();
// Check if the user is logged in and is an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'admin') {
    header("location: ../index.php");
    exit;
}

require_once '../includes/db_connect.php';

$message = '';
$error = '';

// --- Handle Form Submissions (Update & Delete) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Update
    if (isset($_POST['update_courier'])) {
        $courier_id = $_POST['courier_id'];
        $status = $_POST['status'];
        $updated_by = $_SESSION['user_id'];
        $location = "Admin Panel"; // Or get dynamically if needed
        $description = "Status updated to " . ucfirst($status);

        $stmt = $conn->prepare("CALL UpdateCourierStatus(?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $courier_id, $status, $location, $description, $updated_by);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Courier status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating status: " . $stmt->error;
        }
        $stmt->close();
        header("Location: courier_list.php"); // Redirect to avoid form resubmission
        exit;
    }

    // Handle Delete
    if (isset($_POST['delete_courier'])) {
        $courier_id = $_POST['courier_id'];
        $stmt = $conn->prepare("DELETE FROM couriers WHERE id = ?");
        $stmt->bind_param("i", $courier_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Courier deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting courier: " . $stmt->error;
        }
        $stmt->close();
        header("Location: courier_list.php"); // Redirect
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

// --- Filtering Logic ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT * FROM couriers";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $where_clauses[] = "(tracking_number LIKE ? OR sender_name LIKE ? OR receiver_name LIKE ? OR pickup_city LIKE ? OR delivery_city LIKE ?)";
    $search_like = "%" . $search_term . "%";
    // Add 5 search_like params
    array_push($params, $search_like, $search_like, $search_like, $search_like, $search_like);
    $types .= 'sssss';
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_clauses[] = "status = ?";
    array_push($params, $status_filter);
    $types .= 's';
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$couriers = $result->fetch_all(MYSQLI_ASSOC);
$courier_count = count($couriers);

// --- Courier Stats ---
$total_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers")->fetch_assoc()['count'];
$pending_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'pending'")->fetch_assoc()['count'];
$in_transit_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'in_transit'")->fetch_assoc()['count'];
$delivered_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'delivered'")->fetch_assoc()['count'];
$cancelled_couriers = $conn->query("SELECT COUNT(*) as count FROM couriers WHERE status = 'cancelled'")->fetch_assoc()['count'];
// Example sub-labels (replace with real calculations if available)
$pending_change = '+3 this week';
$in_transit_change = '+1 today';
$delivered_change = '+8 this month';
$cancelled_change = '-1 this week';
$total_change = '+12 this month';

include '../includes/header.php';
?>

<body>
    <?php include '../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>

        <div class="page-header">
            <div>
                <h2>Courier List</h2>
                <p>Manage all courier shipments</p>
            </div>
            <a href="add_courier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Courier</a>
        </div>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" style="padding: 1rem; min-height: auto;" title="Total number of couriers in the system.">
                    <div class="stat-info">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.3rem;"><?php echo $total_couriers; ?></h3>
                        <p>Total Couriers</p>
                        <span class="stat-sublabel text-primary" style="font-size: 0.8rem;"><?php echo $total_change; ?></span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary" style="width: 40px; height: 40px; font-size: 1rem;">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="padding: 1rem; min-height: auto;" title="Couriers that are pending pickup or processing.">
                    <div class="stat-info">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.3rem;"><?php echo $pending_couriers; ?></h3>
                        <p>Pending</p>
                        <span class="stat-sublabel text-warning" style="font-size: 0.8rem;"><?php echo $pending_change; ?></span>
                    </div>
                    <div class="stat-icon bg-warning-light text-warning" style="width: 40px; height: 40px; font-size: 1rem;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="padding: 1rem; min-height: auto;" title="Couriers that have been delivered.">
                    <div class="stat-info">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.3rem;"><?php echo $delivered_couriers; ?></h3>
                        <p>Delivered</p>
                        <span class="stat-sublabel text-success" style="font-size: 0.8rem;"><?php echo $delivered_change; ?></span>
                    </div>
                    <div class="stat-icon bg-success-light text-success" style="width: 40px; height: 40px; font-size: 1rem;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="padding: 1rem; min-height: auto;" title="Couriers that have been cancelled.">
                    <div class="stat-info">
                        <h3 style="font-size: 1.5rem; margin-bottom: 0.3rem;"><?php echo $cancelled_couriers; ?></h3>
                        <p>Cancelled</p>
                        <span class="stat-sublabel text-danger" style="font-size: 0.8rem;"><?php echo $cancelled_change; ?></span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger" style="width: 40px; height: 40px; font-size: 1rem;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-filter"></i> Filters</h4>
            </div>
            <div class="card-body">
                <form action="courier_list.php" method="get">
                    <div class="filters">
                        <div class="search-filter">
                            <input type="text" class="form-control" name="search" placeholder="Search by tracking number, sender, receiver, or city..." value="<?php echo htmlspecialchars($search_term); ?>" autocomplete="off">
                            <div class="search-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        <div class="status-filter d-flex flex-wrap mt-3 gap-2">
                            <button type="submit" name="status" value="all" class="btn btn-outline-primary <?php echo ($status_filter == 'all') ? 'active' : ''; ?>">All</button>
                            <button type="submit" name="status" value="pending" class="btn btn-outline-primary <?php echo ($status_filter == 'pending') ? 'active' : ''; ?>">Pending</button>
                            <button type="submit" name="status" value="in_transit" class="btn btn-outline-primary <?php echo ($status_filter == 'in_transit') ? 'active' : ''; ?>">In Transit</button>
                            <button type="submit" name="status" value="delivered" class="btn btn-outline-primary <?php echo ($status_filter == 'delivered') ? 'active' : ''; ?>">Delivered</button>
                            <button type="submit" name="status" value="cancelled" class="btn btn-outline-primary <?php echo ($status_filter == 'cancelled') ? 'active' : ''; ?>">Cancelled</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                Couriers (<?php echo $courier_count; ?>)
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Tracking Number</th>
                                <th>Sender</th>
                                <th>Receiver</th>
                                <th>Route</th>
                                <th>Type</th>
                                <th>Weight</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($couriers)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No couriers found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($couriers as $courier): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($courier['tracking_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($courier['sender_name']); ?></td>
                                    <td><?php echo htmlspecialchars($courier['receiver_name']); ?></td>
                                    <td><?php echo htmlspecialchars($courier['pickup_city']); ?> &rarr; <?php echo htmlspecialchars($courier['delivery_city']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($courier['courier_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($courier['weight']); ?> kg</td>
                                    <td>
                                        <?php
                                            $status = $courier['status'];
                                            $badge_class = '';
                                            switch ($status) {
                                                case 'in_transit': $badge_class = 'bg-primary'; break;
                                                case 'delivered': $badge_class = 'bg-success'; break;
                                                case 'pending': $badge_class = 'bg-warning text-dark'; break;
                                                case 'cancelled': $badge_class = 'bg-danger'; break;
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                                    </td>
                                    <td><?php echo date('m/d/Y', strtotime($courier['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-icon view-btn" title="View"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#courierDetailsModal"
                                                data-courier='<?php echo json_encode($courier, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-icon edit-btn text-primary" title="Edit"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editCourierModal"
                                                data-id="<?php echo $courier['id']; ?>"
                                                data-status="<?php echo $courier['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="courier_list.php" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this courier?');">
                                                <input type="hidden" name="courier_id" value="<?php echo $courier['id']; ?>">
                                                <button type="submit" name="delete_courier" class="btn btn-icon text-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
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

    <div class="modal fade" id="courierDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0"><h5 class="modal-title">Courier Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="row gy-3">
                        <div class="col-6"><strong>Tracking Number</strong><p id="modalTrackingNumber" class="text-muted"></p></div>
                        <div class="col-6"><strong>Status</strong><p id="modalStatus"></p></div>
                        <div class="col-6"><strong>Sender</strong><p id="modalSender" class="text-muted"></p></div>
                        <div class="col-6"><strong>Receiver</strong><p id="modalReceiver" class="text-muted"></p></div>
                        <div class="col-6"><strong>From</strong><p id="modalFrom" class="text-muted"></p></div>
                        <div class="col-6"><strong>To</strong><p id="modalTo" class="text-muted"></p></div>
                        <div class="col-6"><strong>Type</strong><p id="modalType" class="text-muted"></p></div>
                        <div class="col-6"><strong>Weight</strong><p id="modalWeight" class="text-muted"></p></div>
                        <div class="col-6"><strong>Created Date</strong><p id="modalCreatedDate" class="text-muted"></p></div>
                        <div class="col-6"><strong>Delivery Date</strong><p id="modalDeliveryDate" class="text-muted"></p></div>
                </div></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCourierModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Courier Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form action="courier_list.php" method="post">
                        <input type="hidden" id="editCourierId" name="courier_id">
                        <div class="mb-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="pending">Pending</option>
                                <option value="in_transit">In Transit</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" name="update_courier" class="btn btn-primary w-100">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // View Modal
            const viewModal = document.getElementById('courierDetailsModal');
            viewModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const courier = JSON.parse(button.getAttribute('data-courier'));
                
                viewModal.querySelector('#modalTrackingNumber').textContent = courier.tracking_number;
                viewModal.querySelector('#modalSender').textContent = courier.sender_name;
                viewModal.querySelector('#modalReceiver').textContent = courier.receiver_name;
                viewModal.querySelector('#modalFrom').textContent = courier.pickup_city;
                viewModal.querySelector('#modalTo').textContent = courier.delivery_city;
                viewModal.querySelector('#modalType').textContent = courier.courier_type.charAt(0).toUpperCase() + courier.courier_type.slice(1);
                viewModal.querySelector('#modalWeight').textContent = courier.weight + ' kg';
                viewModal.querySelector('#modalCreatedDate').textContent = new Date(courier.created_at).toLocaleDateString();
                viewModal.querySelector('#modalDeliveryDate').textContent = courier.delivery_date ? new Date(courier.delivery_date).toLocaleDateString() : 'N/A';

                const statusBadge = viewModal.querySelector('#modalStatus');
                let badgeClass = '';
                let statusText = courier.status.replace('_', ' ');
                statusText = statusText.charAt(0).toUpperCase() + statusText.slice(1);

                switch (courier.status) {
                    case 'in_transit': badgeClass = 'bg-primary'; break;
                    case 'delivered': badgeClass = 'bg-success'; break;
                    case 'pending': badgeClass = 'bg-warning text-dark'; break;
                    case 'cancelled': badgeClass = 'bg-danger'; break;
                }
                statusBadge.innerHTML = `<span class="badge ${badgeClass}">${statusText}</span>`;
            });

            // Edit Modal
            const editModal = document.getElementById('editCourierModal');
            editModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const courierId = button.getAttribute('data-id');
                const status = button.getAttribute('data-status');

                editModal.querySelector('#editCourierId').value = courierId;
                editModal.querySelector('#editStatus').value = status;
            });
        });
    </script>
</body>
</html>