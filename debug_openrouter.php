<?php
/**
 * Academic Ease - OpenRouter Debug Script
 * This script tests the OpenRouter integration directly
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/OpenRouterClient.php';
require_once 'includes/ai_config.php';

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

echo "<h1>OpenRouter Debug Tool</h1>";

// Get API key
$api_key = $ai_config['openrouter']['api_key'] ?? getenv('OPENROUTER_API_KEY');

if (empty($api_key) || $api_key === 'your_openrouter_api_key_here') {
    echo "<p style='color: red;'>API key not configured!</p>";
    echo "<p>Please check your .env file and make sure OPENROUTER_API_KEY is set correctly.</p>";
    exit;
}

// Mask the API key for display
$masked_key = substr($api_key, 0, 4) . '...' . substr($api_key, -4);
echo "<p>Using API key: $masked_key</p>";

// Initialize OpenRouter client
$client = new OpenRouterClient($api_key);

// Test simple completion
echo "<h2>Testing Direct API Call</h2>";

try {
    // Create a simple message array
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant.'
        ],
        [
            'role' => 'user',
            'content' => 'Say hello and tell me what the capital of Ghana is.'
        ]
    ];
    
    echo "<p>Sending request to OpenRouter API...</p>";
    
    // Make the API call directly
    $url = 'https://openrouter.ai/api/v1/chat/completions';
    
    $payload = [
        'model' => 'anthropic/claude-3-haiku',
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 150,
        'route' => 'openai'
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        'HTTP-Referer: https://academic-ease.com',
        'X-Title: Academic Ease Debug Tool'
    ];
    
    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // Execute cURL request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Handle cURL errors
    if (curl_errno($ch)) {
        echo "<p style='color: red;'>cURL Error: " . curl_error($ch) . " (Code: " . curl_errno($ch) . ")</p>";
        curl_close($ch);
        exit;
    }
    
    curl_close($ch);
    
    echo "<p>HTTP Response Code: $httpCode</p>";
    
    // Parse and display response
    $decodedResponse = json_decode($response, true);
    
    echo "<h3>Raw API Response:</h3>";
    echo "<pre>" . htmlspecialchars(json_encode($decodedResponse, JSON_PRETTY_PRINT)) . "</pre>";
    
    // Extract the completion text
    if (isset($decodedResponse['choices']) && !empty($decodedResponse['choices'])) {
        $completionText = $decodedResponse['choices'][0]['message']['content'];
        
        echo "<h3>AI Response:</h3>";
        echo "<div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px;'>";
        echo "<p>" . nl2br(htmlspecialchars($completionText)) . "</p>";
        echo "</div>";
    } else {
        echo "<p style='color: red;'>Error: No completion text found in response.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test using the client
echo "<h2>Testing via OpenRouterClient</h2>";

try {
    // Create a simple message array
    $messages = [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant.'
        ],
        [
            'role' => 'user',
            'content' => 'What is the capital of Ghana?'
        ]
    ];
    
    echo "<p>Sending request via OpenRouterClient...</p>";
    
    // Generate chat completion
    $response = $client->generateChatCompletion($messages);
    
    echo "<h3>Client Response:</h3>";
    echo "<div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px;'>";
    echo "<p>" . nl2br(htmlspecialchars($response)) . "</p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
