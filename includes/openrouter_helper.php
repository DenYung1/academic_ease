<?php
/**
 * Academic Ease - OpenRouter API Helper Functions
 * Provides functions for interacting with OpenRouter API
 */

// Include configuration
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/OpenRouterClient.php';
require_once __DIR__ . '/ai_config.php';

/**
 * Process a text question with OpenRouter
 * 
 * @param string $message User's question
 * @param string|null $conversationId Conversation ID for context
 * @param array $options Additional options
 * @return string AI response
 */
function processWithOpenRouter($message, $conversationId = null, $options = []) {
    global $ai_config;
    
    // Log the conversation ID for debugging
    error_log('OpenRouter Helper - Processing message with conversation ID: ' . ($conversationId ? $conversationId : 'null'));
    error_log('OpenRouter Helper - User message: ' . $message);
    
    try {
        // Get OpenRouter API key
        $api_key = $ai_config['openrouter']['api_key'] ?? getenv('OPENROUTER_API_KEY');
        
        // Check if API key is configured
        if (empty($api_key) || $api_key === 'your_openrouter_api_key_here') {
            error_log('OpenRouter API key is not configured');
            throw new Exception('OpenRouter API key is not configured. Please add your API key to the .env file.');
        }
        
        // Initialize OpenRouter client
        $client = new OpenRouterClient($api_key);
        
        // Get conversation history if conversation ID is provided
        $messages = [];
        if ($conversationId) {
            error_log('OpenRouter Helper - Getting conversation history for ID: ' . $conversationId);
            $history = getConversationHistory($conversationId, 5); // Get last 5 messages
            
            if (!empty($history)) {
                error_log('OpenRouter Helper - Found ' . count($history) . ' messages in history');
                foreach ($history as $msg) {
                    $messages[] = [
                        'role' => $msg['sender'] === 'user' ? 'user' : 'assistant',
                        'content' => $msg['content']
                    ];
                }
            } else {
                error_log('OpenRouter Helper - No conversation history found');
            }
        }
        
        // Add system message with educational focus
        $messages[] = [
            'role' => 'system',
            'content' => 'You are an AI assistant for Academic Ease, an educational platform for students in Ghana. '
                      . 'You provide helpful, accurate, and educational responses following the Ghana curriculum. '
                      . 'Focus on explaining concepts rather than solving homework directly. '
                      . 'Be friendly, clear, and provide step-by-step explanations when needed.'
                      . '\n\nIMPORTANT EDUCATIONAL GUIDELINES:\n'
                      . '1. NEVER solve homework problems directly for students. Instead, explain the underlying concepts and guide them to find the solution themselves.\n'
                      . '2. When a student asks you to solve a question, first explain the concept behind it, then provide a similar example with a worked solution to demonstrate the approach.\n'
                      . '3. Use the Socratic method - ask guiding questions that lead students to discover answers themselves.\n'
                      . '4. Provide clear, step-by-step explanations aligned with the Ghanaian curriculum.\n'
                      . '5. If you detect that a student is asking you to complete an assignment for them, politely redirect them to learning the material.'
        ];
        
        // Add current user message
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];
        
        // Detect task type based on message content
        $task = detectTaskType($message);
        error_log('OpenRouter Helper - Detected task type: ' . $task);
        
        // Set options for API request
        $apiOptions = [
            'task' => $task,
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];
        
        // Add selected model if provided
        if (isset($options['model']) && !empty($options['model']) && $options['model'] !== 'auto') {
            $apiOptions['model'] = $options['model'];
            error_log('OpenRouter Helper - Using specified model: ' . $options['model']);
        }
        
        // Generate response
        error_log('OpenRouter Helper - Sending request to OpenRouter API');
        $response = $client->generateChatCompletion($messages, $apiOptions);
        error_log('OpenRouter Helper - Received response from OpenRouter API');
        
        return $response;
    } catch (Exception $e) {
        error_log('OpenRouter Error: ' . $e->getMessage());
        
        // Fallback responses if API fails
        $fallback_responses = [
            'I apologize, but I\'m having trouble connecting to my knowledge base right now. Please try again in a moment.',
            'It seems there\'s a technical issue. Please try asking your question again.',
            'I\'m sorry, but I couldn\'t process your request at this time. Please try again later.'
        ];
        
        return $fallback_responses[array_rand($fallback_responses)];
    }
}

