<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/parent_child_helper.php'; // Include parent-child helper functions
require_once '../includes/device_tracking.php'; // Include device tracking functions

// Start session with secure settings
session_start([
    'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
    'cookie_secure' => isset($_SERVER['HTTPS']), // Use secure cookies when on HTTPS
    'cookie_samesite' => 'Lax'    // Prevent CSRF attacks
]);

// Store plan parameter in session if provided
if (isset($_GET['plan'])) {
    $_SESSION['selected_plan'] = $_GET['plan'];
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // If user has a selected plan, redirect to subscription page
    if (isset($_SESSION['selected_plan'])) {
        header('Location: subscribe-direct.php?plan=' . $_SESSION['selected_plan']);
    } else {
        header('Location: dashboard.php');
    }
    exit;
}

// Initialize variables
$email = isset($_GET['email']) ? $_GET['email'] : '';
$error = '';
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';
$has_child = isset($_GET['has_child']) ? true : false;

// Check for session timeout message
if (isset($_SESSION['timeout_message'])) {
    $error = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
} else if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
} else if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $error = "Your session has expired due to inactivity. Please log in again.";
} else if (isset($_GET['security']) && $_GET['security'] == 1) {
    $error = "Your session was terminated for security reasons. Please log in again.";
} else if (isset($_GET['security']) && $_GET['security'] == 'device') {
    $error = "Your session was terminated because we detected an unusual login pattern. For security reasons, AcademicEase accounts can only be used on a limited number of devices.";
}

// Implement brute force protection
$login_attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$last_attempt = isset($_SESSION['last_attempt']) ? $_SESSION['last_attempt'] : 0;

// After 5 failed attempts, user must wait 5 minutes before trying again
if ($login_attempts >= 5 && (time() - $last_attempt) < 300) {
    $wait_time = ceil((300 - (time() - $last_attempt)) / 60);
    $error = "Too many failed login attempts. Please try again in {$wait_time} minutes.";
    
    // Log potential brute force attempt
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    error_log("Potential brute force attack detected from IP: {$ip}, User-Agent: {$user_agent}");
    
    // If there have been 10+ attempts, implement a longer timeout (30 minutes)
    if ($login_attempts >= 10) {
        $wait_time = ceil((1800 - (time() - $last_attempt)) / 60);
        $error = "Account temporarily locked. Please try again in {$wait_time} minutes or contact an administrator.";
        error_log("Account locked due to excessive login attempts from IP: {$ip}");
    }
}

