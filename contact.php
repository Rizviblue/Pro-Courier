<?php
// This file should be placed in your project's root directory.
require_once 'includes/db_connect.php'; // Include the database connection

$message = '';
// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Sanitize and retrieve form data
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $category = trim($_POST['category']);
    $subject = trim($_POST['subject']);
    $form_message = trim($_POST['message']);

    // Prepare an insert statement to save the message into the database
    $sql = "INSERT INTO support_messages (name, email, category, subject, message) VALUES (?, ?, ?, ?, ?)";
    
    if ($stmt = $conn->prepare($sql)) {
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("sssss", $fullName, $email, $category, $subject, $form_message);
        
        // Attempt to execute the prepared statement
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success mt-3'>Thank you for your message! We have received it and will get back to you shortly.</div>";
        } else {
            // Provide a generic error message for the user
            $message = "<div class='alert alert-danger mt-3'>Oops! Something went wrong. Please try again later.</div>";
            // For debugging: error_log("SQL Error: " . $stmt->error);
        }

        // Close statement
        $stmt->close();
    } else {
         $message = "<div class='alert alert-danger mt-3'>Error preparing the statement.</div>";
         // For debugging: error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }
    
    // Close connection
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - CourierPro</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --main-bg: linear-gradient(120deg, #f8fbff 60%, #eaf1fb 100%);
            --card-bg: #ffffff;
            --text-color: #4a5568;
            --border-color: #e2e8f0;
            --input-bg: #f7fafc;
        }
        body {
            background: var(--main-bg);
            font-family: 'Inter', sans-serif;
            color: var(--text-color);
        }
        .main-container {
            max-width: 1200px;
            margin: 1.5rem auto 2.5rem auto;
            padding: 1.5rem;
        }
        .header {
            text-align: center;
            margin-bottom: 2.8rem;
        }
        .header h1 {
            font-weight: 800;
            color: #212529;
            font-size: 2.5rem;
            letter-spacing: -1px;
        }
        .header p {
            font-size: 1.13rem;
            color: #6c757d;
            margin-top: 0.7rem;
        }
        .card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px rgba(60,60,60,0.10);
            background-color: #fff;
            margin-bottom: 2rem;
        }
        .card-body {
            padding: 2.5rem 2.2rem 2.2rem 2.2rem;
        }
        .card-title {
            font-weight: 700;
            color: #212529;
            font-size: 1.35rem;
            margin-bottom: 2rem;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }
        .form-label {
            font-size: 0.97rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.25rem;
        }
        .form-control, .form-select {
            background-color: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 0.7rem;
            height: 52px;
            font-size: 1.08rem;
            padding: 0.7rem 1.1rem;
            margin-bottom: 1.2rem;
            box-shadow: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus, .form-select:focus {
            background-color: #fff;
            border-color: var(--primary-color);
            box-shadow: 0 2px 8px rgba(13,110,253,0.07);
        }
        textarea.form-control {
            height: 120px;
            resize: vertical;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 1rem 0;
            font-weight: 700;
            border-radius: 2rem;
            font-size: 1.13rem;
            box-shadow: 0 2px 8px rgba(13,110,253,0.08);
            letter-spacing: 0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7rem;
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-primary:hover {
            background: #0b5ed7;
            box-shadow: 0 4px 16px rgba(13,110,253,0.13);
        }
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2.2rem;
        }
        .contact-info-item:last-child {
            margin-bottom: 0;
        }
        .contact-info-item .icon-wrapper {
            background-color: #e6f0ff;
            border-radius: 50%;
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1.2rem;
            flex-shrink: 0;
        }
        .contact-info-item .icon-wrapper i {
            font-size: 1.35rem;
            color: var(--primary-color);
        }
        .contact-info-item .details h6 {
            font-weight: 700;
            color: #212529;
            margin-bottom: 0.3rem;
            font-size: 1.08rem;
        }
        .contact-info-item .details p {
            margin-bottom: 0;
            font-size: 0.97rem;
            color: #6c757d;
        }
        .contact-info-item .details a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        @media (max-width: 991px) {
            .main-container { padding: 0.7rem; }
            .card-body { padding: 1.5rem 1rem; }
        }
        @media (max-width: 767px) {
            .main-container { margin: 1.2rem auto; }
            .header h1 { font-size: 1.6rem; }
            .card-title { font-size: 1.1rem; }
            .contact-info-item .icon-wrapper { width: 40px; height: 40px; }
            .contact-info-item .icon-wrapper i { font-size: 1rem; }
        }
        .back-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #0d6efd;
            background: none;
            border: none;
            font-weight: 500;
            font-size: 1.01rem;
            padding: 0;
            box-shadow: none;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link-btn i {
            font-size: 1.13rem;
            display: flex;
            align-items: center;
        }
        .back-link-btn:hover {
            color: #084298;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header text-center mb-4">
            <h1 style="margin-bottom:0.2rem;">Contact Support</h1>
            <p style="margin-bottom:0;">Need help? We're here to assist you with any questions or issues.</p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body" style="padding-top: 1.3rem;">
                        <a href="index.php" class="back-link-btn" style="margin-left:0; margin-bottom:0.9rem; display:inline-flex;"><i class="fas fa-arrow-left"></i>Back to Login</a>
                        <h4 class="card-title mb-2" style="margin-bottom:0.7rem !important;"><i class="fas fa-paper-plane me-2"></i>Send us a Message</h4>
                        <?php echo $message; ?>
                        <form id="contactForm" method="post" action="contact.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="fullName" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="fullName" name="fullName" placeholder="Enter your full name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option selected disabled value="">Select category</option>
                                        <option>Package Tracking</option>
                                        <option>Delivery Issues</option>
                                        <option>Account Support</option>
                                        <option>Billing Questions</option>
                                        <option>Technical Support</option>
                                        <option>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" placeholder="Brief description of your issue" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Message *</label>
                                <textarea class="form-control" id="message" name="message" rows="5" placeholder="Please describe your issue or question in detail..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane"></i>Send Message</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title">Get in Touch</h4>
                        <div class="contact-info-item">
                            <div class="icon-wrapper"><i class="fas fa-phone-alt"></i></div>
                            <div class="details">
                                <h6>Phone Support</h6>
                                <p><a href="tel:+15551234567">+1 (555) 123-4567</a></p>
                                <p class="text-muted small">Mon-Fri, 9 AM - 6 PM EST</p>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <div class="icon-wrapper"><i class="fas fa-envelope"></i></div>
                            <div class="details">
                                <h6>Email Support</h6>
                                <p><a href="mailto:support@courierpro.com">support@courierpro.com</a></p>
                                <p class="text-muted small">Response within 24 hours</p>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <div class="icon-wrapper"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="details">
                                <h6>Office Address</h6>
                                <p>123 Business Plaza, Suite 100<br>New York, NY 10001</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
