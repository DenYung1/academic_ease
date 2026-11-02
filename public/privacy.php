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
    <title>Privacy Policy - AcademicEase</title>
    
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

    <!-- Privacy Policy Content -->
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h1 class="h3 mb-0">Privacy Policy</h1>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">Last updated: May 19, 2025</p>
                        
                        <h2 class="h4 mb-3">1. Introduction</h2>
                        <p>AcademicEase ("we", "our", or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our website, mobile application, and services (collectively, the "Service").</p>
                        <p>Please read this Privacy Policy carefully. If you do not agree with the terms of this Privacy Policy, please do not access the Service.</p>
                        
                        <h2 class="h4 mb-3 mt-4">2. Information We Collect</h2>
                        
                        <h3 class="h5 mb-2">2.1 Personal Information</h3>
                        <p>We may collect personally identifiable information, such as:</p>
                        <ul>
                            <li>Name</li>
                            <li>Email address</li>
                            <li>Phone number</li>
                            <li>Date of birth</li>
                            <li>School name and grade level</li>
                            <li>Payment information (processed securely through our payment processors)</li>
                        </ul>
                        
                        <h3 class="h5 mb-2">2.2 Educational Information</h3>
                        <p>To provide our educational services, we may collect:</p>
                        <ul>
                            <li>Questions you submit to our AI assistant</li>
                            <li>Study materials you access</li>
                            <li>Quiz results and progress data</li>
                            <li>Learning preferences and study habits</li>
                        </ul>
                        
                        <h3 class="h5 mb-2">2.3 Usage Data</h3>
                        <p>We may also collect information that your browser sends whenever you visit our Service or access it through a mobile device, including:</p>
                        <ul>
                            <li>Your computer's Internet Protocol ("IP") address</li>
                            <li>Browser type and version</li>
                            <li>The pages of our Service that you visit</li>
                            <li>The time and date of your visit</li>
                            <li>The time spent on those pages</li>
                            <li>Device identifiers if accessing via mobile</li>
                        </ul>
                        
                        <h2 class="h4 mb-3 mt-4">3. How We Use Your Information</h2>
                        <p>We use the information we collect for various purposes, including to:</p>
                        <ul>
                            <li>Provide, maintain, and improve our Service</li>
                            <li>Personalize your experience with tailored educational content</li>
                            <li>Process payments and manage your account</li>
                            <li>Send you important information regarding the Service, changes to our terms, and policies</li>
                            <li>Respond to your inquiries and provide customer support</li>
                            <li>Monitor usage of the Service for improvement and optimization</li>
                            <li>Detect, prevent, and address technical issues</li>
                            <li>Generate anonymized, aggregated data for educational research and service improvement</li>
                        </ul>
                        
                        <h2 class="h4 mb-3 mt-4">4. Data Retention</h2>
                        <p>We will retain your personal information only for as long as is necessary for the purposes set out in this Privacy Policy. We will retain and use your information to the extent necessary to comply with our legal obligations, resolve disputes, and enforce our policies.</p>
                        <p>Educational data, such as questions asked and study progress, may be retained indefinitely in an anonymized form to improve our educational services.</p>
                        
                        <h2 class="h4 mb-3 mt-4">5. Data Security</h2>
                        <p>We use administrative, technical, and physical security measures to protect your personal information. While we strive to use commercially acceptable means to protect your personal data, we cannot guarantee its absolute security. Any information you transmit to us is done at your own risk.</p>
                        
                        <h2 class="h4 mb-3 mt-4">6. Disclosure of Data</h2>
                        
                        <h3 class="h5 mb-2">6.1 Service Providers</h3>
                        <p>We may employ third-party companies and individuals to facilitate our Service ("Service Providers"), to provide the Service on our behalf, to perform Service-related services, or to assist us in analyzing how our Service is used. These third parties have access to your personal information only to perform these tasks on our behalf and are obligated not to disclose or use it for any other purpose.</p>
                        
                        <h3 class="h5 mb-2">6.2 Business Transfers</h3>
                        <p>If we are involved in a merger, acquisition, or asset sale, your personal information may be transferred. We will provide notice before your personal information is transferred and becomes subject to a different Privacy Policy.</p>
                        
                        <h3 class="h5 mb-2">6.3 Legal Requirements</h3>
                        <p>We may disclose your personal information if required to do so by law or in response to valid requests by public authorities (e.g., a court or a government agency).</p>
                        
                        <h2 class="h4 mb-3 mt-4">7. Children's Privacy</h2>
                        <p>Our Service is intended for use by students in Junior High School (JHS) and Senior High School (SHS). We recognize the need to provide further privacy protections with respect to personal information we may collect from children under the age of 18.</p>
                        <p>For users under 18, we require parental consent before collecting personal information. Parents can review, edit, request the deletion of their child's personal information, or refuse to permit further collection by contacting us.</p>
                        
                        <h2 class="h4 mb-3 mt-4">8. Your Data Protection Rights</h2>
                        <p>You have certain data protection rights. These include:</p>
                        <ul>
                            <li><strong>The right to access</strong> – You have the right to request copies of your personal data.</li>
                            <li><strong>The right to rectification</strong> – You have the right to request that we correct any information you believe is inaccurate or complete information you believe is incomplete.</li>
                            <li><strong>The right to erasure</strong> – You have the right to request that we erase your personal data, under certain conditions.</li>
                            <li><strong>The right to restrict processing</strong> – You have the right to request that we restrict the processing of your personal data, under certain conditions.</li>
                            <li><strong>The right to data portability</strong> – You have the right to request that we transfer the data we have collected to another organization, or directly to you, under certain conditions.</li>
                        </ul>
                        <p>If you make a request, we have one month to respond to you. If you would like to exercise any of these rights, please contact us.</p>
                        
                        <h2 class="h4 mb-3 mt-4">9. Cookies and Tracking Technologies</h2>
                        <p>We use cookies and similar tracking technologies to track activity on our Service and hold certain information. Cookies are files with a small amount of data which may include an anonymous unique identifier.</p>
                        <p>You can instruct your browser to refuse all cookies or to indicate when a cookie is being sent. However, if you do not accept cookies, you may not be able to use some portions of our Service.</p>
                        
                        <h2 class="h4 mb-3 mt-4">10. Third-Party Services</h2>
                        <p>Our Service may contain links to other sites that are not operated by us. If you click on a third-party link, you will be directed to that third party's site. We strongly advise you to review the Privacy Policy of every site you visit.</p>
                        <p>We have no control over and assume no responsibility for the content, privacy policies, or practices of any third-party sites or services.</p>
                        
                        <h2 class="h4 mb-3 mt-4">11. Changes to This Privacy Policy</h2>
                        <p>We may update our Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last updated" date.</p>
                        <p>You are advised to review this Privacy Policy periodically for any changes. Changes to this Privacy Policy are effective when they are posted on this page.</p>
                        
                        <h2 class="h4 mb-3 mt-4">12. Contact Us</h2>
                        <p>If you have any questions about this Privacy Policy, please contact us:</p>
                        <p>Email: privacy@academicease.com<br>
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
