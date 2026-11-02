<?php
// Include configuration and database connection
require_once '../../includes/config.php';

// Start session with secure settings
session_start([
    'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
    'cookie_secure' => isset($_SERVER['HTTPS']), // Use secure cookies when on HTTPS
    'cookie_samesite' => 'Lax'    // Prevent CSRF attacks
]);

// Check if admin is already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

// Initialize variables
$error = '';
$success = '';
$login_attempts = isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] : 0;
$last_attempt = isset($_SESSION['last_attempt']) ? $_SESSION['last_attempt'] : 0;

// Check for session timeout message
if (isset($_SESSION['timeout_message'])) {
    $error = $_SESSION['timeout_message'];
    unset($_SESSION['timeout_message']);
} else if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $error = "Your session has expired due to inactivity. Please log in again.";
} else if (isset($_GET['security']) && $_GET['security'] == 1) {
    $error = "Your session was terminated for security reasons. Please log in again.";
}

// Implement rate limiting to prevent brute force attacks
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

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Standard database authentication is now working correctly
    // Emergency credentials have been removed for security
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        // Initialize authentication flag
        $authenticated = false;
        $stored_hash = null;
        
        // STEP 1: PRIORITIZE SUPABASE AUTHENTICATION - Check for user's own credentials
        error_log("Attempting login with email: {$email}");
        
        // Get admin data from database with secure error handling
        if (!empty($email)) {
            try {
                require_once '../../includes/db_connect.php';

                // Match email addresses case-insensitively while preserving an exact match.
                $encodedEmail = urlencode($email);
                $endpoint = "admin_users?email=ilike.{$encodedEmail}";
                error_log("Admin Login - Querying endpoint: {$endpoint}");
                
                // Dump Supabase connection details (without sensitive info)
                error_log("Admin Login - Supabase URL: " . (defined('SUPABASE_URL') ? SUPABASE_URL : 'Not defined'));
                error_log("Admin Login - Supabase Key defined: " . (defined('SUPABASE_KEY') && !empty(SUPABASE_KEY) ? 'Yes' : 'No'));
                error_log("Admin Login - Supabase Service Key defined: " . (defined('SUPABASE_SERVICE_KEY') && !empty(SUPABASE_SERVICE_KEY) ? 'Yes' : 'No'));
                
                $admin_data = supabaseRequest($endpoint, 'GET', null, true);
                error_log("Admin Login - Query result: " . json_encode($admin_data));

                if (isset($admin_data['error']) && $admin_data['error'] === true) {
                    throw new RuntimeException(
                        'Admin lookup failed (HTTP ' . ($admin_data['status'] ?? 'unknown') . '): ' .
                        ($admin_data['message'] ?? 'Unknown Supabase error')
                    );
                }
                
                if (!empty($admin_data)) {
                    $admin = $admin_data[0];
                    error_log("Admin Login - Found admin: " . $admin['email']);
                    
                    // Dump all available columns for debugging
                    error_log("Admin Login - Available columns: " . implode(", ", array_keys($admin)));
                    
                    // Support both schemas used by this project.
                    $stored_hash = $admin['password'] ?? $admin['password_hash'] ?? null;
                    if (!empty($stored_hash)) {
                        error_log("Admin Login - Using password column: " . substr($stored_hash, 0, 10) . '...');
                    } else {
                        error_log("Admin Login - No password found in admin record");
                        // Log all available columns for debugging
                        error_log("Admin Login - Available columns: " . implode(", ", array_keys($admin)));
                    }
                    
                    if ($stored_hash && password_verify($password, $stored_hash)) {
                        error_log("Admin Login - Password verified successfully");
                        $authenticated = true;
                        
                        // Set session variables
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['admin_last_activity'] = time();
                        $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'];
                        $_SESSION['admin_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
                        
                        // Update last login time
                        supabaseRequest("admin_users?admin_id=eq.{$admin['admin_id']}", 'PATCH', [
                            'last_login' => date('c')
                        ], true);
                    } else {
                        error_log("Admin Login - Password verification failed for: {$email}");
                    }
                } else {
                error_log("Admin Login - No admin found with email: {$email}");
                }
            } catch (Throwable $e) {
                error_log("Admin Login - Error during authentication: " . $e->getMessage());
            }
            
            // If not authenticated, increment login attempts
            if (!$authenticated) {
                $_SESSION['login_attempts'] = ($login_attempts + 1);
                $_SESSION['last_attempt'] = time();
                error_log("Admin Login - Failed attempt #{$_SESSION['login_attempts']} for: {$email} from IP {$_SERVER['REMOTE_ADDR']}");
                $error = 'Invalid email or password.';
            } else {
                // Reset login attempts on successful login
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt']);
                
                // Generate new session ID for security
                session_regenerate_id(true);
                
                // Log successful login
                error_log("Admin Login - Successful login: {$_SESSION['admin_email']} from IP {$_SERVER['REMOTE_ADDR']}");
                
                // Redirect to admin dashboard
                header('Location: index.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .login-card {
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .login-header {
            background-color: #343a40;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-color: #86b7fe;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card bg-white">
            <div class="login-header">
                <h3 class="m-0">Academic Ease</h3>
                <p class="m-0">Admin Panel</p>
            </div>
            <div class="login-body">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </div>
                </form>
                
                <div class="mt-4 text-center">
                    <a href="../index.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Website
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