/**
 * Process an image with OpenRouter
 * 
 * @param string $message User's question about the image
 * @param string $imagePath Path to the image
 * @param string|null $conversationId Conversation ID for context
 * @param array $options Additional options
 * @return string AI response
 */
function processImageWithOpenRouter($message, $imagePath, $conversationId = null, $options = []) {
    global $ai_config;
    
    try {
        // Get OpenRouter API key
        $api_key = $ai_config['openrouter']['api_key'];
        
        // Check if API key is configured
        if (empty($api_key) || $api_key === 'your_openrouter_api_key_here') {
            error_log('OpenRouter API key is not configured');
            throw new Exception('OpenRouter API key is not configured. Please add your API key to the .env file.');
        }
        
        // Initialize OpenRouter client
        $client = new OpenRouterClient($api_key);
        
        // Get conversation history if conversation ID is provided
        $messages = [];
        if ($conversationId) {
            $history = getConversationHistory($conversationId, 3); // Get last 3 messages
            foreach ($history as $msg) {
                if (isset($msg['has_image']) && $msg['has_image'] && isset($msg['image_url'])) {
                    // Skip messages with images in history to avoid confusion
                    continue;
                }
                $messages[] = [
                    'role' => $msg['sender'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['content']
                ];
            }
        }
        
        // Add system message with educational focus
        $messages[] = [
            'role' => 'system',
            'content' => 'You are an AI assistant for Academic Ease, an educational platform for students in Ghana. '
                      . 'You provide helpful, accurate, and educational responses following the Ghana curriculum. '
                      . 'Focus on explaining concepts rather than solving homework directly. '
                      . 'Be friendly, clear, and provide step-by-step explanations when needed.'
        ];
        
        // For local development, use http instead of https
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $image_full_url = $protocol . $host . '/' . $imagePath;
        
        // Add current user message with image
        $content = [
            [
                'type' => 'text',
                'text' => $message
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image_full_url
                ]
            ]
        ];
        
        $messages[] = [
            'role' => 'user',
            'content' => $content
        ];
        
        // Set options for API request - use a multimodal model
        $apiOptions = [
            'model' => 'meta-llama/llama-4-maverick:free', // Best for visual content
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];
        
        // Generate response
        $response = $client->generateChatCompletion($messages, $apiOptions);
        
        return $response;
    } catch (Exception $e) {
        error_log('OpenRouter Image Processing Error: ' . $e->getMessage());
        
        // Fallback responses if API fails
        $fallback_responses = [
            'I apologize, but I\'m having trouble analyzing this image right now. Please try again in a moment.',
            'It seems there\'s a technical issue with the image processing. Please try again.',
            'I\'m sorry, but I couldn\'t process the image at this time. Please try again later.'
        ];
        
        return $fallback_responses[array_rand($fallback_responses)];
    }
}

/**
 * Get conversation history from database
 * 
 * @param string $conversationId Conversation ID
 * @param int $limit Maximum number of messages to retrieve
 * @return array Conversation messages
 */
function getConversationHistory($conversationId, $limit = 10) {
    try {
        $endpoint = 'messages?conversation_id=eq.' . urlencode($conversationId) . '&order=timestamp.asc&limit=' . $limit;
        $messages = supabaseRequest($endpoint, 'GET', null, true);
        
        if (!is_array($messages)) {
            return [];
        }
        
        return $messages;
    } catch (Exception $e) {
        error_log('Error retrieving conversation history: ' . $e->getMessage());
        return [];
    }
}

/**
 * Detect task type based on message content
 * 
 * @param string $message User message
 * @return string Task type
 */
function detectTaskType($message) {
    $message = strtolower($message);
    
    // Check for math problems
    if (preg_match('/(math|equation|calculate|solve|algebra|geometry|calculus|trigonometry|arithmetic)/i', $message)) {
        return 'math_problem';
    }
    
    // Check for science questions
    if (preg_match('/(science|physics|chemistry|biology|experiment|scientific|molecule|atom|cell|organism|reaction)/i', $message)) {
        return 'science_question';
    }
    
    // Check for programming questions
    if (preg_match('/(code|program|function|algorithm|programming|javascript|python|java|php|html|css|sql|database)/i', $message)) {
        return 'programming';
    }
    
    // Default to general question
    return 'general_question';
}
