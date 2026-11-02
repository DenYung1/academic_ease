<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$loggedIn = false;
if (isset($_SESSION['user_id'])) {
    $loggedIn = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/enhanced-ui.css">
    
    <!-- Mobile-specific CSS -->
    <link rel="stylesheet" href="assets/css/mobile.css">
    
    <!-- Embedded Logo CSS to fix 404 error -->
    <style>
    /* AcademicEase Logo Styling */
    .ae-logo-container {
        display: flex;
        align-items: center;
        font-family: 'Arial', sans-serif;
    }
    
    .ae-logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #4285F4, #34A853);
        border-radius: 8px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 10px;
        position: relative;
        overflow: hidden;
    }
    
    .ae-logo-icon::before {
        content: 'A';
        color: white;
        font-size: 24px;
        font-weight: bold;
        line-height: 1;
    }
    
    .ae-logo-icon::after {
        content: 'E';
        color: rgba(255, 255, 255, 0.8);
        font-size: 16px;
        font-weight: bold;
        position: absolute;
        bottom: 2px;
        right: 3px;
        line-height: 1;
    }
    
    .ae-logo-text {
        font-size: 18px;
        font-weight: bold;
    }
    
    .ae-academic {
        color: #4285F4;
    }
    
    .ae-ease {
        color: #34A853;
    }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <div class="navbar-brand">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <?php if ($loggedIn): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study.php">Study</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <?php if ($loggedIn): ?>
                        <a href="dashboard.php" class="btn btn-outline-primary me-2">Dashboard</a>
                        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Terms of Service Content -->
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h3 mb-0">Terms of Service</h1>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Last updated: May 19, 2025</p>
                        
                        <h2 class="h4 mb-3">1. Introduction</h2>
                        <p>Welcome to AcademicEase. These Terms of Service ("Terms") govern your use of the AcademicEase website, mobile applications, and services (collectively, the "Service"). By accessing or using the Service, you agree to be bound by these Terms. If you do not agree to these Terms, please do not use the Service.</p>
                        
                        <h2 class="h4 mb-3 mt-4">2. Educational Purpose</h2>
                        <p>AcademicEase is designed to be an educational tool that helps students understand concepts through explanations and examples. Our Service is intended to:</p>
                        <ul>
                            <li>Provide explanations of academic concepts aligned with the Ghana Education Service curriculum</li>
                            <li>Offer examples to illustrate these concepts</li>
                            <li>Help students develop a deeper understanding of their coursework</li>
                            <li>Support exam preparation through practice questions and study guides</li>
                        </ul>
                        <p><strong>AcademicEase is not intended to:</strong></p>
                        <ul>
                            <li>Complete homework assignments for students</li>
                            <li>Provide answers without explanations</li>
                            <li>Replace classroom instruction or teacher guidance</li>
                            <li>Be used for cheating or academic dishonesty</li>
                        </ul>
                        
                        <h2 class="h4 mb-3 mt-4">3. User Accounts</h2>
                        <p>To access certain features of the Service, you must register for an account. When you register, you agree to provide accurate, current, and complete information. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>
                        
                        <h2 class="h4 mb-3 mt-4">4. Acceptable Use</h2>
                        <p>You agree to use the Service only for lawful purposes and in accordance with these Terms. You agree not to:</p>
                        <ul>
                            <li>Use the Service in any way that violates any applicable law or regulation</li>
                            <li>Use the Service to engage in academic dishonesty or cheating</li>
                            <li>Attempt to access or use another user's account without authorization</li>
                            <li>Use the Service to transmit any material that is harmful, threatening, abusive, harassing, defamatory, vulgar, obscene, or otherwise objectionable</li>
                            <li>Attempt to interfere with, compromise the system integrity or security, or decipher any transmissions to or from the servers running the Service</li>
                            <li>Use any robot, spider, crawler, scraper, or other automated means to access the Service</li>
                            <li>Bypass measures we may use to prevent or restrict access to the Service</li>
                        </ul>
                        
                        <h2 class="h4 mb-3 mt-4">5. Subscription and Payment</h2>
                        <p>Some features of the Service require a paid subscription. By subscribing to a paid plan, you agree to pay the subscription fees as described at the time of purchase. Subscription fees are non-refundable except as required by law or as explicitly stated in these Terms.</p>
                        <p>We offer a free trial period as specified at the time of registration. After the trial period ends, your account will automatically convert to a paid subscription unless you cancel before the trial period ends.</p>
                        
                        <h2 class="h4 mb-3 mt-4">6. Intellectual Property</h2>
                        <p>The Service and its original content, features, and functionality are owned by AcademicEase and are protected by international copyright, trademark, patent, trade secret, and other intellectual property or proprietary rights laws.</p>
                        <p>You may not copy, modify, create derivative works of, publicly display, publicly perform, republish, or transmit any of the material on our Service without prior written consent.</p>
                        
                        <h2 class="h4 mb-3 mt-4">7. User Content</h2>
                        <p>You retain all rights to any content you submit, post, or display on or through the Service ("User Content"). By providing User Content, you grant us a worldwide, non-exclusive, royalty-free license to use, reproduce, modify, adapt, publish, translate, and distribute your User Content in connection with the Service.</p>
                        <p>You represent and warrant that your User Content does not violate copyright, trademark, or other intellectual property rights of any third party.</p>
                        
                        <h2 class="h4 mb-3 mt-4">8. Termination</h2>
                        <p>We may terminate or suspend your account and access to the Service immediately, without prior notice or liability, for any reason, including if you breach these Terms.</p>
                        <p>Upon termination, your right to use the Service will immediately cease. If you wish to terminate your account, you may simply discontinue using the Service or contact us to request account deletion.</p>
                        
                        <h2 class="h4 mb-3 mt-4">9. Limitation of Liability</h2>
                        <p>To the maximum extent permitted by law, AcademicEase and its directors, employees, partners, agents, suppliers, or affiliates shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from:</p>
                        <ul>
                            <li>Your access to or use of or inability to access or use the Service</li>
                            <li>Any conduct or content of any third party on the Service</li>
                            <li>Any content obtained from the Service</li>
                            <li>Unauthorized access, use, or alteration of your transmissions or content</li>
                        </ul>
                        
                        <h2 class="h4 mb-3 mt-4">10. Disclaimer</h2>
                        <p>The Service is provided on an "AS IS" and "AS AVAILABLE" basis. AcademicEase makes no warranties, expressed or implied, regarding the operation or availability of the Service.</p>
                        <p>While we strive to provide accurate and reliable information, we do not guarantee the accuracy, completeness, or usefulness of any information on the Service. Any reliance you place on such information is strictly at your own risk.</p>
                        
                        <h2 class="h4 mb-3 mt-4">11. Changes to Terms</h2>
                        <p>We reserve the right to modify or replace these Terms at any time. If a revision is material, we will provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
                        <p>By continuing to access or use our Service after any revisions become effective, you agree to be bound by the revised terms.</p>
                        
                        <h2 class="h4 mb-3 mt-4">12. Contact Us</h2>
                        <p>If you have any questions about these Terms, please contact us at:</p>
                        <p>Email: support@academicease.com<br>
                        Address: 123 Education Street, Accra, Ghana</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted me-3">Terms of Service</a>
                    <a href="support.php" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Performance optimization script -->
    <script src="assets/js/performance.js"></script>
</body>
</html>
