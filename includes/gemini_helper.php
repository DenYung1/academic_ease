<?php
/**
 * Academic Ease - Gemini API Helper Functions
 * Provides functions for interacting with Google's Gemini API
 */

// Include configuration
require_once __DIR__ . '/config.php';

/**
 * Check if Gemini API key is set
 * 
 * @return bool True if API key is set
 */
function isGeminiApiKeySet() {
    return !empty(GEMINI_API_KEY);
}

/**
 * Call Gemini API for text processing
 * 
 * @param string $prompt The prompt to send to Gemini
 * @param int $maxTokens Maximum tokens in response
 * @param float $temperature Temperature for response generation
 * @return string Gemini's response
 */
function callGeminiApi($prompt, $maxTokens = 1024, $temperature = 0.7) {
    // Check if API key is set
    if (!isGeminiApiKeySet()) {
        error_log('Gemini API key is not set');
        return "I'm sorry, the AI service is not properly configured. Please contact support.";
    }
    
    // Gemini API endpoint - using the latest API version
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
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens
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
    
    // SSL verification options
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('Gemini API cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return "I'm sorry, there was an error connecting to the AI service. Please try again later.";
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if response contains error
    if (isset($responseData['error'])) {
        error_log('Gemini API Error: ' . $responseData['error']['message']);
        
        // Check for quota exceeded
        if (strpos($responseData['error']['message'], 'quota') !== false) {
            return "I'm sorry, the AI service has reached its usage limit. Please try again later or contact support.";
        }
        
        return "I'm sorry, there was an error processing your request. Please try again later.";
    }
    
    // Extract text from response
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } else {
        error_log('Gemini API: Unexpected response format - ' . $response);
        return "I'm sorry, I received an unexpected response. Please try again later.";
    }
}

/**
 * Call Gemini API for image analysis
 * 
 * @param string $prompt The text prompt to send with the image
 * @param string $imageData Base64 encoded image data
 * @param string $mimeType Image MIME type
 * @return string Gemini's response
 */
function analyzeImageWithGemini($prompt, $imageData, $mimeType) {
    // Check if API key is set
    if (!isGeminiApiKeySet()) {
        error_log('Gemini API key is not set');
        return "I'm sorry, the AI service is not properly configured. Please contact support.";
    }
    
    // Gemini API endpoint for vision model
    $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-pro-vision:generateContent?key=' . GEMINI_API_KEY;
    
    // Prepare request data with image
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $imageData
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1024
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
    
    // SSL verification options
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('Gemini Vision API cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return "I'm sorry, there was an error connecting to the AI service. Please try again later.";
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if response contains error
    if (isset($responseData['error'])) {
        error_log('Gemini Vision API Error: ' . $responseData['error']['message']);
        
        // Check for quota exceeded
        if (strpos($responseData['error']['message'], 'quota') !== false) {
            return "I'm sorry, the AI service has reached its usage limit. Please try again later or contact support.";
        }
        
        return "I'm sorry, there was an error processing your image. Please try again later.";
    }
    
    // Extract text from response
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } else {
        error_log('Gemini Vision API: Unexpected response format - ' . $response);
        return "I'm sorry, I received an unexpected response. Please try again later.";
    }
}

