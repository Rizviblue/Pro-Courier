<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['user_role'] !== 'user') {
    header("location: ../index.php");
    exit;
}
// --- FIX: Added database connection before including top_bar.php ---
require_once '../includes/db_connect.php'; 
include '../includes/header.php';
?>
<body>
    <?php include '../includes/sidebar_user.php'; ?>
    <div class="main-content">
        <?php include '../includes/top_bar.php'; ?>
        <div class="page-header">
            <h2>Contact Support</h2>
            <p>Need help? We're here to assist you with any questions or issues.</p>
        </div>
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title"><i class="fas fa-paper-plane me-2"></i>Send us a Message</h4>
                        <form id="contactForm">
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="fullName" class="form-label">Full Name *</label><input type="text" class="form-control" id="fullName" placeholder="Enter your full name" required></div>
                                <div class="col-md-6 mb-3"><label for="email" class="form-label">Email Address *</label><input type="email" class="form-control" id="email" placeholder="Enter your email" required></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="category" class="form-label">Category *</label><select class="form-select" id="category" required><option selected disabled value="">Select category</option><option>Package Tracking</option><option>Delivery Issues</option><option>Account Support</option><option>Billing Questions</option><option>Technical Support</option><option>Other</option></select></div>
                                <div class="col-md-6 mb-3"><label for="subject" class="form-label">Subject *</label><input type="text" class="form-control" id="subject" placeholder="Brief description of your issue" required></div>
                            </div>
                            <div class="mb-3"><label for="message" class="form-label">Message *</label><textarea class="form-control" id="message" rows="5" placeholder="Please describe your issue or question in detail..." required></textarea></div>
                            <button type="submit" class="btn btn-primary w-100">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Get in Touch</h4>
                        <div class="contact-info-item mb-4"><div class="icon-wrapper"><i class="fas fa-phone-alt"></i></div><div class="details"><h6>Phone Support</h6><p><a href="tel:+15551234567">+1 (555) 123-4567</a></p><p class="text-muted small">Mon-Fri, 9 AM - 6 PM EST</p></div></div>
                        <div class="contact-info-item mb-4"><div class="icon-wrapper"><i class="fas fa-envelope"></i></div><div class="details"><h6>Email Support</h6><p><a href="mailto:support@courierpro.com">support@courierpro.com</a></p><p class="text-muted small">Response within 24 hours</p></div></div>
                        <div class="contact-info-item mb-4"><div class="icon-wrapper"><i class="fas fa-comments"></i></div><div class="details"><h6>Live Chat</h6><p><a href="#">Available 24/7</a></p><p class="text-muted small">Instant support</p></div></div>
                        <div class="contact-info-item"><div class="icon-wrapper"><i class="fas fa-map-marker-alt"></i></div><div class="details"><h6>Office Address</h6><p>123 Business Plaza, Suite 100<br>New York, NY 10001</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
