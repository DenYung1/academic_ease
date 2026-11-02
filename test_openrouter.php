<?php
/**
 * OpenRouter API Test Script
 * 
 * This script tests the OpenRouter API integration to verify it's working correctly.
 * It will attempt to send a simple request and display the response.
 */

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/OpenRouterClient.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    // Try multiple methods to load the .env file
    $env_content = file_get_contents(__DIR__ . '/.env');
    $env_loaded = false;
    
    // Method 1: Try parse_ini_file
    $env = @parse_ini_file(__DIR__ . '/.env');
    if ($env) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
        $env_loaded = true;
    } 
    // Method 2: Parse line by line
    else {
        $lines = explode("\n", $env_content);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            // Look for KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove quotes if present
                if (preg_match('/^["\'](.+)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $env_loaded = true;
            }
        }
    }
}

// Load AI configuration
$ai_config = include 'includes/ai_config.php';

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Output HTML header
echo "<h1>OpenRouter API Test</h1>";

// Check if API key is set
$api_key = $ai_config['openrouter']['api_key'] ?? getenv('OPENROUTER_API_KEY');

if (empty($api_key) || $api_key === 'your_openrouter_api_key_here') {
    echo "<p>API Key Status: <span style='color: red;'>NOT CONFIGURED</span><p>Please add your OpenRouter API key to the .env file.</p>";
    exit;
}

// Mask the API key for display
$masked_key = substr($api_key, 0, 4) . '...' . substr($api_key, -4);
echo "<p>API Key Status: <span style='color: green;'>CONFIGURED</span> ($masked_key)</p>";

try {
    // Initialize OpenRouter client
    $client = new OpenRouterClient($api_key);
    
    // Test a chat completion (this is what the AI assistant uses)
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are an AI assistant for Academic Ease, an educational platform for students in Ghana. '
                      . 'You provide helpful, accurate, and educational responses following the Ghana curriculum. '
                      . 'Focus on explaining concepts rather than solving homework directly. '
                      . 'Be friendly, clear, and provide step-by-step explanations when needed.'
        ],
        [
            'role' => 'user',
            'content' => 'What is the capital of Ghana and why is it significant?'
        ]
    ];
    
    // Set options
    $options = [
        'temperature' => 0.7,
        'max_tokens' => 50
    ];
    
    echo "<p>Sending request to OpenRouter API...</p>";
    
    // Start timer
    $start_time = microtime(true);
    
    // Make the API call
    $response = $client->generateChatCompletion($messages, $options);
    
    // End timer
    $end_time = microtime(true);
    $execution_time = ($end_time - $start_time);
    
    echo "<p>Request completed in " . round($execution_time, 2) . " seconds.</p>";
    
    echo "<h2>Response:</h2>";
    echo "<div style='background-color: #f0f0f0; padding: 15px; border-radius: 5px;'>";
    echo "<p>" . nl2br(htmlspecialchars($response)) . "</p>";
    echo "</div>";
    
    echo "<h2>Test Result: <span style='color: green;'>SUCCESS</span></h2>";
    echo "<p>The OpenRouter API is working correctly!</p>";
    
} catch (Exception $e) {
    echo "<h2>Test Result: <span style='color: red;'>FAILED</span></h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Provide troubleshooting guidance
    echo "<h3>Troubleshooting:</h3>";
    echo "<ol>";
    echo "<li>Check that your OpenRouter API key is correctly set in the .env file</li>";
    echo "<li>Verify that your server can make outbound HTTP requests</li>";
    echo "<li>Check if you've hit rate limits on your OpenRouter account</li>";
    echo "<li>Look at the server logs for more detailed error information</li>";
    echo "</ol>";
}
?>
