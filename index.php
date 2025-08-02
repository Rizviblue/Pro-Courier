<?php
// --- Error Reporting for Debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'includes/db_connect.php';

$login_error = '';
$register_success = '';
$register_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['login'])) {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (!empty($email) && !empty($password)) {
            $stmt = $conn->prepare("SELECT id, name, password, role, email FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $_SESSION['loggedin'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['login_success_message'] = "Logged in as " . ucfirst($user['role']);

                    switch ($user['role']) {
                        case 'admin': header("location: admin/dashboard.php"); break;
                        case 'agent': header("location: agent/dashboard.php"); break;
                        case 'user': header("location: user/dashboard.php"); break;
                        default: $login_error = "Invalid user role.";
                    }
                    exit;
                } else {
                    $login_error = "Invalid email or password.";
                }
            } else {
                $login_error = "Invalid email or password.";
            }
            $stmt->close();
        } else {
            $login_error = "Please enter both email and password.";
        }
    }
    elseif (isset($_POST['register'])) {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $role = $_POST['role'];
        $password = trim($_POST['password']);
        $confirm_password = trim($_POST['confirm_password']);

        if ($password !== $confirm_password) {
            $register_error = "Passwords do not match.";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $register_error = "An account with this email already exists.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $fullname, $email, $phone, $hashed_password, $role);

                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    if ($role === 'agent') {
                        $agent_code = 'AGT' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                        $stmt_agent = $conn->prepare("INSERT INTO agents (user_id, agent_code, joined_date) VALUES (?, ?, CURDATE())");
                        $stmt_agent->bind_param("is", $user_id, $agent_code);
                        $stmt_agent->execute();
                        $stmt_agent->close();
                    } elseif ($role === 'user') {
                        $customer_code = 'CUST' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
                        $stmt_customer = $conn->prepare("INSERT INTO customers (user_id, customer_code, name, email, phone, registered_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
                        $stmt_customer->bind_param("issss", $user_id, $customer_code, $fullname, $email, $phone);
                        $stmt_customer->execute();
                        $stmt_customer->close();
                    }
                    $register_success = "Registration successful! You can now log in.";
                } else {
                    $register_error = "Something went wrong. Please try again.";
                }
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CourierPro - Modern Courier Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .navbar { padding: 1.5rem 0; background-color: #fff; box-shadow: 0 2px 8px rgba(60,60,60,0.03); }
        .navbar-brand { font-size: 1.5rem; }
        .hero-section { padding: 7rem 0 5rem 0; text-align: center; background: linear-gradient(120deg, #f8fbff 60%, #eaf1fb 100%); }
        .hero-section h1 { font-size: 3.8rem; font-weight: 800; letter-spacing: -1px; }
        .hero-section p { font-size: 1.3rem; color: #6c757d; max-width: 650px; margin: 1.2rem auto 2.5rem; }
        .feature-card { background-color: #fff; border: none; border-radius: 1.25rem; padding: 2.2rem 1.5rem; text-align: center; margin-bottom: 2rem; height: 100%; box-shadow: 0 4px 24px rgba(60,60,60,0.07); transition: box-shadow 0.2s; }
        .feature-card:hover { box-shadow: 0 8px 32px rgba(13,110,253,0.10); }
        .feature-card .icon { font-size: 2.2rem; color: #0d6efd; margin-bottom: 1.2rem; }
        .about-section { padding: 4.5rem 0; }
        .key-benefits { background-color: #f0f5ff; border-radius: 1.25rem; padding: 2.2rem; }
        .key-benefits ul { list-style: none; padding: 0; }
        .key-benefits ul li { margin-bottom: 1.1rem; }
        .key-benefits ul li i { color: #198754; margin-right: 0.5rem; }
        .cta-section { background: linear-gradient(120deg, #f8fbff 60%, #eaf1fb 100%); padding: 5rem 0 4rem 0; text-align: center; }
        .modal-content { border-radius: 1.25rem; box-shadow: 0 8px 32px rgba(60,60,60,0.12); border: none; background: #fff; }
        .modal-header { border: none; background: transparent; }
        .nav-tabs { border: none; }
        .nav-tabs .nav-link { color: #b0b0b0; border: none; background: none; font-weight: 500; font-size: 1.1rem; }
        .nav-tabs .nav-link.active { color: #222; border-bottom: 2.5px solid #0d6efd; background: none; font-weight: 700; }
        .modal-body { padding: 2.5rem 2rem 2rem 2rem; }
        .form-label { color: #888; font-weight: 500; margin-bottom: 0.15rem; font-size: 0.98rem; letter-spacing: 0.01em; }
        .form-control {
            border: 1.5px solid #e0e0e0;
            border-radius: 0.5rem;
            background: #fff;
            font-size: 1.05rem;
            padding: 0.7rem 1rem;
            margin-bottom: 1.2rem;
            box-shadow: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: #0d6efd;
            background: #fff;
            box-shadow: 0 2px 8px rgba(13,110,253,0.04);
        }
        .form-control::placeholder {
            color: #b0b0b0;
            opacity: 1;
            font-size: 0.98rem;
        }
        .btn-primary, .btn-primary:focus {
            background: #0d6efd;
            border: none;
            border-radius: 2rem;
            font-weight: 700;
            font-size: 1.13rem;
            padding: 0.95rem 2.2rem;
            box-shadow: 0 2px 8px rgba(13,110,253,0.08);
            transition: background 0.2s, box-shadow 0.2s;
            letter-spacing: 0.01em;
        }
        .btn-primary:hover {
            background: #0b5ed7;
            box-shadow: 0 4px 16px rgba(13,110,253,0.13);
        }
        .btn-outline-secondary, .btn-outline-secondary:focus {
            border: 1.5px solid #b0b0b0;
            color: #222;
            background: #fff;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 1.13rem;
            padding: 0.95rem 2.2rem;
            transition: border-color 0.2s, color 0.2s, background 0.2s;
        }
        .btn-outline-secondary:hover {
            border-color: #0d6efd;
            color: #0d6efd;
            background: #f8fbff;
        }
        .btn-close { filter: grayscale(1); }
        .alert { border-radius: 0.75rem; font-size: 0.98rem; }
        .small, .form-text { color: #b0b0b0; }
        .text-end { text-align: right; }
        .modal-title { font-weight: 700; color: #222; letter-spacing: -0.5px; }
        .w-100 { width: 100%; }
        .py-2 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
        .mb-3 { margin-bottom: 1.5rem !important; }
        .mb-2 { margin-bottom: 1rem !important; }
        .position-relative { position: relative; }
        .link-primary { color: #0d6efd !important; text-decoration: none; }
        .link-primary:hover { text-decoration: underline; }
        .container { padding-left: 1.5rem; padding-right: 1.5rem; }
        section { margin-bottom: 3.5rem; }
        h2, .fw-bold { font-weight: 800 !important; letter-spacing: -0.5px; }
        h2 { font-size: 2.3rem; }
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 2.1rem; }
            .feature-card { padding: 1.2rem 0.7rem; }
            .about-section { padding: 2.5rem 0; }
            .cta-section { padding: 2.5rem 0; }
            .feature-card { margin-bottom: 1.5rem; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#"><i class="fas fa-box me-2"></i>CourierPro</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginRegisterModal">Login / Sign Up</button>
        </div>
    </nav>
    <section class="hero-section">
        <div class="container">
            <h1>Modern Courier Management System</h1>
            <p>Streamline your courier operations with our comprehensive platform. Manage packages, track deliveries, and optimize routes with real-time analytics.</p>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginRegisterModal">Get Started</button>
        </div>
    </section>
    <!-- Other sections (Features, About, CTA, Footer) remain the same -->
    <section class="container my-5">
        <h2 class="text-center mb-5 fw-bold">Powerful Features</h2>
        <div class="row g-4">
            <div class="col-md-4"><div class="feature-card"><div class="icon"><i class="fas fa-box-open"></i></div><h5>Package Management</h5><p>Complete tracking and management of packages from pickup to delivery.</p></div></div>
            <div class="col-md-4"><div class="feature-card"><div class="icon"><i class="fas fa-route"></i></div><h5>Courier Operations</h5><p>Streamlined courier assignment and route optimization.</p></div></div>
            <div class="col-md-4"><div class="feature-card"><div class="icon"><i class="fas fa-users-cog"></i></div><h5>Multi-Role System</h5><p>Admin, Agent, and User roles with specific permissions and dashboards.</p></div></div>
            <div class="col-md-4"><div class="feature-card"><div class="icon"><i class="fas fa-chart-pie"></i></div><h5>Analytics & Reports</h5><p>Comprehensive reporting and analytics for business insights.</p></div></div>
            <div class="col-md-4"><div class="feature-card"><div class="icon"><i class="fas fa-shield-alt"></i></div><h5>Secure Platform</h5><p>Role-based access control and secure data management.</p></div></div>
            <div class="col-md-4"><div class="feature-card"><div class="icon"><i class="fas fa-clock"></i></div><h5>Real-time Tracking</h5><p>Live updates on package status and delivery progress.</p></div></div>
        </div>
    </section>
    <section class="about-section bg-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold">About CourierPro</h2>
                    <p>CourierPro is a comprehensive courier management system designed to streamline your logistics. Our platform offers robust features for efficient package tracking, courier management, and customer service.</p>
                    <ul><li>Wide role-based access control, real-time analytics, and intuitive interfaces.</li><li>Admin dashboard for complete system oversight.</li><li>Agent tools for efficient package processing.</li><li>User portal for package tracking and management.</li><li>Real-time notifications and updates.</li><li>Comprehensive reporting and analytics.</li></ul>
                </div>
                <div class="col-lg-6"><div class="key-benefits"><h4 class="fw-bold">Key Benefits</h4><ul><li><i class="fas fa-check-circle"></i> <strong>Increased Efficiency:</strong> Reduce processing time by up to 40%.</li><li><i class="fas fa-check-circle"></i> <strong>Better Analytics:</strong> Make data-driven decisions.</li><li><i class="fas fa-check-circle"></i> <strong>Improved Customer Service:</strong> Enhanced tracking and communication.</li></ul></div></div>
            </div>
        </div>
    </section>
    <section class="cta-section">
        <div class="container">
            <h2 class="fw-bold">Ready to Transform Your Courier Operations?</h2>
            <p>Join thousands of businesses using CourierPro to streamline their delivery operations.</p>
            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginRegisterModal">Start Your Trial</button>
            <a href="contact.php" class="btn btn-outline-secondary btn-lg">Contact Support</a>
        </div>
    </section>
    <footer class="text-center py-4"><p>&copy; 2024 CourierPro. All rights reserved.</p></footer>

    <!-- Login/Register Modal -->
    <div class="modal fade" id="loginRegisterModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <ul class="nav nav-tabs w-100" id="myTab" role="tablist">
                        <li class="nav-item flex-fill text-center" role="presentation"><button class="nav-link active w-100" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-panel" type="button">Sign In</button></li>
                        <li class="nav-item flex-fill text-center" role="presentation"><button class="nav-link w-100" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-panel" type="button">Create Account</button></li>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="tab-content" id="myTabContent">
                        <!-- Login Panel -->
                        <div class="tab-pane fade show active" id="login-panel">
                            <?php if (!empty($login_error)): ?><div class="alert alert-danger mb-3"><?php echo $login_error; ?></div><?php endif; ?>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required placeholder="Enter your email">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" id="loginPassword" required placeholder="Enter your password">
                                <div class="mb-2 d-flex justify-content-between align-items-center" style="gap: 0.5rem;">
                                    <a href="#" class="link-primary small" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" data-bs-dismiss="modal">Forgot password?</a>
                                    <a href="contact.php" class="link-primary small" target="_blank" rel="noopener">Contact Support</a>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100 py-2">Sign In</button>
                            </form>
                        </div>
                        <!-- Register Panel -->
                        <div class="tab-pane fade" id="register-panel">
                             <?php if (!empty($register_error)): ?><div class="alert alert-danger mb-3"><?php echo $register_error; ?></div><?php endif; ?>
                             <?php if (!empty($register_success)): ?><div class="alert alert-success mb-3"><?php echo $register_success; ?></div><?php endif; ?>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="fullname" class="form-control" required placeholder="Enter your full name">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required placeholder="Enter your email">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" placeholder="Enter your phone number">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" placeholder="Enter your city">
                                <label class="form-label">Role</label>
                                <select class="form-select" name="role" required>
                                    <option value="user">User</option>
                                    <option value="agent">Agent</option>
                                </select>
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" id="registerPassword" required placeholder="Create a password">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" id="registerConfirmPassword" required placeholder="Confirm your password">
                                <button type="submit" name="register" class="btn btn-primary w-100 py-2">Create Account</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Forgot Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="forgotPasswordForm" autocomplete="off">
                        <label for="forgotEmail" class="form-label">Enter your email address</label>
                        <input type="email" class="form-control" id="forgotEmail" name="forgotEmail" required placeholder="Email">
                        <button type="submit" class="btn btn-primary w-100 py-2 mt-3">Send Reset Link</button>
                        <div id="forgotPasswordMsg" class="mt-3"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // If there was a registration error, show the registration tab by default
        <?php if (!empty($register_error) || !empty($register_success)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var loginModal = new bootstrap.Modal(document.getElementById('loginRegisterModal'));
            var registerTab = new bootstrap.Tab(document.getElementById('register-tab'));
            loginModal.show();
            registerTab.show();
        });
        <?php elseif (!empty($login_error)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var loginModal = new bootstrap.Modal(document.getElementById('loginRegisterModal'));
            loginModal.show();
        });
        <?php endif; ?>
        // Remove show/hide password toggles for minimalism
        // Forgot Password form (demo only)
        document.getElementById('forgotPasswordForm').onsubmit = function(e) {
            e.preventDefault();
            var email = document.getElementById('forgotEmail').value;
            var msg = document.getElementById('forgotPasswordMsg');
            msg.innerHTML = '<div class="alert alert-info">If this email exists, a reset link will be sent.</div>';
            setTimeout(function() {
                var forgotModal = bootstrap.Modal.getInstance(document.getElementById('forgotPasswordModal'));
                forgotModal.hide();
                msg.innerHTML = '';
            }, 2000);
        };
    </script>
</body>
</html>
