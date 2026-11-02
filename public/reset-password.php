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
$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Check if token is valid
if (empty($token)) {
    $error = 'Invalid password reset request.';
} else {
    try {
        // Get user with matching reset token
        $filter = "reset_token=eq." . urlencode($token);
        $users = supabaseRequest("users?$filter", 'GET', null, true);
        
        if (empty($users)) {
            $error = 'Invalid or expired password reset link.';
        } else {
            $user = $users[0];
            
            // Check if token has expired
            $token_expires = strtotime($user['reset_token_expires']);
            if ($token_expires < time()) {
                $error = 'Password reset link has expired. Please request a new one.';
            }
        }
    } catch (Exception $e) {
        error_log('Reset Password Error: ' . $e->getMessage());
        $error = 'An error occurred while processing your request. Please try again later.';
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token)) {
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Please enter both password fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            // Get user with matching reset token
            $filter = "reset_token=eq." . urlencode($token);
            $users = supabaseRequest("users?$filter", 'GET', null, true);
            
            if (!empty($users)) {
                $user = $users[0];
                
                // Update password and clear reset token
                $updateData = [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'reset_token' => null,
                    'reset_token_expires' => null
                ];
                $filter = "user_id=eq." . urlencode($user['user_id']);
                supabaseRequest("users?$filter", 'PATCH', $updateData, true);
                
                $success = 'Password has been successfully reset. You can now log in with your new password.';
            } else {
                $error = 'Invalid password reset request.';
            }
        } catch (Exception $e) {
            error_log('Reset Password Error: ' . $e->getMessage());
            $error = 'An error occurred while resetting your password. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - AcademicEase</title>
    
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

    <!-- Reset Password Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h2 class="text-center mb-4">Reset Password</h2>
                            
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                                <div class="text-center mt-4">
                                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                                </div>
                            <?php else: ?>
                                <form method="post" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>">
                                    <div class="mb-3">
                                        <label for="password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">Reset Password</button>
                                    </div>
                                </form>
                            <?php endif; ?>
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
