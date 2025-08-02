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
    // Handle Add New Agent
    if (isset($_POST['add_agent'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $password = trim($_POST['password']);
        $city_id = $_POST['city_id'];
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt_user = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status) VALUES (?, ?, ?, ?, 'agent', 'active')");
        $stmt_user->bind_param("ssss", $name, $email, $hashed_password, $phone);
        
        if ($stmt_user->execute()) {
            $user_id = $stmt_user->insert_id;
            $agent_code = 'AGT' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
            
            $stmt_agent = $conn->prepare("INSERT INTO agents (user_id, agent_code, city_id, joined_date) VALUES (?, ?, ?, CURDATE())");
            $stmt_agent->bind_param("isi", $user_id, $agent_code, $city_id);
            if ($stmt_agent->execute()) {
                $_SESSION['message'] = "New agent added successfully!";
            } else {
                $_SESSION['error'] = "Error creating agent profile: " . $stmt_agent->error;
            }
            $stmt_agent->close();
        } else {
            $_SESSION['error'] = "Error creating user: " . $stmt_user->error;
        }
        $stmt_user->close();
        header("Location: agent_management.php");
        exit;
    }

    // Handle Edit Agent
    if (isset($_POST['edit_agent'])) {
        $user_id = $_POST['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $city_id = $_POST['city_id'];

        $stmt = $conn->prepare("UPDATE users u JOIN agents a ON u.id = a.user_id SET u.name = ?, u.email = ?, u.phone = ?, a.city_id = ? WHERE u.id = ?");
        $stmt->bind_param("sssii", $name, $email, $phone, $city_id, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Agent details updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating agent: " . $stmt->error;
        }
        $stmt->close();
        header("Location: agent_management.php");
        exit;
    }

    // Handle Status Toggle
    if (isset($_POST['toggle_status'])) {
        $user_id = $_POST['user_id'];
        $current_status = $_POST['current_status'];
        $new_status = ($current_status == 'active') ? 'inactive' : 'active';

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'agent'");
        $stmt->bind_param("si", $new_status, $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Agent status updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating status: " . $stmt->error;
        }
        $stmt->close();
        header("Location: agent_management.php");
        exit;
    }

    // Handle Delete Agent
    if (isset($_POST['delete_agent'])) {
        $user_id = $_POST['user_id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'agent'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Agent deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting agent: " . $stmt->error;
        }
        $stmt->close();
        header("Location: agent_management.php");
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
$total_agents = $conn->query("SELECT COUNT(*) as count FROM agents")->fetch_assoc()['count'];
$active_agents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND status = 'active'")->fetch_assoc()['count'];
$inactive_agents = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'agent' AND status = 'inactive'")->fetch_assoc()['count'];
$total_couriers_managed_query = $conn->query("SELECT SUM(total_couriers) as total FROM agents");
$total_couriers_managed = $total_couriers_managed_query ? $total_couriers_managed_query->fetch_assoc()['total'] : 0;
// Example sub-labels (replace with real calculations if available)
$total_agents_change = '+2 this month';
$active_agents_change = '+1 this week';
$inactive_agents_change = '0 this week';
$total_couriers_managed_change = '+10 this month';
$cities_result = $conn->query("SELECT id, name FROM cities ORDER BY name ASC");

// --- Filtering and Fetching Agents ---
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT u.id as user_id, u.name, u.email, u.phone, u.status, a.id as agent_id, a.agent_code, a.city_id, a.joined_date, a.total_couriers, c.name as city_name 
        FROM users u 
        JOIN agents a ON u.id = a.user_id 
        LEFT JOIN cities c ON a.city_id = c.id 
        WHERE u.role = 'agent'";

$params = [];
$types = '';

if (!empty($search_term)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR c.name LIKE ?)";
    $search_like = "%" . $search_term . "%";
    $params[] = &$search_like; $params[] = &$search_like; $params[] = &$search_like;
    $types .= 'sss';
}

if ($status_filter !== 'all' && !empty($status_filter)) {
    $sql .= " AND u.status = ?";
    $params[] = &$status_filter;
    $types .= 's';
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$agents = $result->fetch_all(MYSQLI_ASSOC);
$agent_count = count($agents);


include '../includes/header.php';
?>

<body>
    <?php include '../includes/sidebar_admin.php'; ?>

    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>

        <div class="page-header">
            <div>
                <h2>Agent Management</h2>
                <p>Manage and monitor your delivery agents</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAgentModal"><i class="fas fa-plus"></i> Add New Agent</button>
        </div>

        <!-- Stat Cards (match courier list) -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card" title="Total number of agents in the system.">
                    <div class="stat-info">
                        <h3><?php echo $total_agents; ?></h3>
                        <p>Total Agents</p>
                        <span class="stat-sublabel text-success">+2 this month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Agents currently active.">
                    <div class="stat-info">
                        <h3><?php echo $active_agents; ?></h3>
                        <p>Active Agents</p>
                        <span class="stat-sublabel text-primary">+1 this week</span>
                    </div>
                    <div class="stat-icon bg-success-light text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Agents currently inactive.">
                    <div class="stat-info">
                        <h3><?php echo $inactive_agents; ?></h3>
                        <p>Inactive Agents</p>
                        <span class="stat-sublabel text-danger">0 this week</span>
                    </div>
                    <div class="stat-icon bg-danger-light text-danger">
                        <i class="fas fa-user-times"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" title="Total couriers managed by all agents.">
                    <div class="stat-info">
                        <h3><?php echo $total_couriers_managed ?? 0; ?></h3>
                        <p>Couriers Managed</p>
                        <span class="stat-sublabel text-success">+10 this month</span>
                    </div>
                    <div class="stat-icon bg-primary-light text-primary">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h4><i class="fas fa-filter"></i> Filters</h4>
            </div>
            <div class="card-body">
                <form action="agent_management.php" method="get">
                    <div class="filters">
                        <div class="search-filter">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, email, or city..." value="<?php echo htmlspecialchars($search_term); ?>">
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

        <div class="card">
            <div class="card-header">Agents (<?php echo $agent_count; ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Agent</th>
                                <th>Contact</th>
                                <th>City</th>
                                <th>Status</th>
                                <th>Couriers</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($agents)): ?>
                                <tr><td colspan="7" class="text-center">No agents found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($agents as $agent): ?>
                                <tr>
                                    <td><div class="agent-info"><div class="agent-icon"><i class="fas fa-user"></i></div><div><div class="agent-name"><?php echo htmlspecialchars($agent['name']); ?></div><div class="agent-id">ID: <?php echo $agent['agent_code']; ?></div></div></div></td>
                                    <td><?php echo htmlspecialchars($agent['email']); ?><br><?php echo htmlspecialchars($agent['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($agent['city_name']); ?></td>
                                    <td>
                                        <?php if ($agent['status'] == 'active'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $agent['total_couriers'] ?? 0; ?></td>
                                    <td><?php echo date('m/d/Y', strtotime($agent['joined_date'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <button class="btn btn-icon view-btn" data-bs-toggle="modal" data-bs-target="#viewAgentModal" data-agent='<?php echo json_encode($agent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>' title="View"><i class="fas fa-eye"></i></button>
                                            <button class="btn btn-icon edit-btn text-primary" data-bs-toggle="modal" data-bs-target="#editAgentModal" data-agent='<?php echo json_encode($agent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>' title="Edit"><i class="fas fa-edit"></i></button>
                                            <form class="d-inline" action="agent_management.php" method="post">
                                                <input type="hidden" name="user_id" value="<?php echo $agent['user_id']; ?>">
                                                <input type="hidden" name="current_status" value="<?php echo $agent['status']; ?>">
                                                <input type="hidden" name="toggle_status" value="1">
                                                <label class="switch mb-0" style="vertical-align: middle;">
                                                    <input type="checkbox" onchange="this.form.submit();" <?php echo ($agent['status'] == 'active') ? 'checked' : ''; ?>>
                                                    <span class="slider round"></span>
                                                </label>
                                            </form>
                                            <form class="d-inline" action="agent_management.php" method="post" onsubmit="return confirm('Are you sure you want to delete this agent?');">
                                                <input type="hidden" name="user_id" value="<?php echo $agent['user_id']; ?>">
                                                <button type="submit" name="delete_agent" class="btn btn-icon text-danger" title="Delete"><i class="fas fa-trash"></i></button>
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

    <div class="modal fade" id="addAgentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Add New Agent</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form action="agent_management.php" method="post">
                        <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">City</label><select name="city_id" class="form-select" required><option value="">Select City</option><?php mysqli_data_seek($cities_result, 0); while($city = $cities_result->fetch_assoc()){ echo "<option value='{$city['id']}'>".htmlspecialchars($city['name'])."</option>"; } ?></select></div>
                        <button type="submit" name="add_agent" class="btn btn-primary w-100">Add Agent</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editAgentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit Agent</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <form action="agent_management.php" method="post">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" id="editName" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="editEmail" class="form-control" required></div>
                        <div class="mb-3"><label class="form-label">Phone</label><input type="tel" name="phone" id="editPhone" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">City</label><select name="city_id" id="editCity" class="form-select" required><option value="">Select City</option><?php mysqli_data_seek($cities_result, 0); while($city = $cities_result->fetch_assoc()){ echo "<option value='{$city['id']}'>".htmlspecialchars($city['name'])."</option>"; } ?></select></div>
                        <button type="submit" name="edit_agent" class="btn btn-primary w-100">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="viewAgentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Agent Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                     <div class="row gy-3">
                        <div class="col-6"><strong>Name</strong><p id="viewName" class="text-muted mb-0"></p></div>
                        <div class="col-6"><strong>ID</strong><p id="viewId" class="text-muted mb-0"></p></div>
                        <div class="col-6"><strong>Email</strong><p id="viewEmail" class="text-muted mb-0"></p></div>
                        <div class="col-6"><strong>Phone</strong><p id="viewPhone" class="text-muted mb-0"></p></div>
                        <div class="col-6"><strong>City</strong><p id="viewCity" class="text-muted mb-0"></p></div>
                        <div class="col-6"><strong>Status</strong><p id="viewStatus" class="mb-0"></p></div>
                        <div class="col-6"><strong>Couriers</strong><p id="viewCouriers" class="text-muted mb-0"></p></div>
                        <div class="col-6"><strong>Joined</strong><p id="viewJoined" class="text-muted mb-0"></p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editAgentModal = document.getElementById('editAgentModal');
            editAgentModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const agent = JSON.parse(button.getAttribute('data-agent'));
                
                editAgentModal.querySelector('#editUserId').value = agent.user_id;
                editAgentModal.querySelector('#editName').value = agent.name;
                editAgentModal.querySelector('#editEmail').value = agent.email;
                editAgentModal.querySelector('#editPhone').value = agent.phone;
                editAgentModal.querySelector('#editCity').value = agent.city_id;
            });

            const viewAgentModal = document.getElementById('viewAgentModal');
            viewAgentModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const agent = JSON.parse(button.getAttribute('data-agent'));
                
                viewAgentModal.querySelector('#viewName').textContent = agent.name;
                viewAgentModal.querySelector('#viewId').textContent = 'ID: ' + agent.agent_code;
                viewAgentModal.querySelector('#viewEmail').textContent = agent.email;
                viewAgentModal.querySelector('#viewPhone').textContent = agent.phone;
                viewAgentModal.querySelector('#viewCity').textContent = agent.city_name;
                viewAgentModal.querySelector('#viewCouriers').textContent = agent.total_couriers;
                viewAgentModal.querySelector('#viewJoined').textContent = new Date(agent.joined_date).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });
                
                const statusBadge = viewAgentModal.querySelector('#viewStatus');
                if (agent.status === 'active') {
                    statusBadge.innerHTML = `<span class="badge bg-success">Active</span>`;
                } else {
                    statusBadge.innerHTML = `<span class="badge bg-danger">Inactive</span>`;
                }
            });
        });
    </script>
</body>
</html>