<?php
/**
 * Academic Ease - Gemini API Test Script
 * This script tests if your Gemini API key is working correctly
 */

// Include configuration
require_once './includes/config.php';

// Check if Gemini API key is set
if (empty(GEMINI_API_KEY)) {
    echo "ERROR: Gemini API key is not set in your .env file.\n";
    exit(1);
}

// Test function to call Gemini API
function testGeminiApi() {
    // Simple prompt for testing
    $prompt = "You are Academic Ease, an AI tutor. Please respond with a short greeting.";
    
    // Gemini API endpoint
    // Using the latest API version and model name
    $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-pro:generateContent?key=' . GEMINI_API_KEY;
    
    // Prepare request data
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 100
        ]
    ];
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // SSL verification options - for development only
    // Option 1: Disable SSL verification (not recommended for production)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Option 2: Specify certificate path (better approach for production)
    // Uncomment the line below and update the path if you have a certificate bundle
    // curl_setopt($ch, CURLOPT_CAINFO, 'path/to/cacert.pem');
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        echo "ERROR: " . curl_error($ch) . "\n";
        curl_close($ch);
        return false;
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if response contains error
    if (isset($responseData['error'])) {
        echo "API ERROR: " . $responseData['error']['message'] . "\n";
        echo "Status: " . $responseData['error']['status'] . "\n";
        
        // Provide more helpful information for quota exceeded errors
        if (strpos($responseData['error']['message'], 'quota') !== false) {
            echo "\nQUOTA EXCEEDED: You need to set up billing for your Google Cloud project.\n";
            echo "1. Go to https://console.cloud.google.com/billing\n";
            echo "2. Link your project to a billing account\n";
            echo "3. You may need to upgrade from the free tier\n";
            echo "4. Once billing is set up, try again\n";
        }
        
        return false;
    }
    
    // Extract text from response
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];
        echo "SUCCESS! Gemini API is working correctly.\n";
        echo "Response: " . $aiResponse . "\n";
        return true;
    } else {
        echo "ERROR: Unexpected response format.\n";
        echo "Raw response: " . $response . "\n";
        return false;
    }
}

// Run the test
echo "Testing Gemini API connection...\n";
testGeminiApi();
