<?php
// Load environment variables
require_once __DIR__ . '/includes/config.php';

// Load OpenRouterClient
require_once __DIR__ . '/includes/OpenRouterClient.php';

// Get API key from environment
$api_key = $_ENV['OPENROUTER_API_KEY'] ?? $_SERVER['OPENROUTER_API_KEY'] ?? null;

echo "API Key available: " . ($api_key ? "Yes (length: " . strlen($api_key) . ")" : "No") . "\n";

if (!$api_key) {
    die("No API key found. Please check your .env file.\n");
}

// Create OpenRouterClient instance
$client = new OpenRouterClient($api_key); // Initialize with API key

// Test message
$messages = [
    [
        'role' => 'system',
        'content' => 'You are a helpful assistant for Academic Ease, an educational platform.'
    ],
    [
        'role' => 'user',
        'content' => 'Hello, can you help me with a simple math problem?'
    ]
];

// Options
$options = [
    'model' => 'anthropic/claude-3-haiku', // Using a simple model for testing
    'temperature' => 0.7,
    'max_tokens' => 100
];

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    echo "Sending test request to OpenRouter API...\n";
    $response = $client->generateChatCompletion($messages, $options);
    echo "Response received:\n";
    echo $response . "\n";
    echo "Test completed successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    echo "Test failed.\n";
}
?>
