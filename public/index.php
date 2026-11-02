<?php
// Start session
session_start();

// Check if user is logged in and validate the session
$loggedIn = false;
if (isset($_SESSION['user_id'])) {
    try {
        // The public page does not need a database connection for logged-out visitors.
        require_once '../includes/config.php';
        require_once '../includes/db_connect.php';

        // Verify that the user exists in the database
        $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
        $users = supabaseRequest($endpoint, 'GET', null, true);
        if (!empty($users)) {
            $loggedIn = true;
        } else {
            // User not found, clear the session
            session_unset();
            session_destroy();
            session_start();
        }
    } catch (Throwable $e) {
        error_log('Homepage session validation error: ' . $e->getMessage());
        // Error occurred, clear the session to be safe
        session_unset();
        session_destroy();
        session_start();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcademicEase - AI Study Assistant for JHS/SHS Students</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
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
    
    <!-- Meta tags for SEO -->
    <meta name="description" content="AcademicEase is an AI-powered study assistant designed for Junior and Senior High School students in Ghana. Get help with BECE and WASSCE preparation.">
    <meta name="keywords" content="study assistant, AI tutor, JHS, SHS, BECE, WASSCE, Ghana education">
    
    <!-- PWA support -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#1A73E8">
    
    <!-- Mobile-specific CSS -->
    <style>
        /* Mobile-specific enhancements */
        @media (max-width: 767.98px) {
            /* Increase touch target sizes for better mobile usability */
            .btn, .nav-link, .dropdown-item, input[type="radio"], input[type="checkbox"], select {
                min-height: 44px; /* Apple's recommended minimum touch target size */
                padding: 10px 15px;
            }
            
            /* Improve form elements on mobile */
            input, select, textarea {
                font-size: 16px !important; /* Prevents iOS zoom on focus */
            }
            
            /* Adjust spacing for mobile */
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
            
            /* Make tables responsive */
            table {
                display: block;
                width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 575.98px) {
            h1 { font-size: 1.8rem !important; }
            h2 { font-size: 1.5rem !important; }
            
            /* Reduce animations for better performance */
            * {
                animation-duration: 0.1s !important;
                transition-duration: 0.1s !important;
            }
            
            .card:hover {
                transform: none !important;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05) !important;
            }
            
            /* Improve button spacing on mobile */
            .navbar .d-flex .btn {
                margin-right: 8px !important;
                padding: 8px 12px !important;
            }
            
            /* Better content stacking */
            .row > [class*="col-"] {
                margin-bottom: 20px;
            }
            
            /* Optimize images for mobile */
            img {
                max-width: 100%;
                height: auto;
            }
        }
    </style>
    
    <!-- Preconnect to external domains for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="javascript:void(0)" onclick="scrollToSection('index')">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="javascript:void(0)" onclick="scrollToSection('features')">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="javascript:void(0)" onclick="scrollToSection('pricing')">Pricing</a>
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

    <!-- Logout message removed -->
    
    <!-- Hero Section -->
    <section id="index" class="hero-section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Your Personal AI Study Assistant</h1>
                    <p class="lead mb-4">Get instant explanations, examples, and learning guidance for your studies. Designed specifically to support Ghanaian JHS and SHS students in understanding curriculum concepts.</p>
                    <div class="mb-4">
                        <a href="register.php" class="btn btn-primary btn-lg me-2">Start Free Trial</a>
                        <a href="#features" class="btn btn-outline-secondary btn-lg">Learn More</a>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">BECE</span>
                        <span class="badge bg-success me-2">WASSCE</span>
                        <span class="badge bg-success me-2">JHS</span>
                        <span class="badge bg-success">SHS</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm rounded" style="background: linear-gradient(135deg, #4285F4, #34A853); height: 100%; overflow: hidden;">
                        <div class="card-body text-center text-white p-4">
                            <div class="d-flex justify-content-center mb-2">
                                <span class="badge bg-danger px-3 py-2 rounded-pill">AI-Powered</span>
                            </div>
                            <h2 class="fw-bold mb-3 mt-4">Learn. Grow. Succeed.</h2>
                            <p class="mb-4">Your educational journey starts here</p>
                            <div class="row justify-content-center mt-4 mb-3">
                                <div class="col-3">
                                    <div class="bg-white text-primary rounded-circle p-3 mx-auto" style="width: 60px; height: 60px;">
                                        <i class="bi bi-book fs-3"></i>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white text-primary rounded-circle p-3 mx-auto" style="width: 60px; height: 60px;">
                                        <i class="bi bi-calculator fs-3"></i>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white text-primary rounded-circle p-3 mx-auto" style="width: 60px; height: 60px;">
                                        <i class="bi bi-globe fs-3"></i>
                                    </div>
                                </div>
                                <div class="col-3">
                                    <div class="bg-white text-primary rounded-circle p-3 mx-auto" style="width: 60px; height: 60px;">
                                        <i class="bi bi-mortarboard fs-3"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="position-absolute top-0 end-0 m-3">
                                <div style="width: 50px; height: 35px; background: linear-gradient(to bottom, #CE1126 33.3%, #FCD116 33.3%, #FCD116 66.6%, #006B3F 66.6%);">
                                    <div class="position-relative" style="width: 100%; height: 100%;">
                                        <div class="position-absolute" style="width: 15px; height: 15px; background: #000; top: 10px; left: 17px; clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">How AcademicEase Helps You</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-mortarboard-fill fs-1 text-primary"></i>
                            </div>
                            <h3 class="card-title">Concept Explanations</h3>
                            <p class="card-text">Get clear explanations and examples to understand difficult concepts in any subject. Upload a photo or type your question.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-journal-check fs-1 text-primary"></i>
                            </div>
                            <h3 class="card-title">Exam Preparation</h3>
                            <p class="card-text">Prepare for BECE or WASSCE with practice questions, study guides, and personalized assistance.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon mb-3">
                                <i class="bi bi-chat-dots-fill fs-1 text-primary"></i>
                            </div>
                            <h3 class="card-title">WhatsApp Access</h3>
                            <p class="card-text">Access AcademicEase anytime, anywhere through WhatsApp. No need for high-speed internet or expensive devices.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">How It Works</h2>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="step text-center">
                        <div class="step-number">1</div>
                        <h4>Sign Up</h4>
                        <p>Create your account and start with a free one-day trial.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="step text-center">
                        <div class="step-number">2</div>
                        <h4>Ask Questions</h4>
                        <p>Type your question or upload a photo of concepts you want to understand.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="step text-center">
                        <div class="step-number">3</div>
                        <h4>Learn Concepts</h4>
                        <p>Receive detailed explanations and examples tailored to your curriculum.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="step text-center">
                        <div class="step-number">4</div>
                        <h4>Track Progress</h4>
                        <p>Monitor your learning journey and identify areas for improvement.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="pricing-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Affordable Plans for Everyone</h2>
            <div class="row justify-content-center">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card pricing-card h-100">
                        <div class="card-header text-center bg-success text-white">
                            <h3 class="my-0">Free Trial</h3>
                            <span class="badge bg-warning text-dark">Get Started</span>
                        </div>
                        <div class="card-body">
                            <h2 class="card-title pricing-card-title text-center">GH¢0 <small class="text-muted">/ 1 day</small></h2>
                            <ul class="list-unstyled mt-3 mb-4">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Full access for 24 hours</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Try all features</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> No credit card required</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Easy upgrade anytime</li>
                                <li><i class="bi bi-x-circle-fill text-danger me-2"></i> Limited usage</li>
                            </ul>
                            <a href="register.php" class="btn btn-success d-block">Start Free Trial</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card pricing-card h-100">
                        <div class="card-header text-center bg-primary text-white">
                            <h3 class="my-0">Weekly Plan</h3>
                        </div>
                        <div class="card-body">
                            <h2 class="card-title pricing-card-title text-center">GH¢20 <small class="text-muted">/ week</small></h2>
                            <ul class="list-unstyled mt-3 mb-4">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Full access for 7 days</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Perfect for exam weeks</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> All features included</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Renew or upgrade anytime</li>
                                <li><i class="bi bi-x-circle-fill text-danger me-2"></i> Pay weekly (more expensive)</li>
                            </ul>
                            <a href="register.php?plan=weekly" class="btn btn-primary d-block">Choose Plan</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card pricing-card h-100 border-primary">
                        <div class="card-header text-center bg-primary text-white">
                            <h3 class="my-0">Monthly Plan</h3>
                            <span class="badge bg-warning text-dark">Best Value</span>
                        </div>
                        <div class="card-body">
                            <h2 class="card-title pricing-card-title text-center">GH¢60 <small class="text-muted">/ month</small></h2>
                            <p class="text-center text-success"><strong>Save 25% compared to weekly</strong></p>
                            <ul class="list-unstyled mt-3 mb-4">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Full access for 30 days</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Best for continuous learning</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> All features included</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Priority support</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Most cost-effective option</li>
                            </ul>
                            <a href="register.php?plan=monthly" class="btn btn-primary d-block">Choose Plan</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">What Our Students Say</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex mb-3">
                                <div class="text-warning">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                            <p class="card-text">"AcademicEase helped me improve my math grades from 65% to 85%. The step-by-step explanations make complex problems easy to understand."</p>
                            <div class="d-flex align-items-center">
                                <div class="testimonial-avatar me-3">
                                    <img src="" alt="Student" class="rounded-circle" width="50" height="50">
                                </div>
                                <div>
                                    <h6 class="mb-0">Kofi Mensah</h6>
                                    <small class="text-muted">SHS2 Student, Accra</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex mb-3">
                                <div class="text-warning">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                            <p class="card-text">"I love that I can access AcademicEase through WhatsApp. It's like having a personal tutor available 24/7 to help with my BECE preparation."</p>
                            <div class="d-flex align-items-center">
                                <div class="testimonial-avatar me-3">
                                    <img src="" alt="Student" class="rounded-circle" width="50" height="50">
                                </div>
                                <div>
                                    <h6 class="mb-0">Ama Owusu</h6>
                                    <small class="text-muted">JHS3 Student, Kumasi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex mb-3">
                                <div class="text-warning">
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-fill"></i>
                                    <i class="bi bi-star-half"></i>
                                </div>
                            </div>
                            <p class="card-text">"As a parent, I appreciate the weekly progress reports. I can now track my son's academic progress and see which subjects he needs more help with."</p>
                            <div class="d-flex align-items-center">
                                <div class="testimonial-avatar me-3">
                                    <img src="" alt="Parent" class="rounded-circle" width="50" height="50">
                                </div>
                                <div>
                                    <h6 class="mb-0">Mrs. Addo</h6>
                                    <small class="text-muted">Parent, Takoradi</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="cta-section py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Improve Your Grades?</h2>
            <p class="lead mb-4">Start your free trial today and experience the AcademicEase difference.</p>
            <a href="register.php" class="btn btn-light btn-lg">Start Free Trial</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-5 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5>AcademicEase</h5>
                    <p>AI-powered concept explanation and study assistant for JHS and SHS students in Ghana.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-2"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="text-white me-2"><i class="bi bi-twitter"></i></a>
                        <a href="#" class="text-white me-2"><i class="bi bi-instagram"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="features.php" class="text-white">Features</a></li>
                        <li><a href="pricing.php" class="text-white">Pricing</a></li>
                        <li><a href="register.php" class="text-white">Sign Up</a></li>
                        <li><a href="login.php" class="text-white">Login</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-4 col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-envelope me-2"></i> info@academicease.com</li>
                        <li><i class="bi bi-phone me-2"></i> +233244878667</li>
                        <li><i class="bi bi-whatsapp me-2"></i> +233503404986</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="privacy.php" class="text-white">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="terms.php" class="text-white">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Embedded Hero Animation JavaScript -->
    <script>
    /**
     * Hero Animation for AcademicEase
     * Creates an interactive and engaging hero section for the homepage
     */
    
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the hero animation if the hero container exists
        const heroContainer = document.getElementById('hero-animation-container');
        if (heroContainer) {
            initHeroAnimation(heroContainer);
        }
        
        function initHeroAnimation(container) {
            // Create floating elements
            const elements = ['📚', '🔍', '📝', '🧠', '📊', '💡', '🎓', '📱'];
            elements.forEach(emoji => {
                createFloatingElement(container, emoji);
            });
        }
        
        function createFloatingElement(container, content) {
            const element = document.createElement('div');
            element.className = 'floating-element';
            element.textContent = content;
            element.style.left = Math.random() * 90 + '%';
            element.style.animationDuration = (Math.random() * 10 + 10) + 's';
            element.style.animationDelay = (Math.random() * 5) + 's';
            container.appendChild(element);
        }
    });
    </script>
    
    <!-- Embedded Enhanced UI JavaScript -->
    <script>
    // Enhanced UI JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth scrolling to all links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add hover effects to cards
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 15px rgba(0, 0, 0, 0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
        });
    });
    </script>
    
    <!-- Embedded Settings Panel JavaScript -->
    <script>
    // Settings Panel JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Add CSS for settings panel
        const style = document.createElement('style');
        style.textContent = `
            #settings-panel {
                position: fixed;
                right: -300px;
                top: 0;
                width: 300px;
                height: 100%;
                background-color: #ffffff;
                box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
                transition: right 0.3s ease;
                z-index: 1050;
                padding: 20px;
                overflow-y: auto;
            }
            
            #settings-panel.show {
                right: 0;
            }
            
            .dark-theme #settings-panel {
                background-color: #1e1e1e;
                color: #e0e0e0;
                box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
            }
            
            .settings-section {
                margin-bottom: 20px;
                padding-bottom: 20px;
                border-bottom: 1px solid #eeeeee;
            }
            
            .dark-theme .settings-section {
                border-bottom-color: #333333;
            }
        `;
        document.head.appendChild(style);
        
        // Create settings button if it doesn't exist
        if (!document.querySelector('#settings-toggle')) {
            const settingsBtn = document.createElement('button');
            settingsBtn.id = 'settings-toggle';
            settingsBtn.className = 'btn btn-sm btn-outline-secondary position-fixed';
            settingsBtn.style.bottom = '20px';
            settingsBtn.style.right = '20px';
            settingsBtn.style.zIndex = '1051';
            settingsBtn.innerHTML = '<i class="bi bi-gear"></i>';
            document.body.appendChild(settingsBtn);
        }
        
        // Create settings panel if it doesn't exist
        if (!document.querySelector('#settings-panel')) {
            const settingsPanel = document.createElement('div');
            settingsPanel.id = 'settings-panel';
            settingsPanel.innerHTML = `
                <h5 class="mb-3">Display Settings</h5>
                <div class="settings-section">
                    <div class="mb-3">
                        <label for="theme-selector" class="form-label">Theme</label>
                        <select class="form-select" id="theme-selector">
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Font Size</label>
                        <div class="d-flex">
                            <button id="font-smaller" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-dash"></i></button>
                            <button id="font-reset" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <button id="font-larger" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(settingsPanel);
        }
        
        // Toggle settings panel
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsPanel = document.getElementById('settings-panel');
        
        if (settingsToggle && settingsPanel) {
            settingsToggle.addEventListener('click', function() {
                settingsPanel.classList.toggle('show');
            });
            
            // Close panel when clicking outside
            document.addEventListener('click', function(e) {
                if (!settingsPanel.contains(e.target) && e.target !== settingsToggle) {
                    settingsPanel.classList.remove('show');
                }
            });
            
            // Theme selector
            const themeSelector = document.getElementById('theme-selector');
            themeSelector.addEventListener('change', function() {
                if (this.value === 'dark') {
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-theme');
                    localStorage.setItem('theme', 'light');
                }
            });
            
            // Font size controls
            document.getElementById('font-smaller').addEventListener('click', function() {
                changeFontSize(-1);
            });
            
            document.getElementById('font-reset').addEventListener('click', function() {
                resetFontSize();
            });
            
            document.getElementById('font-larger').addEventListener('click', function() {
                changeFontSize(1);
            });
            
            // Load saved settings
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                themeSelector.value = 'dark';
            }
            
            const savedFontSize = localStorage.getItem('fontSize');
            if (savedFontSize) {
                document.documentElement.style.fontSize = savedFontSize + 'px';
            }
        }
        
        // Font size functions
        function changeFontSize(step) {
            const currentSize = parseInt(window.getComputedStyle(document.documentElement).fontSize);
            const newSize = currentSize + step;
            document.documentElement.style.fontSize = newSize + 'px';
            localStorage.setItem('fontSize', newSize);
        }
        
        function resetFontSize() {
            document.documentElement.style.fontSize = '16px';
            localStorage.removeItem('fontSize');
        }
    });
    </script>
    
    <!-- Custom JS -->
    <script src="./assets/js/main.js"></script>
    
    <!-- Custom Navigation JavaScript -->
    <script>
        // Function to handle smooth scrolling to sections
        function scrollToSection(sectionId) {
            // Scroll to the section
            const section = document.getElementById(sectionId);
            if (section) {
                section.scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        // Always scroll to top on page load/refresh
        window.onload = function() {
            window.scrollTo(0, 0);
        };
    </script>
    
    <!-- Service Worker Registration for PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js').then(function(registration) {
                    console.log('ServiceWorker registration successful');
                }, function(err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
    
    <!-- Performance optimization script -->
    <script>
        // Enable link prefetching for faster navigation
        document.addEventListener('DOMContentLoaded', function() {
            // Prefetch links when user hovers over them
            const links = document.querySelectorAll('a');
            links.forEach(link => {
                if (link.href && link.href.startsWith(window.location.origin)) {
                    link.addEventListener('mouseenter', () => {
                        const prefetchLink = document.createElement('link');
                        prefetchLink.rel = 'prefetch';
                        prefetchLink.href = link.href;
                        document.head.appendChild(prefetchLink);
                    });
                }
            });
            
            // Optimize image loading with lazy loading
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                if (!img.hasAttribute('loading')) {
                    img.setAttribute('loading', 'lazy');
                }
            });
        });
        
        // Improve mobile touch response by removing 300ms delay
        document.addEventListener('touchend', function() {}, false);
    </script>
</body>
</html>
