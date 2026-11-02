<?php
/**
 * Supabase Constraint Fixer - Academic Ease
 * This script will fix the foreign key constraint issue by:
 * 1. First finding all conversations for a user
 * 2. Then properly deleting messages in those conversations
 * 3. Then deleting the conversations
 * 4. Finally attempting to delete the user
 */

require_once __DIR__ . '/includes/config.php';

// Check command line arguments
if ($argc < 2) {
    echo "Usage: php fix_constraints.php <email>\n";
    exit(1);
}

$email = $argv[1];

// Helper function for making Supabase API requests with SSL verification disabled
function supabaseApiRequest($endpoint, $method = 'GET', $data = null) {
    $api_url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $key = SUPABASE_SERVICE_KEY ?: SUPABASE_KEY;
    
    echo "API Request: $method $api_url\n";
    
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Prefer: return=representation'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Disable SSL verification since we're seeing certificate issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $status\n";
    if ($error) {
        echo "Error: $error\n";
    }
    
    return [
        'status' => $status,
        'body' => $response ? json_decode($response, true) : null,
        'error' => $error
    ];
}

// Step 1: Find the user ID
echo "\n[1] Looking up user by email: $email\n";
$response = supabaseApiRequest("users?email=eq." . urlencode($email));

if ($response['status'] !== 200 || empty($response['body'])) {
    echo "Error finding user with email $email\n";
    exit(1);
}

$user = $response['body'][0];
$user_id = $user['user_id'];
echo "Found user ID: $user_id\n";

// Step 2: Find all conversations for this user
echo "\n[2] Finding conversations for user ID: $user_id\n";
$response = supabaseApiRequest("conversations?user_id=eq.$user_id");

if ($response['status'] !== 200) {
    echo "Error finding conversations\n";
    exit(1);
}

$conversations = $response['body'];
$conversation_count = count($conversations);
echo "Found $conversation_count conversations\n";

if ($conversation_count > 0) {
    // Step 3: Delete messages from each conversation
    echo "\n[3] Deleting messages from conversations...\n";
    $total_messages_deleted = 0;
    
    foreach ($conversations as $conversation) {
        $conversation_id = $conversation['conversation_id'];
        echo "  Processing conversation ID: $conversation_id\n";
        
        // Delete messages in this conversation
        $response = supabaseApiRequest("messages?conversation_id=eq.$conversation_id", "DELETE");
        
        if ($response['status'] >= 200 && $response['status'] < 300) {
            $count = is_array($response['body']) ? count($response['body']) : 0;
            echo "  Deleted $count messages\n";
            $total_messages_deleted += $count;
        } else {
            echo "  Error deleting messages: " . json_encode($response['body']) . "\n";
        }
    }
    
    echo "Total messages deleted: $total_messages_deleted\n";
    
    // Step 4: Now delete the conversations
    echo "\n[4] Deleting conversations for user ID: $user_id\n";
    $response = supabaseApiRequest("conversations?user_id=eq.$user_id", "DELETE");
    
    if ($response['status'] >= 200 && $response['status'] < 300) {
        $count = is_array($response['body']) ? count($response['body']) : 0;
        echo "Deleted $count conversations\n";
    } else {
        echo "Error deleting conversations: " . json_encode($response['body']) . "\n";
    }
}

// Step 5: Delete subscriptions
echo "\n[5] Deleting subscriptions for user ID: $user_id\n";
$response = supabaseApiRequest("subscriptions?user_id=eq.$user_id", "DELETE");
if ($response['status'] >= 200 && $response['status'] < 300) {
    $count = is_array($response['body']) ? count($response['body']) : 0;
    echo "Deleted $count subscriptions\n";
}

// Step 6: Delete parent-child relationships
echo "\n[6] Deleting parent-child relationships for user ID: $user_id\n";
$response = supabaseApiRequest("parent_child?parent_id=eq.$user_id", "DELETE");
if ($response['status'] >= 200 && $response['status'] < 300) {
    $count = is_array($response['body']) ? count($response['body']) : 0;
    echo "Deleted $count parent relationships\n";
}

$response = supabaseApiRequest("parent_child?child_id=eq.$user_id", "DELETE");
if ($response['status'] >= 200 && $response['status'] < 300) {
    $count = is_array($response['body']) ? count($response['body']) : 0;
    echo "Deleted $count child relationships\n";
}

// Step 7: Delete user progress, study plans, and study schedules
echo "\n[7] Deleting user progress, study plans, and study schedules for user ID: $user_id\n";
$response = supabaseApiRequest("user_progress?user_id=eq.$user_id", "DELETE");
if ($response['status'] >= 200 && $response['status'] < 300) {
    $count = is_array($response['body']) ? count($response['body']) : 0;
    echo "Deleted $count progress records\n";
}

// Delete study schedules (important for foreign key constraint)
$response = supabaseApiRequest("study_schedules?user_id=eq.$user_id", "DELETE");
if ($response['status'] >= 200 && $response['status'] < 300) {
    $count = is_array($response['body']) ? count($response['body']) : 0;
    echo "Deleted $count study schedules\n";
}

// Delete study plans (important for foreign key constraint)
$response = supabaseApiRequest("study_plans?user_id=eq.$user_id", "DELETE");
if ($response['status'] >= 200 && $response['status'] < 300) {
    $count = is_array($response['body']) ? count($response['body']) : 0;
    echo "Deleted $count study plans\n";
}

// Step 8: Finally try to delete the user
echo "\n[8] Deleting user account for user ID: $user_id\n";
$response = supabaseApiRequest("users?user_id=eq.$user_id", "DELETE");

if ($response['status'] >= 200 && $response['status'] < 300) {
    echo "\nSUCCESS: User account with email $email (ID: $user_id) has been completely deleted!\n";
} else {
    echo "\nERROR: Failed to delete user account. Response: " . json_encode($response['body']) . "\n";
    
    // Check what might still be referencing this user
    echo "\nChecking what might still be referencing this user...\n";
    
    $tables = [
        'conversations',
        'subscriptions',
        'parent_child',
        'user_progress',
        'quiz_attempts',
        'student_notes',
        'study_plans',
        'study_schedules'
    ];
    
    foreach ($tables as $table) {
        $response = supabaseApiRequest($table . "?user_id=eq.$user_id");
        if ($response['status'] === 200 && !empty($response['body'])) {
            echo "Table '$table' still has " . count($response['body']) . " records for this user\n";
        }
    }
}