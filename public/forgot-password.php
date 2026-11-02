<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session
session_start();

// Initialize variables
$email = '';
$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if user exists
            $filter = "email=eq." . urlencode($email);
            $users = supabaseRequest("users?$filter", 'GET', null, true);
            
            if (empty($users)) {
                // Don't reveal whether email exists for security
                $success = 'If an account exists with this email, you will receive an email with instructions to reset your password.';
            } else {
                $user = $users[0];
                
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                
                // Update user with reset token and expiration
                $updateData = [
                    'reset_token' => $reset_token,
                    'reset_token_expires' => date('c', strtotime('+1 hour'))
                ];
                $filter = "user_id=eq." . urlencode($user['user_id']);
                supabaseRequest("users?$filter", 'PATCH', $updateData, true);
                
                // Create reset URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $reset_url = "{$protocol}://{$_SERVER['HTTP_HOST']}/reset-password.php?token={$reset_token}";
                
                // Only store reset URL in session for development environments
                $is_development = ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false);
                if ($is_development) {
                    $_SESSION['reset_url'] = $reset_url;
                }
                
                // Prepare email content
                $subject = "AcademicEase - Password Reset Instructions";
                $message = "Dear " . htmlspecialchars($user['full_name']) . ",\n\n";
                $message .= "You have requested to reset your password for AcademicEase.\n\n";
                $message .= "Please click the following link to reset your password:\n";
                $message .= $reset_url . "\n\n";
                $message .= "This link will expire in 1 hour. If you did not request this password reset, please ignore this email.\n\n";
                $message .= "Thank you,\nAcademicEase Team";
                
                $headers = "From: AcademicEase <no-reply@academicease.com>";
                
                // Log the email details for debugging
                error_log("Password reset email would be sent to: {$email} with reset URL: {$reset_url}");
                
                // Try to send email, but continue even if it fails
                $mail_sent = @mail($email, $subject, $message, $headers);
                if (!$mail_sent) {
                    error_log("Failed to send password reset email to {$email}");
                }
                
                // Always show success message to prevent email enumeration
                $success = 'If an account exists with this email, you will receive instructions to reset your password.';
            }
        } catch (Exception $e) {
            error_log('Forgot Password Error: ' . $e->getMessage());
            $error = 'An error occurred while processing your request. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="./assets/css/style.css">
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="features.php">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pricing.php">Pricing</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                    <a href="register.php" class="btn btn-primary">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Forgot Password Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h2 class="text-center mb-4">Forgot Password</h2>
                            
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                                <?php 
                                // Only show development reset link on localhost
                                $is_development = ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false);
                                if ($is_development && isset($_SESSION['reset_url'])): 
                                ?>
                                    <div class="alert alert-info mt-3" role="alert">
                                        <p><strong>Development Mode:</strong> Use this link to reset your password:</p>
                                        <a href="<?php echo htmlspecialchars($_SESSION['reset_url']); ?>" class="btn btn-info btn-sm mt-2">Reset Password</a>
                                        <p class="mt-2 small text-muted">Note: In production, this link would be sent via email.</p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <form method="post" action="forgot-password.php">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Send Reset Link</button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="login.php">Back to Login</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-5 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5>Academic Ease</h5>
                    <p>AI-powered homework and study assistant for JHS and SHS students in Ghana.</p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="features.php" class="text-white">Features</a></li>
                        <li><a href="pricing.php" class="text-white">Pricing</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h5>Resources</h5>
                    <ul class="list-unstyled">
                        <li><a href="faq.php" class="text-white">FAQ</a></li>
                        <li><a href="support.php" class="text-white">Support</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-envelope me-2"></i> info@academicease.com</li>
                        <li><i class="bi bi-phone me-2"></i> +233 20 123 4567</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 Academic Ease. All rights reserved.</p>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
