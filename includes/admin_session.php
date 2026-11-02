<?php
/**
 * Admin session security handler
 * Provides consistent security checks for all admin pages
 */

// Start session with secure settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
        'cookie_secure' => isset($_SERVER['HTTPS']), // Use secure cookies when on HTTPS
        'cookie_samesite' => 'Lax'    // Prevent CSRF attacks
    ]);
}

// Check if admin is logged in
function check_admin_login() {
    // Check if admin is logged in
    if (!isset($_SESSION['admin_id'])) {
        // Check alternative admin authentication methods based on memory
        $admin_authenticated = false;
        
        // Method 1: Check JSON file
        $admin_users_file = __DIR__ . '/../data/admin_users.json';
        if (file_exists($admin_users_file)) {
            $admin_users = json_decode(file_get_contents($admin_users_file), true);
            if (isset($_SESSION['user_id'])) {
                foreach ($admin_users as $admin) {
                    if ($admin['user_id'] == $_SESSION['user_id']) {
                        $_SESSION['admin_id'] = $_SESSION['user_id'];
                        $_SESSION['admin_name'] = $_SESSION['name'] ?? 'Admin';
                        $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
                        $_SESSION['admin_last_activity'] = time();
                        $admin_authenticated = true;
                        break;
                    }
                }
            }
        }
        
        if (!$admin_authenticated) {
            // Redirect to login page
            header('Location: ' . get_admin_url('login.php'));
            exit;
        }
    }
    
    // Session timeout check (30 minutes)
    if (isset($_SESSION['admin_last_activity'])) {
        $inactive_time = time() - $_SESSION['admin_last_activity'];
        $timeout_limit = 1800; // 30 minutes
        
        if ($inactive_time > $timeout_limit) {
            // Log session timeout
            $admin_id = $_SESSION['admin_id'] ?? 'unknown';
            $admin_email = $_SESSION['admin_email'] ?? 'unknown';
            $ip = $_SERVER['REMOTE_ADDR'];
            $inactive_minutes = round($inactive_time / 60);
            
            error_log("Admin session timeout: Admin ID: {$admin_id}, Email: {$admin_email}, IP: {$ip}, Inactive for {$inactive_minutes} minutes");
            
            // Session expired, log out
            session_unset();
            session_destroy();
            
            // Start a new session to pass the expired message
            session_start();
            $_SESSION['timeout_message'] = "Your session has expired due to inactivity. Please log in again.";
            
            header('Location: ' . get_admin_url('login.php') . '?expired=1');
            exit;
        }
    }
    
    // Session hijacking prevention - check if IP or user agent changed
    if (isset($_SESSION['admin_ip']) && isset($_SESSION['admin_user_agent'])) {
        if ($_SESSION['admin_ip'] !== $_SERVER['REMOTE_ADDR'] || 
            $_SESSION['admin_user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            // Potential session hijacking, log out
            error_log("Potential admin session hijacking detected for user ID: {$_SESSION['admin_id']}");
            session_unset();
            session_destroy();
            header('Location: ' . get_admin_url('login.php') . '?security=1');
            exit;
        }
    }
    
    // Update last activity time
    $_SESSION['admin_last_activity'] = time();
    
    return true;
}

// Check if admin has specific role permissions
function check_admin_permission($required_role = 'admin') {
    // First check if logged in
    check_admin_login();
    
    // Super admin has all permissions
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin') {
        return true;
    }
    
    // Check specific role
    if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== $required_role) {
        // Redirect to dashboard with permission error
        header('Location: ' . get_admin_url('index.php') . '?permission=0');
        exit;
    }
    
    return true;
}

// Get admin URL helper
function get_admin_url($path = '') {
    $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url .= $_SERVER['HTTP_HOST'];
    $base_url .= dirname($_SERVER['PHP_SELF']);
    
    // If we're already in the admin directory, don't append it again
    if (strpos($base_url, '/admin') === false) {
        $base_url .= '/admin';
    }
    
    if (!empty($path)) {
        // Make sure we don't double up on slashes
        if (substr($base_url, -1) === '/' && substr($path, 0, 1) === '/') {
            $path = substr($path, 1);
        }
        $base_url .= '/' . $path;
    }
    
    return $base_url;
}
