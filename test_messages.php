<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

// Function to generate a UUID v4
function generate_uuid_v4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Test conversation ID - generate a proper UUID
$conversation_id = generate_uuid_v4();

echo "Testing message saving with conversation ID: $conversation_id\n";

// Create a test conversation
$user_id = generate_uuid_v4();
$conversation = [
    'conversation_id' => $conversation_id,
    'user_id' => $user_id,
    'title' => 'Test Conversation',
    'start_time' => date('Y-m-d H:i:s'),
    'last_message_time' => date('Y-m-d H:i:s')
];

echo "Creating test conversation...\n";
$endpoint = 'conversations';
$result = supabaseRequest($endpoint, 'POST', $conversation, true);

if (isset($result['error']) && $result['error'] === true) {
    echo "Error creating conversation: " . ($result['message'] ?? 'Unknown error') . "\n";
    exit;
} else {
    echo "Successfully created conversation\n";
}

// Create a test user message
$userMessage = [
    'conversation_id' => $conversation_id,
    'sender' => 'user',
    'content' => 'This is a test user message',
    'timestamp' => date('Y-m-d H:i:s'),
    'has_image' => false,
    'image_url' => null
];

echo "Saving user message...\n";
$endpoint = 'messages';
$result = supabaseRequest($endpoint, 'POST', $userMessage, true);

if (isset($result['error']) && $result['error'] === true) {
    echo "Error saving user message: " . ($result['message'] ?? 'Unknown error') . "\n";
} else {
    echo "Successfully saved user message\n";
}

// Create a test AI message
$aiMessage = [
    'conversation_id' => $conversation_id,
    'sender' => 'ai',
    'content' => 'This is a test AI response',
    'timestamp' => date('Y-m-d H:i:s')
];

echo "Saving AI message...\n";
$endpoint = 'messages';
$result = supabaseRequest($endpoint, 'POST', $aiMessage, true);

if (isset($result['error']) && $result['error'] === true) {
    echo "Error saving AI message: " . ($result['message'] ?? 'Unknown error') . "\n";
} else {
    echo "Successfully saved AI message\n";
}

// Retrieve messages for the conversation
echo "Retrieving messages...\n";
$endpoint = 'messages?conversation_id=eq.' . urlencode($conversation_id) . '&order=timestamp.asc';
$messages = supabaseRequest($endpoint, 'GET', null, true);

if (isset($messages['error']) && $messages['error'] === true) {
    echo "Error retrieving messages: " . ($messages['message'] ?? 'Unknown error') . "\n";
} else {
    echo "Retrieved " . count($messages) . " messages\n";
    
    foreach ($messages as $index => $message) {
        echo "Message " . ($index + 1) . ":\n";
        echo "  Sender: " . ($message['sender'] ?? 'Unknown') . "\n";
        echo "  Content: " . ($message['content'] ?? 'No content') . "\n";
        echo "  Timestamp: " . ($message['timestamp'] ?? 'Unknown time') . "\n";
        echo "\n";
    }
}
?>
