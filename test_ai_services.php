<?php
/**
 * Academic Ease - AI Services Test Script
 * 
 * This script tests all available AI services including OpenRouter, Ollama, and any fallback mechanisms.
 * It follows the educational approach that focuses on explaining concepts rather than solving homework directly.
 */

// Set error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

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
    
    if ($env_loaded) {
        echo "<p style='color: green;'>âœ“ Successfully loaded environment variables from .env file</p>";
    } else {
        echo "<p style='color: orange;'>âš ï¸ .env file exists but couldn't be parsed</p>";
    }
} else {
    echo "<p style='color: red;'>âœ— .env file not found</p>";
}

// Function to check if a file exists and is readable
function checkFile($path, $description) {
    if (file_exists($path) && is_readable($path)) {
        echo "<p style='color: green;'>âœ“ $description found</p>";
        return true;
    } else {
        echo "<p style='color: red;'>âœ— $description not found</p>";
        return false;
    }
}

// Check for required files
echo "<h2>File System Checks</h2>";
$openRouterClientExists = checkFile('includes/OpenRouterClient.php', 'OpenRouter Client');
$aiConfigExists = checkFile('includes/ai_config.php', 'AI Configuration');
$openRouterHelperExists = checkFile('includes/openrouter_helper.php', 'OpenRouter Helper');

// Load AI configuration
if ($aiConfigExists) {
    $ai_config = include 'includes/ai_config.php';
    echo "<p>Loaded AI configuration</p>";
}

// Check API keys
echo "<h2>API Key Checks</h2>";

// Check OpenRouter API key
if (isset($ai_config['openrouter']['api_key'])) {
    $openrouter_key = $ai_config['openrouter']['api_key'];
    if (!empty($openrouter_key) && $openrouter_key !== 'your_openrouter_api_key_here') {
        echo "<p style='color: green;'>âœ“ OpenRouter API key is configured</p>";
        $masked_key = substr($openrouter_key, 0, 4) . '...' . substr($openrouter_key, -4);
        echo "<p>Key: $masked_key</p>";
        $openrouter_configured = true;
    } else {
        echo "<p style='color: red;'>âœ— OpenRouter API key is not configured</p>";
        $openrouter_configured = false;
    }
} else {
    echo "<p style='color: red;'>âœ— OpenRouter configuration not found</p>";
    $openrouter_configured = false;
}

// Test OpenRouter if configured
if ($openrouter_configured && $openRouterClientExists) {
    echo "<h2>Testing OpenRouter</h2>";
    
    try {
        require_once 'includes/OpenRouterClient.php';
        
        // Initialize OpenRouter client
        $client = new OpenRouterClient($openrouter_key);
        
        // Prepare a simple educational message
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
                'content' => 'Can you briefly explain photosynthesis?'
            ]
        ];
        
        // Set options
        $options = [
            'temperature' => 0.7,
            'max_tokens' => 150
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
        
        echo "<h3>Response:</h3>";
        echo "<div style='background-color: #f0f0f0; padding: 15px; border-radius: 5px;'>";
        echo "<p>" . nl2br(htmlspecialchars($response)) . "</p>";
        echo "</div>";
        
        echo "<p style='color: green;'>âœ“ OpenRouter API test successful!</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>âœ— OpenRouter API test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Ollama section removed as requested

// Test API endpoints
echo "<h2>Testing API Endpoints</h2>";

// Function to test API endpoint
function testApiEndpoint($endpoint, $data = []) {
    $url = "http://localhost:8000/api/assistant/$endpoint.php";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    
    // Add session cookie if available
    if (isset($_COOKIE[session_name()])) {
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "<p style='color: red;'>âœ— Error testing $endpoint: " . curl_error($ch) . "</p>";
        curl_close($ch);
        return null;
    }
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        echo "<p style='color: red;'>âœ— Error testing $endpoint: HTTP code $httpCode</p>";
        return null;
    }
    
    return json_decode($response, true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: orange;'>âš ï¸ Not logged in - API endpoint tests will fail. Please log in first.</p>";
} else {
    echo "<p style='color: green;'>âœ“ Logged in as user ID: " . htmlspecialchars($_SESSION['user_id']) . "</p>";
    
    // Test question API
    echo "<h3>Testing question.php API</h3>";
    $questionResponse = testApiEndpoint('question', [
        'message' => 'What is the capital of Ghana?'
    ]);
    
    if ($questionResponse) {
        echo "<p style='color: green;'>âœ“ Question API test successful!</p>";
        echo "<div style='background-color: #f0f0f0; padding: 15px; border-radius: 5px;'>";
        echo "<pre>" . htmlspecialchars(json_encode($questionResponse, JSON_PRETTY_PRINT)) . "</pre>";
        echo "</div>";
    }
}

// Summary
echo "<h2>Summary</h2>";
echo "<ul>";
if ($openrouter_configured) {
    echo "<li style='color: green;'>OpenRouter is configured and ready to use</li>";
} else {
    echo "<li style='color: red;'>OpenRouter is not configured - add your API key to the .env file</li>";
}
echo "</ul>";

// Recommendations
echo "<h2>Recommendations</h2>";
echo "<ol>";
if (!$openrouter_configured) {
    echo "<li>Add your OpenRouter API key to the .env file:<br>";
    echo "<code>OPENROUTER_API_KEY=your_api_key_here</code></li>";
}
echo "<li>Consider implementing error handling for API rate limits or downtime</li>";
echo "</ol>";
?>
