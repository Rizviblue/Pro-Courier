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

// --- Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle Add New Customer
    if (isset($_POST['add_customer'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
        $stmt_user->bind_param("ssss", $name, $email, $hashed_password, $phone);
        
        if ($stmt_user->execute()) {
            $user_id = $stmt_user->insert_id;
            $customer_code = 'CUST' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            
            $stmt_customer = $conn->prepare("INSERT INTO customers (user_id, customer_code, name, email, phone, registered_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
            $stmt_customer->bind_param("issss", $user_id, $customer_code, $name, $email, $phone);
            if ($stmt_customer->execute()) {
                $_SESSION['message'] = "New customer added successfully!";
            } else {
                // If customer creation fails, delete the user to avoid orphaned records
                $conn->query("DELETE FROM users WHERE id = $user_id");
                $_SESSION['error'] = "Error creating customer profile: " . $stmt_customer->error;
            }
            $stmt_customer->close();
        } else {
            $_SESSION['error'] = "Error creating user: " . $stmt_user->error;
        }
        $stmt_user->close();
        header("Location: customer_management.php");
        exit;
    }

    // Handle Status Toggle
    if (isset($_POST['toggle_status'])) {
        $customer_id = $_POST['customer_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';

        $stmt = $conn->prepare("UPDATE customers SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $customer_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Customer status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating status: " . $stmt->error;
        }
        $stmt->close();
        header("Location: customer_management.php");
        exit;
    }

    // Handle Delete Customer
    if (isset($_POST['delete_customer'])) {
        $customer_id = $_POST['customer_id'];
        $user_id = $_POST['user_id']; // Passed from the form

        // It's safer to delete from the child table (customers) first, then the parent (users)
        $stmt_cust = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt_cust->bind_param("i", $customer_id);
        
        if ($stmt_cust->execute()) {
            $stmt_user = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $stmt_user->close();
            $_SESSION['message'] = "Customer deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting customer: " . $stmt_cust->error;
        }
        $stmt_cust->close();
        header("Location: customer_management.php");
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

// --- Fetching Statistics ---
$total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
$active_customers = $conn->query("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")->fetch_assoc()['count'];
$inactive_customers = $conn->query("SELECT COUNT(*) as count FROM customers WHERE status = 'inactive'")->fetch_assoc()['count'];
$total_orders_query = $conn->query("SELECT SUM(total_orders) as count FROM customers");
$total_orders = $total_orders_query ? $total_orders_query->fetch_assoc()['count'] : 0;
$total_revenue_query = $conn->query("SELECT SUM(total_spent) as total FROM customers");
$total_revenue = $total_revenue_query ? $total_revenue_query->fetch_assoc()['total'] : 0;

// --- Filtering and Fetching Customers ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT * FROM customers";
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search_term)) {
    $where_clauses[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = &$search_like; $params[] = &$search_like; $params[] = &$search_like;
    $types .= 'sss';
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $where_clauses[] = "status = ?";
    $params[] = &$status_filter;
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
$customers = $result->fetch_all(MYSQLI_ASSOC);
$customer_count = count($customers);


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
                <h2>Customer Management</h2>
                <p>Manage and track your customers</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal"><i class="fas fa-plus"></i> Add New Customer</button>
        </div>

        <?php if ($message): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

        <!-- Stat Cards (match agent management) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total number of customers in the system.">
                    <div class="stat-info">
                        <h3><?php echo $total_customers; ?></h3>
                        <p>Total Customers</p>
                        <span class="stat-sublabel text-success">+5 this month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Customers currently active.">
                    <div class="stat-info">
                        <h3><?php echo $active_customers; ?></h3>
                        <p>Active Customers</p>
                        <span class="stat-sublabel text-primary">+2 this week</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Customers currently inactive.">
                    <div class="stat-info">
                        <h3><?php echo $inactive_customers; ?></h3>
                        <p>Inactive Customers</p>
                        <span class="stat-sublabel text-danger">0 this week</span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Total orders placed by all customers.">
                    <div class="stat-info">
                        <h3><?php echo $total_orders ?? 0; ?></h3>
                        <p>Total Orders</p>
                        <span class="stat-sublabel text-success">+12 this month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters (match agent management) -->
        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-filter"></i> Filters</h4>
            </div>
            <div class="card-body">
                <form action="customer_management.php" method="get">
                    <div class="filters">
                        <div class="search-filter">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, email, or phone..." value="<?php echo htmlspecialchars($search_term); ?>">
                            <div class="search-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        <div class="status-filter d-flex flex-wrap mt-3 gap-2">
                            <button type="submit" name="status" value="all" class="btn btn-outline-primary <?php echo ($status_filter == 'all') ? 'active' : ''; ?>">All</button>
                            <button type="submit" name="status" value="active" class="btn btn-outline-primary <?php echo ($status_filter == 'active') ? 'active' : ''; ?>">Active</button>
                            <button type="submit" name="status" value="inactive" class="btn btn-outline-primary <?php echo ($status_filter == 'inactive') ? 'active' : ''; ?>">Inactive</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Customer List Table (match agent management) -->
        <div class="card">
            <div class="card-header">Customers (<?php echo $customer_count; ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Contact</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Last Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr><td colspan="8" class="text-center">No customers found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><div class="agent-info"><div class="agent-icon"><i class="fas fa-user"></i></div><div><div class="agent-name"><?php echo htmlspecialchars($customer['name']); ?></div><div class="agent-id">ID: <?php echo $customer['id']; ?></div></div></div></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?><br><?php echo htmlspecialchars($customer['phone']); ?></td>
                                    <td><?php echo $customer['total_orders']; ?></td>
                                    <td>$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td>
                                        <?php if ($customer['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('m/d/Y', strtotime($customer['registered_date'])); ?></td>
                                    <td><?php echo date('m/d/Y', strtotime($customer['last_order_date'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <form class="d-inline" action="customer_management.php" method="post" onsubmit="return confirm('Are you sure?');">
                                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $customer['status']; ?>">
                                                <input type="hidden" name="toggle_status" value="1">
                                                <label class="switch mb-0" style="vertical-align: middle;">
                                                    <input type="checkbox" onchange="this.form.submit();" <?php echo ($customer['status'] == 'active') ? 'checked' : ''; ?> >
                                                    <span class="slider round"></span>
                                                </label>
                                            </form>
                                            <form class="d-inline" action="customer_management.php" method="post" onsubmit="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $customer['user_id']; ?>">
                                                <button type="submit" name="delete_customer" class="btn btn-icon text-danger" title="Delete"><i class="fas fa-trash"></i></button>
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

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Add New Customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <form action="customer_management.php" method="post">
                <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <button type="submit" name="add_customer" class="btn btn-primary w-100">Add Customer</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
