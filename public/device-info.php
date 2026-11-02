<?php
/**
 * Device Information Endpoint
 * 
 * Receives device information from JavaScript and stores it in the session
 */

// Include configuration
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
        'cookie_secure' => isset($_SERVER['HTTPS']), // Use secure cookies when on HTTPS
        'cookie_samesite' => 'Lax'    // Prevent CSRF attacks
    ]);
}

// Get JSON data from request
$json_data = file_get_contents('php://input');
$device_data = json_decode($json_data, true);

// Validate the data
if (!empty($device_data) && is_array($device_data)) {
    // Store device information in session
    if (isset($device_data['screen_width'])) {
        $_SESSION['screen_width'] = $device_data['screen_width'];
    }
    
    if (isset($device_data['screen_height'])) {
        $_SESSION['screen_height'] = $device_data['screen_height'];
    }
    
    if (isset($device_data['timezone'])) {
        $_SESSION['timezone'] = $device_data['timezone'];
    }
    
    // If user is logged in, update their device record
    if (isset($_SESSION['user_id']) && isset($_SESSION['device_fingerprint'])) {
        try {
            // Update the device record with additional information
            $filter = "user_id=eq." . urlencode($_SESSION['user_id']) . "&device_fingerprint=eq." . urlencode($_SESSION['device_fingerprint']);
            
            $update_data = [
                'screen_resolution' => $device_data['screen_width'] . 'x' . $device_data['screen_height'],
                'timezone' => $device_data['timezone'] ?? '',
                'platform' => $device_data['platform'] ?? '',
                'last_activity' => date('c')
            ];
            
            supabaseRequest("user_devices?$filter", 'PATCH', $update_data, true);
            
            // Log the update
            error_log("Updated device information for user {$_SESSION['user_id']}");
        } catch (Exception $e) {
            error_log("Error updating device information: " . $e->getMessage());
        }
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Device information recorded']);
} else {
    // Return error response
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid data format']);
}
