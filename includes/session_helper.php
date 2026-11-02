<?php
/**
 * Session Helper Functions
 * 
 * This file contains functions to manage user sessions, including timeout checks
 * and security validations for students and parents.
 */

// Check if user is logged in and session is valid
function check_user_login() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
            'cookie_secure' => isset($_SERVER['HTTPS']), // Use secure cookies when on HTTPS
            'cookie_samesite' => 'Lax'    // Prevent CSRF attacks
        ]);
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        // Not logged in, redirect to login page
        header('Location: login.php');
        exit;
    }
    
    // Verify device fingerprint to prevent account sharing
    if (isset($_SESSION['device_fingerprint']) && function_exists('verify_device')) {
        if (!verify_device()) {
            // Device mismatch detected - potential account sharing
            $user_id = $_SESSION['user_id'] ?? 'unknown';
            error_log("Device verification failed for user {$user_id} - possible account sharing detected");
            
            // Log out the user
            session_unset();
            session_destroy();
            
            // Start a new session to pass the message
            session_start();
            $_SESSION['error_message'] = "Your session was terminated due to suspicious activity. If you believe this is an error, please contact support.";
            
            header('Location: login.php?security=device');
            exit;
        }
    }
    
    // Session timeout check (30 minutes)
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        $timeout_limit = 1800; // 30 minutes
        
        if ($inactive_time > $timeout_limit) {
            // Log session timeout
            $user_id = $_SESSION['user_id'] ?? 'unknown';
            $user_name = $_SESSION['user_name'] ?? 'unknown';
            $ip = $_SERVER['REMOTE_ADDR'];
            $inactive_minutes = round($inactive_time / 60);
            
            error_log("User session timeout: User ID: {$user_id}, Name: {$user_name}, IP: {$ip}, Inactive for {$inactive_minutes} minutes");
            
            // Session expired, log out
            session_unset();
            session_destroy();
            
            // Start a new session to pass the expired message
            session_start();
            $_SESSION['timeout_message'] = "Your session has expired due to inactivity. Please log in again.";
            
            header('Location: login.php?expired=1');
            exit;
        }
    }
    
    // Session hijacking prevention - check if IP or user agent changed
    if (isset($_SESSION['user_ip']) && isset($_SESSION['user_agent'])) {
        if ($_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR'] || 
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            // Potential session hijacking, log out
            error_log("Potential user session hijacking detected for user ID: {$_SESSION['user_id']}");
            session_unset();
            session_destroy();
            header('Location: login.php?security=1');
            exit;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    
    return true;
}

// Get base URL helper
function get_base_url($path = '') {
    $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $base_url .= $_SERVER['HTTP_HOST'];
    $base_url .= dirname($_SERVER['PHP_SELF']);
    
    // Remove 'public' from the path if it's there
    $base_url = rtrim($base_url, '/');
    
    // Add the requested path
    if (!empty($path)) {
        $base_url .= '/' . ltrim($path, '/');
    }
    
    return $base_url;
}