// Debug: Log session information before login attempt
error_log('Login - Session ID before login: ' . session_id());
error_log('Login - SESSION before login: ' . json_encode($_SESSION));

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    // Get form data
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Update login attempt tracking
    $_SESSION['login_attempts'] = $login_attempts + 1;
    $_SESSION['last_attempt'] = time();
    
    // Validate form data
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Check if user exists
            // Use direct API request with service role key to bypass RLS policies
            $filter = "email=eq." . urlencode($email);
            $users = supabaseRequest("users?$filter", 'GET', null, true);
            
            // Debug: Log the API request details
            error_log('Login - API request: users?' . $filter);
            
            // Debug: Log the user lookup result
            error_log('User lookup result: ' . json_encode($users));
            
            if (empty($users)) {
                $error = 'Invalid email or password.';
            } else {
                $user = $users[0];
                
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Password is correct, set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                    
                    // Reset login attempts on successful login
                    unset($_SESSION['login_attempts']);
                    unset($_SESSION['last_attempt']);
                    
                    // Record device login for account sharing prevention
                    $device_fingerprint = generate_device_fingerprint();
                    $device_recorded = record_device_login($user['user_id'], $device_fingerprint);
                    
                    // Store device verification status
                    $_SESSION['device_verified'] = true;
                    $_SESSION['device_fingerprint'] = $device_fingerprint;
                    
                    // Log device tracking information
                    error_log("Device tracking: Recorded login for user {$user['user_id']} with fingerprint {$device_fingerprint}");
                    
                    // Debug: Log session information after setting variables
                    error_log('Login - Session ID after login: ' . session_id());
                    error_log('Login - SESSION after login: ' . json_encode($_SESSION));
                    error_log('Login - User ID set: ' . $user['user_id']);
                    
                    // Check if there's a selected plan in the session
                    $selected_plan = isset($_SESSION['selected_plan']) ? $_SESSION['selected_plan'] : '';
                    
                    // Only give a free trial to brand new users with no subscription status
                    // AND who are not selecting a paid plan
                    if (empty($user['subscription_status']) && $selected_plan !== 'weekly' && $selected_plan !== 'monthly') {
                        // Set trial period (1 day)
                        $trial_start = date('c');
                        $trial_end = date('c', strtotime('+1 day'));
                        
                        // Update new user with free trial using direct API request
                        $updateData = [
                            'last_login' => date('c'),
                            'subscription_status' => 'trial',
                            'subscription_start_date' => $trial_start,
                            'subscription_end_date' => $trial_end
                        ];
                        $filter = "user_id=eq." . urlencode($user['user_id']);
                        supabaseRequest("users?$filter", 'PATCH', $updateData, true);
                        
                        // Debug: Log the update operation
                        error_log('Login - Updated user with trial: ' . $user['user_id']);
                    } else {
                        // Just update login time for existing users using direct API request
                        $updateData = [
                            'last_login' => date('c')
                        ];
                        $filter = "user_id=eq." . urlencode($user['user_id']);
                        supabaseRequest("users?$filter", 'PATCH', $updateData, true);
                        
                        // Debug: Log the update operation
                        error_log('Login - Updated login time for user: ' . $user['user_id']);
                    }
                    
                    // Store subscription info in session for easy access
                    $_SESSION['subscription_status'] = $user['subscription_status'] ?? '';
                    $_SESSION['subscription_end_date'] = $user['subscription_end_date'] ?? '';
                    
                    // Log subscription status for debugging
                    error_log("User login - Subscription status: {$user['subscription_status']}, End date: {$user['subscription_end_date']}");
                    
                    // Check if there's a selected plan in the session
                    if (isset($_SESSION['selected_plan'])) {
                        // User selected a plan before login, redirect to subscription page
                        $selected_plan = $_SESSION['selected_plan'];
                        header('Location: subscribe-direct.php?plan=' . $selected_plan);
                        exit;
                    }
                    // Check subscription status with proper handling for renewed accounts
                    else if (empty($user['subscription_status'])) {
                        // User has no subscription status at all - set to active instead of redirecting
                        $updateData = [
                            'subscription_status' => 'active',
                            'subscription_start_date' => date('c'),
                            'subscription_end_date' => date('c', strtotime('+30 days'))
                        ];
                        $filter = "user_id=eq." . urlencode($user['user_id']);
                        supabaseRequest("users?$filter", 'PATCH', $updateData, true);
                        error_log("Auto-activated account for user {$user['user_id']}");
                        // Continue to dashboard below
                    } 
                    else if ($user['subscription_status'] === 'pending') {
                        // User has started but not completed subscription process - set to active
                        $updateData = [
                            'subscription_status' => 'active',
                            'subscription_start_date' => date('c'),
                            'subscription_end_date' => date('c', strtotime('+30 days'))
                        ];
                        $filter = "user_id=eq." . urlencode($user['user_id']);
                        supabaseRequest("users?$filter", 'PATCH', $updateData, true);
                        error_log("Auto-activated pending account for user {$user['user_id']}");
                        // Continue to dashboard below
                    }
                    else if ($user['subscription_status'] === 'trial' && strtotime($user['subscription_end_date']) < time()) {
                        // Trial has expired, redirect to subscription page
                        header('Location: subscribe-direct.php?expired=1');
                        exit;
                    }
                    else if ($user['subscription_status'] === 'expired' && !isset($user['admin_renewed'])) {
                        // Subscription has expired and not renewed by admin
                        header('Location: subscribe-direct.php?expired=1');
                        exit;
                    }
                    else if ($user['subscription_status'] === 'active' || 
                             $user['subscription_status'] === 'trial' || 
                             $user['subscription_status'] === 'renewed' ||
                             isset($user['admin_renewed'])) {
                        // User has valid subscription - either active, trial, or renewed by admin
                        // Continue to dashboard below
                    }
                    else {
                        // Any other status, log it for debugging
                        error_log("Unhandled subscription status: {$user['subscription_status']} for user {$user['user_id']}");
                        // Redirect to dashboard anyway
                    }
                    
                    // If we got here, the user has a valid subscription
                    // Redirect based on user type
                    if ($_SESSION['user_type'] === 'parent') {
                        // Use the helper function to get linked children
                        $parent_id = $user['user_id'];
                        $children_data = get_linked_children($parent_id);
                        error_log('Login - Retrieved linked children using helper: ' . json_encode($children_data));
                        
                        // Format the linked students data
                        $linked_students = [];
                        foreach ($children_data as $child) {
                            // Make sure we have valid data before adding to the array
                            if (is_array($child) && isset($child['user_id'])) {
                                $linked_students[] = [
                                    'user_id' => $child['user_id'],
                                    'full_name' => isset($child['full_name']) ? $child['full_name'] : 'Unknown',
                                    'school_level' => isset($child['school_level']) ? $child['school_level'] : '',
                                    'linked_at' => date('c')
                                ];
                            }
                        }
                        
                        // Store the linked students in the session for future use
                        if (!empty($linked_students)) {
                            $_SESSION['linked_students'] = $linked_students;
                            $_SESSION['has_linked_children'] = true;
                            error_log('Login - Found ' . count($linked_students) . ' linked children');
                            
                            // Get progress data for each linked student
                            foreach ($linked_students as $key => $student) {
                                // Get progress data for this student
                                $progress_data = get_student_progress($student['user_id']);
                                $_SESSION['linked_students'][$key]['progress'] = $progress_data;
                                error_log('Login - Retrieved progress for student ' . $student['user_id'] . ': ' . json_encode($progress_data));
                            }
                        } else {
                            // Make sure these are unset if no linked children are found
                            unset($_SESSION['has_linked_children']);
                            unset($_SESSION['linked_students']);
                            unset($_SESSION['linked_children']);
                            error_log('Login - No linked children found');
                        }
                        
                        // Redirect parents to parent dashboard
                        header('Location: parent-dashboard.php');
                    } else {
                        // Redirect students to student dashboard
                        header('Location: dashboard.php');
                    }
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } catch (Exception $e) {
            error_log('Login Error: ' . $e->getMessage());
            $error = 'An error occurred during login. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="./assets/css/style.css">
    
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
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
    <!-- Device tracking script -->
    <script src="js/device-tracking.js"></script>
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
                    <a href="login.php" class="btn btn-outline-primary me-2 active">Login</a>
                    <a href="register.php" class="btn btn-primary">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h2 class="text-center mb-4">Login to Academic Ease</h2>
                            
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" action="login.php">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">Remember me</label>
                                </div>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Login</button>
                                </div>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="forgot-password.php">Forgot your password?</a>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="text-center">
                                <p class="mb-0">Don't have an account? <a href="register.php">Sign up</a></p>
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

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="./assets/js/main.js"></script>
    
    <?php if (!empty($user_type) && $user_type === 'parent' && !empty($email)): ?>
    <!-- Auto-login script for parents coming from registration -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set a small timeout to allow the page to fully load
            setTimeout(function() {
                // Fill in the password field with a message
                const passwordField = document.getElementById('password');
                if (passwordField) {
                    passwordField.placeholder = 'Enter your password to continue to parent dashboard';
                    passwordField.focus();
                }
                
                // Add a message to inform the user
                const formElement = document.querySelector('form');
                if (formElement) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-info';
                    alertDiv.innerHTML = '<strong>Welcome!</strong> Enter your password to access your parent dashboard.';
                    formElement.prepend(alertDiv);
                }
            }, 500);
        });
    </script>
    <?php endif; ?>
</body>
</html>
