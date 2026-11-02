<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

echo "Checking messages table structure...\n";

// Check if the messages table exists
$endpoint = 'messages?limit=1';
$result = supabaseRequest($endpoint, 'GET', null, true);

if (isset($result['error']) && $result['error'] === true) {
    echo "Error accessing messages table: " . ($result['message'] ?? 'Unknown error') . "\n";
    exit;
}

echo "Messages table exists and is accessible.\n";

// Check the structure of the messages table
echo "\nRetrieving a sample message to check structure...\n";
if (empty($result)) {
    echo "No messages found in the table.\n";
} else {
    $message = $result[0];
    echo "Sample message structure:\n";
    foreach ($message as $key => $value) {
        echo "  $key: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }
}

// Check for missing fields that might be causing issues
$required_fields = ['id', 'conversation_id', 'sender', 'content', 'timestamp'];
$missing_fields = [];

if (!empty($result)) {
    $message = $result[0];
    foreach ($required_fields as $field) {
        if (!isset($message[$field])) {
            $missing_fields[] = $field;
        }
    }
}

if (!empty($missing_fields)) {
    echo "\nWARNING: The following required fields are missing from the messages table:\n";
    foreach ($missing_fields as $field) {
        echo "  - $field\n";
    }
    echo "\nYou may need to add these fields to your database schema.\n";
} else {
    echo "\nAll required fields are present in the messages table.\n";
}

// Test saving a message
echo "\nTesting message saving functionality...\n";

// Get a valid conversation ID from the database
$endpoint = 'conversations?limit=1';
$conversations = supabaseRequest($endpoint, 'GET', null, true);

if (isset($conversations['error']) && $conversations['error'] === true) {
    echo "Error accessing conversations table: " . ($conversations['message'] ?? 'Unknown error') . "\n";
    exit;
}

if (empty($conversations)) {
    echo "No conversations found in the database. Cannot test message saving.\n";
    exit;
}

$conversation_id = $conversations[0]['conversation_id'];
echo "Using conversation ID: $conversation_id\n";

// Create a test message
$test_message = [
    'conversation_id' => $conversation_id,
    'sender' => 'user',
    'content' => 'This is a test message from check_messages_table.php',
    'timestamp' => date('Y-m-d H:i:s'),
    'has_image' => false,
    'image_url' => null
];

echo "Saving test message...\n";
$endpoint = 'messages';
$result = supabaseRequest($endpoint, 'POST', $test_message, true);

if (isset($result['error']) && $result['error'] === true) {
    echo "Error saving test message: " . ($result['message'] ?? 'Unknown error') . "\n";
    
    // Check for specific error types
    if (strpos($result['message'], 'violates foreign key constraint') !== false) {
        echo "This appears to be a foreign key constraint error. Make sure the conversation_id exists in the conversations table.\n";
    } elseif (strpos($result['message'], 'column') !== false && strpos($result['message'], 'does not exist') !== false) {
        echo "This appears to be a missing column error. Check your database schema.\n";
    }
} else {
    echo "Successfully saved test message.\n";
    
    // Verify the message was saved by retrieving it
    echo "\nVerifying message was saved...\n";
    $endpoint = 'messages?conversation_id=eq.' . urlencode($conversation_id) . '&order=timestamp.desc&limit=1';
    $messages = supabaseRequest($endpoint, 'GET', null, true);
    
    if (isset($messages['error']) && $messages['error'] === true) {
        echo "Error retrieving saved message: " . ($messages['message'] ?? 'Unknown error') . "\n";
    } elseif (empty($messages)) {
        echo "No messages found after saving. This suggests the message was not saved correctly.\n";
    } else {
        $latest_message = $messages[0];
        echo "Retrieved latest message:\n";
        echo "  Sender: " . ($latest_message['sender'] ?? 'Unknown') . "\n";
        echo "  Content: " . ($latest_message['content'] ?? 'No content') . "\n";
        echo "  Timestamp: " . ($latest_message['timestamp'] ?? 'Unknown time') . "\n";
        
        if ($latest_message['content'] === $test_message['content']) {
            echo "\nSUCCESS: The test message was saved and retrieved correctly!\n";
        } else {
            echo "\nWARNING: The retrieved message content does not match the test message content.\n";
        }
    }
}

echo "\nDone checking messages table.\n";
?>
