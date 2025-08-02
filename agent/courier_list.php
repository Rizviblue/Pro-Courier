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

// --- Filtering Logic ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Base SQL query for agent's couriers
$sql = "SELECT * FROM couriers WHERE assigned_agent_id = ?";
$params = [$agent_id];
$types = 'i';

// Add search term to the query
if (!empty($search_term)) {
    $sql .= " AND (tracking_number LIKE ? OR sender_name LIKE ? OR receiver_name LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = &$search_like;
    $params[] = &$search_like;
    $params[] = &$search_like;
    $types .= 'sss';
}

// Add status filter to the query
if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = &$status_filter;
    $types .= 's';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

// Bind parameters
$stmt->bind_param($types, ...$params);

$stmt->execute();
$result = $stmt->get_result();
$couriers = $result->fetch_all(MYSQLI_ASSOC);
$courier_count = count($couriers);

include '../includes/header.php';
?>

<body>
    <?php include '../includes/sidebar_agent.php'; ?>

    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>

        <div class="page-header">
            <div>
                <h2>Courier List</h2>
                <p>Manage all your assigned courier shipments</p>
            </div>
            <a href="add_courier.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Courier</a>
        </div>

        <!-- Stat Cards (modern, consistent with admin panel) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total couriers assigned to you.">
                    <div class="stat-info">
                        <h3><?php echo $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id")->fetch_assoc()['count']; ?></h3>
                        <p>My Couriers</p>
                        <span class="stat-sublabel text-success">+3% this week</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Pending couriers.">
                    <div class="stat-info">
                        <h3><?php echo $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'pending'")->fetch_assoc()['count']; ?></h3>
                        <p>Pending</p>
                        <span class="stat-sublabel text-warning">+1 today</span>
                    </div>
                    <div class="stat-icon bg-warning-light text-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="In transit couriers.">
                    <div class="stat-info">
                        <h3><?php echo $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'in_transit'")->fetch_assoc()['count']; ?></h3>
                        <p>In Transit</p>
                        <span class="stat-sublabel text-primary">+2 this week</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Delivered couriers.">
                    <div class="stat-info">
                        <h3><?php echo $conn->query("SELECT COUNT(*) as count FROM couriers WHERE assigned_agent_id = $agent_id AND status = 'delivered'")->fetch_assoc()['count']; ?></h3>
                        <p>Delivered</p>
                        <span class="stat-sublabel text-success">+5 this week</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters (modern, consistent with admin panel) -->
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-filter"></i> Filters</h4>
            </div>
            <div class="card-body">
                <form action="courier_list.php" method="get">
                    <div class="filters">
                        <div class="search-filter">
                            <input type="text" class="form-control" name="search" placeholder="Search by tracking number, sender, receiver..." value="<?php echo htmlspecialchars($search_term); ?>">
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

        <!-- Courier Table (modern, consistent with admin panel) -->
        <div class="card">
            <div class="card-header">
                My Couriers (<?php echo $courier_count; ?>)
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
                                    <td><?php echo htmlspecialchars($courier['pickup_city']); ?> → <?php echo htmlspecialchars($courier['delivery_city']); ?></td>
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
                                                data-courier='<?php echo json_encode($courier); ?>'>
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

<!-- Courier Details Modal -->
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
<!-- Edit Courier Modal -->
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
        viewModal.querySelector('#modalDeliveryDate').textContent = new Date(courier.delivery_date).toLocaleDateString();
        const statusBadge = viewModal.querySelector('#modalStatus');
        let badgeClass = '';
        switch (courier.status) {
            case 'in_transit': badgeClass = 'bg-primary'; break;
            case 'delivered': badgeClass = 'bg-success'; break;
            case 'pending': badgeClass = 'bg-warning text-dark'; break;
            case 'cancelled': badgeClass = 'bg-danger'; break;
        }
        statusBadge.innerHTML = `<span class="badge ${badgeClass}">${courier.status.replace('_', ' ')}</span>`;
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
<?php include '../includes/footer.php'; ?>
