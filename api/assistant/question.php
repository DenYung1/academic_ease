<?php
/**
 * Academic Ease - AI Assistant Question API
 * Handles processing of text questions sent to the AI service
 */

// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/openrouter_helper.php'; // Include OpenRouter helper

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Verify user's subscription status
try {
    // Get user data using direct request with service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    if (empty($users)) {
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    $user = $users[0];
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => 'Your subscription has expired. Please renew your subscription to continue using the AI Assistant.',
            'subscription_expired' => true
        ]);
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred while verifying your subscription. Please try again later.'
    ]);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get user message
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Check if message is empty
if (empty($message)) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Message cannot be empty'
    ]);
    exit;
}

// Get conversation ID if provided
$conversationId = isset($_POST['conversation_id']) ? $_POST['conversation_id'] : null;

try {
    // Create or retrieve conversation
    if ($conversationId) {
        // Check if conversation exists and belongs to user
        $conversation = supabaseSelect('conversations', [
            'filter' => [
                'conversation_id.eq' => $conversationId,
                'user_id.eq' => $_SESSION['user_id']
            ]
        ]);
        
        if (empty($conversation)) {
            throw new Exception('Conversation not found');
        }
    } else {
        // Create new conversation
        $conversation = supabaseInsert('conversations', [
            'user_id' => $_SESSION['user_id'],
            'start_time' => date('c'),
            'platform' => 'web',
            'subject' => 'General', // Default subject
            'topic' => 'General'    // Default topic
        ]);
        
        $conversationId = $conversation[0]['conversation_id'];
    }
    
    // Save user message to database
    supabaseInsert('messages', [
        'conversation_id' => $conversationId,
        'sender' => 'user',
        'content' => $message,
        'content_type' => 'text',
        'timestamp' => date('c')
    ]);
    
    // Process message with OpenRouter AI
    $aiResponse = processWithOpenRouter($message, $conversationId);
    
    // Save AI response to database
    supabaseInsert('messages', [
        'conversation_id' => $conversationId,
        'sender' => 'ai',
        'content' => $aiResponse,
        'content_type' => 'text',
        'timestamp' => date('c')
    ]);
    
    // Update conversation end time
    supabaseUpdate('conversations', [
        'end_time' => date('c')
    ], 'conversation_id=eq.' . $conversationId);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'error' => false,
        'response' => $aiResponse,
        'conversation_id' => $conversationId
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('AI Assistant Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred while processing your request'
    ]);
}

/**
 * Process message with AI Service
 * 
 * @param string $message User message
 * @param string $conversationId Conversation ID
 * @return string AI response
 */
function processWithGemini($message, $conversationId) {
    // Get conversation history
    $history = getConversationHistory($conversationId);
    
    // Prepare prompt with educational context
    $prompt = prepareEducationalPrompt($message, $history);
    
    // Call Gemini API directly
    try {
        // Check if Gemini API key is set
        if (!isGeminiApiKeySet()) {
            error_log('Gemini API key is not set');
            return "I'm sorry, the AI service is not properly configured. Please contact support.";
        }
        
        // Call Gemini API with the prepared prompt
        $response = callGeminiApi($prompt, 1024, 0.7);
        
        // Log successful API call
        error_log('Gemini API call successful');
        
        return $response;
    } catch (Exception $e) {
        error_log('Gemini API Error: ' . $e->getMessage());
        return "I'm sorry, I'm having trouble connecting to my knowledge services right now. Please try again in a few moments.";
    }
}

/**
 * Get conversation history
 * 
 * @param string $conversationId Conversation ID
 * @param int $limit Maximum number of messages to retrieve
 * @return array Conversation messages
 */
function getConversationHistory($conversationId, $limit = 10) {
    // Get recent messages from conversation
    $messages = supabaseSelect('messages', [
        'filter' => [
            'conversation_id.eq' => $conversationId
        ],
        'order' => 'timestamp.desc',
        'limit' => $limit
    ]);
    
    // Reverse to get chronological order
    return array_reverse($messages);
}

/**
 * Prepare educational prompt that focuses on explaining concepts rather than solving homework
 * 
 * @param string $message User message
 * @param array $history Conversation history
 * @return string Formatted prompt
 */
function prepareEducationalPrompt($message, $history) {
    // Start with system prompt focused on teaching rather than solving
    $prompt = "You are Academic Ease, an AI educational assistant designed to help Ghanaian JHS and SHS students understand their schoolwork better.\n\n";
    
    $prompt .= "IMPORTANT EDUCATIONAL GUIDELINES:\n";
    $prompt .= "1. NEVER solve homework problems directly for students. Instead, explain the underlying concepts and guide them to find the solution themselves.\n";
    $prompt .= "2. When a student asks you to solve a question, first explain the concept behind it, then provide a similar example with a worked solution to demonstrate the approach.\n";
    $prompt .= "3. Use the Socratic method - ask guiding questions that lead students to discover answers themselves.\n";
    $prompt .= "4. Provide clear, step-by-step explanations aligned with the Ghanaian curriculum.\n";
    $prompt .= "5. If you detect that a student is asking you to complete an assignment for them, politely redirect them to learning the material.\n";
    $prompt .= "6. Always be encouraging and supportive, emphasizing that making mistakes is part of the learning process.\n";
    $prompt .= "7. If you don't know something, admit it rather than making up information.\n\n";
    
    // Add examples of good responses
    $prompt .= "EXAMPLES OF GOOD RESPONSES:\n";
    $prompt .= "- If asked 'What is 5 × 7?', don't just say '35'. Instead explain multiplication concepts and encourage the student to try calculating it.\n";
    $prompt .= "- If asked 'Write an essay about the causes of World War II', don't write the essay. Instead, explain the key causes and help the student organize their thoughts to write their own essay.\n";
    $prompt .= "- If asked 'Solve this equation: 2x + 5 = 15', guide them through the steps of solving for x rather than just giving the answer x = 5.\n\n";
    
    // Add conversation history for context
    if (!empty($history)) {
        foreach ($history as $msg) {
            $role = $msg['sender'] === 'user' ? 'Student' : 'Academic Ease';
            $prompt .= $role . ": " . $msg['content'] . "\n";
        }
    }
    
    // Add current message
    $prompt .= "Student: " . $message . "\n";
    $prompt .= "Academic Ease: ";
    
    return $prompt;
}

// Using Gemini API via gemini_helper.php
