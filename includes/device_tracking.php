<?php
/**
 * Device Tracking Helper
 * 
 * Functions to track user devices and prevent account sharing
 */

/**
 * Generate a device fingerprint based on user agent and other available data
 */
function generate_device_fingerprint() {
    $fingerprint = [];
    
    // Browser and OS information
    $fingerprint['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // IP address (hashed for privacy)
    $fingerprint['ip_hash'] = md5($_SERVER['REMOTE_ADDR'] ?? '');
    
    // Screen resolution and color depth if available via JavaScript
    if (isset($_SESSION['screen_width']) && isset($_SESSION['screen_height'])) {
        $fingerprint['screen'] = $_SESSION['screen_width'] . 'x' . $_SESSION['screen_height'];
    }
    
    // Time zone if available
    if (isset($_SESSION['timezone'])) {
        $fingerprint['timezone'] = $_SESSION['timezone'];
    }
    
    // Language settings
    $fingerprint['language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    
    // Generate a unique hash from all collected data
    return md5(json_encode($fingerprint));
}

/**
 * Record a device login for a user
 */
function record_device_login($user_id, $device_fingerprint = null) {
    if ($device_fingerprint === null) {
        $device_fingerprint = generate_device_fingerprint();
    }
    
    // Current time
    $login_time = date('c');
    
    // Create device session data
    $device_data = [
        'device_fingerprint' => $device_fingerprint,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'login_time' => $login_time,
        'last_activity' => $login_time,
        'is_active' => true
    ];
    
    // Store the current device in session
    $_SESSION['device_fingerprint'] = $device_fingerprint;
    $_SESSION['device_verified'] = true;
    
    // Store device data in database
    try {
        // First check if this device already exists for this user
        $filter = "user_id=eq." . urlencode($user_id) . "&device_fingerprint=eq." . urlencode($device_fingerprint);
        $existing_devices = supabaseRequest("user_devices?$filter", 'GET', null, true);
        
        if (!empty($existing_devices)) {
            // Update existing device record
            $device_data['last_activity'] = $login_time;
            $device_data['is_active'] = true;
            supabaseRequest("user_devices?$filter", 'PATCH', $device_data, true);
        } else {
            // Add new device record
            $device_data['user_id'] = $user_id;
            supabaseRequest("user_devices", 'POST', $device_data, true);
            
            // Check if user has too many active devices
            check_and_limit_devices($user_id);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording device login: " . $e->getMessage());
        return false;
    }
}

/**
 * Check and limit the number of active devices for a user
 */
function check_and_limit_devices($user_id, $max_devices = 2) {
    try {
        // Get all active devices for this user, ordered by last activity (oldest first)
        $filter = "user_id=eq." . urlencode($user_id) . "&is_active=eq.true&order=last_activity.asc";
        $active_devices = supabaseRequest("user_devices?$filter", 'GET', null, true);
        
        // If user has more than max allowed devices, deactivate the oldest ones
        if (count($active_devices) > $max_devices) {
            $devices_to_deactivate = array_slice($active_devices, 0, count($active_devices) - $max_devices);
            
            foreach ($devices_to_deactivate as $device) {
                $device_filter = "id=eq." . urlencode($device['id']);
                supabaseRequest("user_devices?$device_filter", 'PATCH', ['is_active' => false], true);
                
                // Log the deactivation
                error_log("Deactivated device for user {$user_id} due to too many active devices");
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error limiting devices: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify that the current device matches the one stored in session
 */
function verify_device() {
    // If no device fingerprint in session, generate one
    if (!isset($_SESSION['device_fingerprint'])) {
        return false;
    }
    
    // Generate current fingerprint and compare
    $current_fingerprint = generate_device_fingerprint();
    $stored_fingerprint = $_SESSION['device_fingerprint'];
    
    // If fingerprints don't match, session might be hijacked
    if ($current_fingerprint !== $stored_fingerprint) {
        error_log("Device fingerprint mismatch for user ID: {$_SESSION['user_id']}");
        return false;
    }
    
    return true;
}

/**
 * Update the database schema to support device tracking
 * Run this once to create the necessary table
 */
function setup_device_tracking() {
    // SQL to create user_devices table
    $sql = "
    CREATE TABLE IF NOT EXISTS user_devices (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        device_fingerprint VARCHAR(255) NOT NULL,
        user_agent TEXT,
        ip_address VARCHAR(45),
        login_time TIMESTAMP WITH TIME ZONE,
        last_activity TIMESTAMP WITH TIME ZONE,
        is_active BOOLEAN DEFAULT TRUE,
        UNIQUE(user_id, device_fingerprint)
    );
    ";
    
    try {
        // Execute the SQL via Supabase
        $result = supabaseRequest("rest/v1/rpc/execute_sql", 'POST', ['sql' => $sql], true);
        return true;
    } catch (Exception $e) {
        error_log("Error setting up device tracking: " . $e->getMessage());
        return false;
    }
}
