<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/OpenRouterClient.php';

// Load AI configuration
$ai_config = include '../includes/ai_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session information
error_log('AI Assistant Fixed - Session ID: ' . session_id());
error_log('AI Assistant Fixed - SESSION: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // Get or create conversation
    // First check POST data (form submission), then GET data (URL parameter)
    $conversation_id = isset($_POST['conversation_id']) ? $_POST['conversation_id'] : (isset($_GET['conversation_id']) ? $_GET['conversation_id'] : null);
    
    // Debug log the conversation ID
    error_log('Conversation ID from request: ' . ($conversation_id ? $conversation_id : 'null'));

    // Debug: Log the user ID we're looking for
    error_log('AI Assistant - Looking for user_id: ' . $_SESSION['user_id']);
    
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the result of the user query
    error_log('AI Assistant - User query result: ' . json_encode($users));
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $user = $users[0];
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if ($subscription_status === 'pending') {
        // Payment is pending, redirect to subscription page
        header('Location: subscribe-direct.php?pending=1');
        exit;
    } else if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    } else if ($subscription_status !== 'active' && $subscription_status !== 'trial') {
        // User doesn't have an active subscription or trial
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    
    // Get active conversation or create a new one
    if ($conversation_id) {
        // Get existing conversation
        $endpoint = 'conversations?conversation_id=eq.' . urlencode($conversation_id) . '&user_id=eq.' . urlencode($_SESSION['user_id']);
        $conversations = supabaseRequest($endpoint, 'GET', null, true);
        
        // Check if we got an error response
        if (isset($conversations['error']) && $conversations['error'] === true) {
            error_log('Error fetching conversation: ' . ($conversations['message'] ?? 'Unknown error'));
            $conversation_id = null;
            $error = 'Unable to load the conversation. Please try again.';
        } else if (empty($conversations) || !is_array($conversations)) {
            // Conversation not found or doesn't belong to user
            $conversation_id = null;
        } else {
            // Store current conversation data
            $current_conversation = $conversations;
        }
    }
    
    // IMPORTANT: Only create a new conversation if explicitly requested with the 'new' parameter
    // or if the user has no conversations at all
    $create_new = isset($_GET['new']) && $_GET['new'] == '1';
    
    // Check if user has any existing conversations
    if (!$conversation_id) {
        $endpoint = 'conversations?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=last_message_time.desc';
        $existing_conversations = supabaseRequest($endpoint, 'GET', null, true);
        
        // If there are existing conversations and we're not explicitly creating a new one,
        // use the most recent conversation
        if (!$create_new && !empty($existing_conversations) && is_array($existing_conversations) && isset($existing_conversations[0]['conversation_id'])) {
            // Use the most recent conversation ID
            $conversation_id = $existing_conversations[0]['conversation_id'];
            // Also store current conversation data
            $current_conversation = [$existing_conversations[0]];
            
            // Redirect to the conversation page with the ID to prevent refresh issues
            if (!isset($_SERVER['HTTP_REFERER']) || empty($_SERVER['HTTP_REFERER'])) {
                header('Location: ai-assistant.php?conversation_id=' . urlencode($conversation_id));
                exit;
            }
        }
        
        // ONLY create a new conversation if EXPLICITLY requested with new=1 parameter OR if user has NO conversations at all
        if ($create_new || (empty($existing_conversations) || !is_array($existing_conversations))) {
            try {
                // Create a new conversation
                $newConversation = [
                    'user_id' => $_SESSION['user_id'],
                    'title' => 'New Conversation',
                    'start_time' => date('Y-m-d H:i:s'),
                    'last_message_time' => date('Y-m-d H:i:s')
                ];
                
                $endpoint = 'conversations';
                $result = supabaseRequest($endpoint, 'POST', $newConversation, true);
                
                // Check if we got an error response
                if (isset($result['error']) && $result['error'] === true) {
                    error_log('Error creating conversation: ' . ($result['message'] ?? 'Unknown error'));
                    throw new Exception('Failed to create a new conversation: ' . ($result['message'] ?? 'Unknown error'));
                }
            
                if (!empty($result) && is_array($result) && isset($result[0]['conversation_id'])) {
                    $conversation_id = $result[0]['conversation_id'];
                    
                    // Store current conversation data
                    $current_conversation = [[
                        'conversation_id' => $conversation_id,
                        'title' => 'New Conversation',
                        'user_id' => $_SESSION['user_id'],
                        'start_time' => date('Y-m-d H:i:s'),
                        'last_message_time' => date('Y-m-d H:i:s')
                    ]];
            } else {
                throw new Exception('Failed to create a new conversation: Invalid response format');
            }
        } catch (Exception $e) {
            error_log('New Conversation Error: ' . $e->getMessage());
            $error = 'An error occurred while creating a new conversation. Please try again.';
        }
    } // Close the if ($create_new || empty($existing_conversations) || !is_array($existing_conversations)) block
    }
    
    // Get messages for the current conversation if we have a valid conversation_id
    $messages = [];
    if ($conversation_id) {
        $endpoint = 'messages?conversation_id=eq.' . urlencode($conversation_id) . '&order=timestamp.asc';
        error_log('AI Assistant - Fetching messages for conversation ID: ' . $conversation_id);
        $messages = supabaseRequest($endpoint, 'GET', null, true);
        error_log('AI Assistant - Retrieved messages: ' . json_encode($messages));
        
        // Check if we got an error response
        if (isset($messages['error']) && $messages['error'] === true) {
            error_log('Error fetching messages: ' . ($messages['message'] ?? 'Unknown error'));
            $messages = [];
            $error = isset($error) ? $error : 'Unable to load messages. Please try again.';
        }
        
        // Ensure $messages is an array
        if (!is_array($messages)) {
            $messages = [];
        }
    }
    
    // Get recent conversations with last message preview
    $endpoint = 'conversations?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=start_time.desc&limit=10';
    $recent_conversations = supabaseRequest($endpoint, 'GET', null, true);
    
    // Check if we got an error response
    if (isset($recent_conversations['error']) && $recent_conversations['error'] === true) {
        error_log('Error fetching conversations: ' . ($recent_conversations['message'] ?? 'Unknown error'));
        $recent_conversations = []; // Reset to empty array to avoid errors
    }
    
    // Get last message for each conversation
    if (!empty($recent_conversations) && is_array($recent_conversations)) {
        foreach ($recent_conversations as $key => &$conv) {
            // Skip if not an array
            if (!is_array($conv)) {
                error_log('Invalid conversation data at index ' . $key . ': ' . print_r($conv, true));
                continue;
            }
            
            // Initialize with empty message
            $conv['last_message'] = '';
            
            // Only proceed if conversation_id exists
            if (isset($conv['conversation_id'])) {
                // Get the last message for this conversation
                $msg_endpoint = 'messages?conversation_id=eq.' . urlencode($conv['conversation_id']) . '&order=timestamp.desc&limit=1';
                $last_messages = supabaseRequest($msg_endpoint, 'GET', null, true);
                
                // Make sure $last_messages is an array and has elements before accessing index
                if (is_array($last_messages) && !empty($last_messages) && isset($last_messages[0]['content'])) {
                    $conv['last_message'] = $last_messages[0]['content'];
                }
                
                // Check if there was an error in the response
                if (isset($last_messages['error']) && $last_messages['error'] === true) {
                    error_log('Error fetching last message: ' . ($last_messages['message'] ?? 'Unknown error'));
                }
            }
        }
        unset($conv); // Break the reference
    }
    
} catch (Exception $e) {
    error_log('AI Assistant Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the AI Assistant. Please try again.';
}

// Debug: Log the request data
error_log('AI Assistant - POST data: ' . json_encode($_POST));
error_log('AI Assistant - GET data: ' . json_encode($_GET));

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle conversation delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete_conversation' && isset($_POST['conversation_id'])) {
        try {
            $delete_conversation_id = trim($_POST['conversation_id']);
            
            if (!empty($delete_conversation_id)) {
                // First delete all messages in the conversation
                $endpoint = 'messages?conversation_id=eq.' . urlencode($delete_conversation_id);
                supabaseRequest($endpoint, 'DELETE', null, true);
                
                // Then delete the conversation
                $endpoint = 'conversations?conversation_id=eq.' . urlencode($delete_conversation_id);
                supabaseRequest($endpoint, 'DELETE', null, true);
                
                // Redirect to AI assistant page without the deleted conversation
                header('Location: ai-assistant.php');
                exit;
            }
        } catch (Exception $e) {
            error_log('Delete Conversation Error: ' . $e->getMessage());
            $error = 'An error occurred while deleting the conversation. Please try again.';
        }
    }
    // Handle conversation rename
    else if (isset($_POST['action']) && $_POST['action'] === 'rename_conversation' && isset($_POST['conversation_title'])) {
        try {
            $conversation_title = trim($_POST['conversation_title']);
            
            if (!empty($conversation_title) && isset($conversation_id)) {
                // Update conversation title
                $updateData = [
                    'title' => $conversation_title
                ];
                
                $endpoint = 'conversations?conversation_id=eq.' . urlencode($conversation_id);
                supabaseRequest($endpoint, 'PATCH', $updateData, true);
                
                // Redirect to avoid form resubmission
                header('Location: ai-assistant.php?conversation_id=' . $conversation_id);
                exit;
            }
        } catch (Exception $e) {
            error_log('Conversation Rename Error: ' . $e->getMessage());
            $error = 'An error occurred while renaming the conversation. Please try again.';
        }
    }
    
    // Handle new message
    if (isset($_POST['message'])) {
    try {
        $user_message = trim($_POST['message']);
        $selected_model = isset($_POST['ai_model']) && $_POST['ai_model'] !== 'auto' ? $_POST['ai_model'] : null;
        $image_url = null;
        
        // Debug: Log the message and conversation ID
        error_log('AI Assistant - Processing message with conversation ID: ' . ($conversation_id ? $conversation_id : 'null'));
        error_log('AI Assistant - User message: ' . $user_message);
        error_log('AI Assistant - POST data: ' . json_encode($_POST));
        
        // Make sure we have a conversation ID
        if (empty($conversation_id)) {
            // If no conversation ID, create a new one
            error_log('AI Assistant - No conversation ID found, creating a new conversation');
            
            $newConversation = [
                'user_id' => $_SESSION['user_id'],
                'title' => 'New Conversation',
                'start_time' => date('Y-m-d H:i:s'),
                'last_message_time' => date('Y-m-d H:i:s')
            ];
            
            $endpoint = 'conversations';
            $result = supabaseRequest($endpoint, 'POST', $newConversation, true);
            
            // Check if we got an error response
            if (isset($result['error']) && $result['error'] === true) {
                error_log('Error creating conversation: ' . ($result['message'] ?? 'Unknown error'));
                throw new Exception('Failed to create a new conversation: ' . ($result['message'] ?? 'Unknown error'));
            }
            
            if (!empty($result) && is_array($result) && isset($result[0]['conversation_id'])) {
                $conversation_id = $result[0]['conversation_id'];
                error_log('AI Assistant - Created new conversation with ID: ' . $conversation_id);
                
                // Initialize student activity tracking for this conversation
                $activity_data = [
                    'conversation_id' => $conversation_id,
                    'user_id' => $_SESSION['user_id'],
                    'activity_type' => 'conversation_start',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'session_data' => json_encode([
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                        'referrer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
                    ])
                ];
                
                // Log the activity start (silently continue if this fails)
                try {
                    $endpoint = 'user_activities';
                    supabaseRequest($endpoint, 'POST', $activity_data, true);
                } catch (Exception $e) {
                    error_log('Failed to log student activity: ' . $e->getMessage());
                }
                
                // Store current conversation data
                $current_conversation = [[
                    'conversation_id' => $conversation_id,
                    'title' => 'New Conversation',
                    'user_id' => $_SESSION['user_id'],
                    'start_time' => date('Y-m-d H:i:s'),
                    'last_message_time' => date('Y-m-d H:i:s')
                ]];
            } else {
                throw new Exception('Failed to create a new conversation: Invalid response format');
            }
        }
        
        // Handle image upload if present
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/ai_images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = basename($_FILES['image']['name']);
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = uniqid() . '_' . $file_name;
            $file_path = $upload_dir . $unique_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                $image_url = 'uploads/ai_images/' . $unique_file_name;
                
                // Add image reference to user message
                $user_message .= "\n[Image attached]";
            } else {
                error_log('Failed to upload image for AI assistant');
            }
        }
        
        if (!empty($user_message)) {
            // Generate a message ID that we'll use for both the message and activity tracking
            $message_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // Save user message - adjusted to match database schema
            $newMessage = [
                'message_id' => $message_id,
                'conversation_id' => $conversation_id,
                'sender' => 'user',
                'content' => $user_message,
                'content_type' => 'text',
                'timestamp' => date('c') // ISO 8601 format
            ];
            
            // Track student message activity
            $activity_data = [
                'activity_id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                ),
                'user_id' => $_SESSION['user_id'],
                'conversation_id' => $conversation_id,
                'message_id' => $message_id,
                'activity_type' => 'message_sent',
                'timestamp' => date('Y-m-d H:i:s'),
                'metadata' => json_encode([
                    'message_length' => strlen($user_message),
                    'has_image' => !empty($image_url),
                    'content_type' => 'text'
                ])
            ];
            
            // Log the message activity (silently continue if this fails)
            try {
                $endpoint = 'user_activities';
                supabaseRequest($endpoint, 'POST', $activity_data, true);
            } catch (Exception $e) {
                error_log('Failed to log student message activity: ' . $e->getMessage());
            }
            
            // Add image data if available
            if ($image_url) {
                $newMessage['media_url'] = $image_url;
            }
            
            $endpoint = 'messages';
            supabaseRequest($endpoint, 'POST', $newMessage, true);
            
            // No need to update conversation last message time - column doesn't exist
            // Just log that we're continuing with the conversation
            error_log('AI Assistant - Continuing with conversation ID: ' . $conversation_id);
            
            // Get conversation history for context
            try {
                // Debug: Log all parameters being passed to getAIResponse
                error_log('AI Assistant - Calling getAIResponse with parameters:');
                error_log('AI Assistant - User message: ' . $user_message);
                error_log('AI Assistant - Messages count: ' . count($messages));
                error_log('AI Assistant - Selected model: ' . ($selected_model ?? 'auto'));
                error_log('AI Assistant - Image URL: ' . ($image_url ?? 'none'));
                
                // Get AI response
                error_log('AI Assistant - About to call getAIResponse with conversation_id: ' . $conversation_id);
                error_log('AI Assistant - Messages count: ' . count($messages));
                
                // Use try-catch to handle any errors in the API call
                try {
                    $ai_response = getAIResponse($user_message, $messages, $selected_model, $image_url);
                    error_log('AI Assistant - AI response received: ' . substr($ai_response, 0, 100) . (strlen($ai_response) > 100 ? '...' : ''));
                } catch (Exception $e) {
                    error_log('AI Assistant - Error in getAIResponse: ' . $e->getMessage());
                    throw new Exception('Error getting AI response: ' . $e->getMessage());
                }
            } catch (Exception $e) {
                error_log('Error calling getAIResponse: ' . $e->getMessage());
                $ai_response = 'An error occurred while generating a response. Please try again.';
            }
            
            // Generate a message ID for the AI response
            $ai_message_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            // Save AI response - adjusted to match database schema
            $newMessage = [
                'message_id' => $ai_message_id,
                'conversation_id' => $conversation_id,
                'sender' => 'ai',
                'content' => $ai_response,
                'content_type' => 'text',
                'timestamp' => date('c') // ISO 8601 format
            ];
            
            // Track AI response activity
            $activity_data = [
                'activity_id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                ),
                'user_id' => $_SESSION['user_id'],
                'conversation_id' => $conversation_id,
                'message_id' => $ai_message_id,
                'activity_type' => 'ai_response_received',
                'timestamp' => date('Y-m-d H:i:s'),
                'metadata' => json_encode([
                    'response_length' => strlen($ai_response),
                    'model_used' => $selected_model ?? 'auto',
                    'processing_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000),
                    'content_type' => 'text'
                ])
            ];
            
            // Log the AI response activity (silently continue if this fails)
            try {
                $endpoint = 'user_activities';
                supabaseRequest($endpoint, 'POST', $activity_data, true);
            } catch (Exception $e) {
                error_log('Failed to log AI response activity: ' . $e->getMessage());
            }
            
            $endpoint = 'messages';
            supabaseRequest($endpoint, 'POST', $newMessage, true);
            
            // Update conversation title if it's still the default "New Conversation"
            $endpoint = 'conversations?conversation_id=eq.' . urlencode($conversation_id);
            $current_conversation = supabaseRequest($endpoint, 'GET', null, true);
            
            if (!empty($current_conversation) && $current_conversation[0]['title'] === 'New Conversation') {
                // Generate a title based on the first user message
                $title = generateConversationTitle($user_message);
                
                // Update the conversation title
                $updateData = [
                    'title' => $title
                ];
                
                supabaseRequest($endpoint, 'PATCH', $updateData, true);
            }
            
            // Instead of redirecting, just refresh the messages and continue
            // Get the updated messages for the current conversation
            $endpoint = 'messages?conversation_id=eq.' . urlencode($conversation_id) . '&order=timestamp.asc';
            $messages = supabaseRequest($endpoint, 'GET', null, true);
            
            // Get the updated conversation data
            $endpoint = 'conversations?conversation_id=eq.' . urlencode($conversation_id);
            $current_conversation = supabaseRequest($endpoint, 'GET', null, true);
            
            // No redirect needed - the page will display the updated conversation
        }
    } catch (Exception $e) {
        error_log('AI Message Error: ' . $e->getMessage());
        $error = 'An error occurred while processing your message: ' . $e->getMessage();
        
        // Add more detailed error information for debugging
        error_log('AI Message Error Stack Trace: ' . $e->getTraceAsString());
    }
  }
}

// Function to generate a conversation title based on user message
function generateConversationTitle($user_message) {
    // Trim the message and limit to 50 characters
    $title = trim($user_message);
    
    // Remove any markdown or special characters
    $title = preg_replace('/[\*\_\~\`\#\>\<\[\]\(\)\{\}\|\:\;\,\.\?\!\@\#\$\%\^\&\=\+\/\\]/u', '', $title);
    
    // Limit to first 50 characters
    if (strlen($title) > 50) {
        $title = substr($title, 0, 47) . '...';
    }
    
    // If title is empty or too short, use a generic title
    if (strlen($title) < 3) {
        $title = 'New Conversation';
    }
    
    return $title;
}

// Function to get AI response
function getAIResponse($user_message, $conversation_history = [], $selected_model = null, $image_url = null) {
    global $ai_config;
    
    try {
        // Debug log the input parameters
        error_log('AI Response Request - User message: ' . substr($user_message, 0, 100) . (strlen($user_message) > 100 ? '...' : ''));
        error_log('AI Response Request - Selected model: ' . ($selected_model ?? 'auto'));
        error_log('AI Response Request - Has image: ' . ($image_url ? 'yes' : 'no'));
        
        // Get OpenRouter API key
        $api_key = $ai_config['openrouter']['api_key'];
        
        // Check if API key is configured
        if (empty($api_key) || $api_key === 'your_openrouter_api_key_here') {
            error_log('OpenRouter API key is not configured');
            throw new Exception('OpenRouter API key is not configured. Please add your API key to the .env file.');
        }
        
        // Initialize OpenRouter client
        $client = new OpenRouterClient($api_key);
        
        // Detect task type based on message content
        $task = detectTaskType($user_message);
        error_log('AI Response - Detected task type: ' . $task);
        
        // Prepare conversation history for the API
        $messages = [];
        
        // Add system message with educational focus
        $messages[] = [
            'role' => 'system',
            'content' => 'You are an AI assistant for AcademicEase, an educational platform for students in Ghana. '
                      . 'You provide helpful, accurate, and educational responses following the Ghana curriculum. '
                      . 'Focus on explaining concepts rather than solving homework directly. '
                      . 'Be friendly, clear, and provide step-by-step explanations when needed.'
        ];
        
        // Add conversation history (limited to last 10 messages to save tokens)
        $history_limit = min(count($conversation_history), 10);
        for ($i = 0; $i < $history_limit; $i++) {
            $message = $conversation_history[$i];
            
            // Skip invalid messages
            if (!isset($message['sender']) || !isset($message['content'])) {
                continue;
            }
            
            // Check if message has an image
            if (isset($message['has_image']) && $message['has_image'] && isset($message['image_url'])) {
                // For local development, use http instead of https
                $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $image_full_url = $protocol . $host . '/' . $message['image_url'];
                
                // Create multimodal content array for messages with images
                $content = [
                    [
                        'type' => 'text',
                        'text' => $message['content']
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $image_full_url
                        ]
                    ]
                ];
                
                $messages[] = [
                    'role' => $message['sender'] === 'user' ? 'user' : 'assistant',
                    'content' => $content
                ];
            } else {
                // Regular text message
                $messages[] = [
                    'role' => $message['sender'] === 'user' ? 'user' : 'assistant',
                    'content' => $message['content']
                ];
            }
        }
        
        // Add current user message (with image if provided)
        if ($image_url) {
            // For local development, use http instead of https
            $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost:8000';
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $image_full_url = $protocol . $host . '/' . $image_url;
            
            $content = [
                [
                    'type' => 'text',
                    'text' => $user_message
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
        } else {
            // Regular text message
            $messages[] = [
                'role' => 'user',
                'content' => $user_message
            ];
        }
        
        // Set options for API request
        $options = [
            'task' => $task,
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];
        
        // Add selected model if provided
        if ($selected_model) {
            $options['model'] = $selected_model;
        }
        
        // Log the number of messages being sent
        error_log('AI Response - Sending ' . count($messages) . ' messages to OpenRouter API');
        
        // Generate response
        error_log('AI Response - About to call generateChatCompletion with ' . count($messages) . ' messages');
        error_log('AI Response - Options: ' . json_encode($options));
        
        try {
            $response = $client->generateChatCompletion($messages, $options);
            
            // Log successful response
            error_log('AI Response - Successfully received response from OpenRouter API');
            error_log('AI Response - Response: ' . substr($response, 0, 100) . (strlen($response) > 100 ? '...' : ''));
            
            return $response;
        } catch (Exception $e) {
            error_log('AI Response - Error in generateChatCompletion: ' . $e->getMessage());
            error_log('AI Response - Error trace: ' . $e->getTraceAsString());
            throw $e; // Re-throw the exception to be caught by the caller
        }
    } catch (Exception $e) {
        error_log('AI Response Error: ' . $e->getMessage());
        
        // Check if it's an API key error
        if (strpos($e->getMessage(), 'API key') !== false) {
            return "I'm sorry, but there's a configuration issue with the AI service. Please make sure the OpenRouter API key is properly set in the .env file.";
        }
        
        // Fallback responses if API fails
        $fallback_responses = [
            'I apologize, but I\'m having trouble connecting to my knowledge base right now. Please try again in a moment.',
            'It seems there\'s a technical issue. Please try asking your question again.',
            'I\'m sorry, but I couldn\'t process your request at this time. Please try again later.'
        ];
        
        return $fallback_responses[array_rand($fallback_responses)];
    }
}

// Function to detect the type of task based on message content
function detectTaskType($message) {
    $message = strtolower($message);
    
    // Check for math problems
    if (preg_match('/(solve|calculate|equation|math problem|formula|computation|algebra|geometry|calculus)/i', $message)) {
        return 'math_problem';
    }
    
    // Check for science questions
    if (preg_match('/(science|biology|chemistry|physics|experiment|scientific|element|molecule|atom|cell|organism)/i', $message)) {
        return 'science_question';
    }
    
    // Check for programming questions
    if (preg_match('/(code|program|function|algorithm|programming|javascript|python|java|html|css|php|sql)/i', $message)) {
        return 'programming';
    }
    
    // Check for essay writing
    if (preg_match('/(essay|write about|composition|paragraph|thesis|introduction|conclusion)/i', $message)) {
        return 'essay_writing';
    }
    
    // Default to general question
    return 'general_question';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Embedded Logo CSS to fix 404 error -->
    <style>
    /* AcademicEase Logo Styling */
    .ae-logo-container {
        display: flex;
        align-items: center;
        font-family: 'Arial', sans-serif;
    }
    
    .ae-logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #4285F4, #34A853);
        border-radius: 8px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 10px;
        position: relative;
        overflow: hidden;
    }
    
    .ae-logo-icon::before {
        content: 'A';
        color: white;
        font-size: 24px;
        font-weight: bold;
        line-height: 1;
    }
    
    .ae-logo-icon::after {
        content: 'E';
        color: rgba(255, 255, 255, 0.8);
        font-size: 16px;
        font-weight: bold;
        position: absolute;
        bottom: 2px;
        right: 3px;
        line-height: 1;
    }
    
    .ae-logo-text {
        font-size: 18px;
        font-weight: bold;
    }
    
    .ae-academic {
        color: #4285F4;
    }
    
    .ae-ease {
        color: #34A853;
    }
    </style>
    
    <!-- Mobile-specific CSS -->
    <link rel="stylesheet" href="/assets/css/mobile.css">
    
    <!-- Preconnect to external domains for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <!-- Navigation Fix Script -->
    <script src="js/navigation-fix.js"></script>
    
    <style>
        .chat-container {
            height: 60vh;
            overflow-y: auto;
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
        }
        
        .model-selection {
            margin-bottom: 1rem;
        }
        
        .image-preview {
            max-width: 300px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 5px;
            display: none;
        }
        
        .message {
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 0.5rem;
            max-width: 80%;
        }
        
        .user-message {
            background-color: #d1e7ff;
            margin-left: auto;
        }
        
        .ai-message {
            background-color: #e9ecef;
        }
        
        .conversation-list {
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .conversation-item {
            cursor: pointer;
        }
        
        .conversation-item:hover {
            background-color: #f8f9fa;
        }
        
        .conversation-item.active {
            background-color: #e9ecef;
        }
        
        /* Mobile Navigation Fixes */
        @media (max-width: 991.98px) {
            /* Fix dropdown menu positioning on mobile */
            .dropdown-menu {
                position: absolute !important;
                will-change: transform !important;
            }
            
            /* Ensure dropdown items are properly spaced and visible */
            .dropdown-item {
                padding: 0.5rem 1rem;
                clear: both;
                white-space: nowrap;
            }
            
            /* Fix for user dropdown on mobile */
            #mobileUserDropdown + .dropdown-menu {
                min-width: 200px;
                box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            }
            
            /* Ensure navbar-collapse takes full width on mobile */
            .navbar-collapse {
                max-height: 80vh;
                overflow-y: auto;
            }
            
            /* Add some spacing to the collapsed navbar */
            .navbar-nav {
                padding-top: 0.5rem;
                padding-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <div class="navbar-brand">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </div>
            
            <!-- Mobile: User Account Button (Outside Collapse) -->
            <div class="d-flex d-lg-none ms-auto me-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="mobileUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileUserDropdown">
                        <li class="dropdown-item-text">
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Hamburger Menu Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="progress.php">Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                
                <!-- Desktop: User Account Dropdown (Inside Collapse) -->
                <div class="d-none d-lg-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="javascript:void(0);" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- AI Assistant Content -->
    <div class="container py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 mb-4 mb-md-0">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Conversations</h5>
                        <a href="ai-assistant.php?new=1" class="btn btn-sm btn-light" title="New Conversation">
                            <i class="bi bi-plus-lg"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <!-- Search conversations -->
                        <div class="p-2">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="searchConversations" placeholder="Search conversations...">
                                <button class="btn btn-outline-secondary" type="button">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Conversation list -->
                        <div class="list-group list-group-flush conversation-list" style="max-height: 500px; overflow-y: auto;">
                            <?php if (empty($recent_conversations) || !is_array($recent_conversations)): ?>
                                <div class="list-group-item text-center text-muted">
                                    <p class="mb-0">No recent conversations</p>
                                    <small>Start a new conversation using the + button</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_conversations as $conv): ?>
                                    <?php 
                                    // Make sure we have a valid conversation object
                                    if (!isset($conv['conversation_id']) || !isset($conv['title'])) {
                                        continue; // Skip this conversation if it's missing required fields
                                    }
                                    
                                    // Get the first few words of the last message as a preview
                                    $preview = '';
                                    if (isset($conv['last_message'])) {
                                        $preview = substr($conv['last_message'], 0, 40) . (strlen($conv['last_message']) > 40 ? '...' : '');
                                    }
                                    
                                    // Make sure we have valid date fields
                                    $date_display = '';
                                    if (isset($conv['last_message_time']) && !empty($conv['last_message_time'])) {
                                        $date_display = date('M j', strtotime($conv['last_message_time']));
                                    } else if (isset($conv['start_time']) && !empty($conv['start_time'])) {
                                        $date_display = date('M j', strtotime($conv['start_time']));
                                    } else {
                                        $date_display = 'Unknown';
                                    }
                                    ?>
                                    <div class="list-group-item <?php echo (string)$conv['conversation_id'] === (string)$conversation_id ? 'active' : ''; ?> position-relative">
                                        <a href="ai-assistant.php?conversation_id=<?php echo htmlspecialchars($conv['conversation_id']); ?>" class="text-decoration-none <?php echo (string)$conv['conversation_id'] === (string)$conversation_id ? 'text-white' : 'text-dark'; ?>">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($conv['title']); ?></h6>
                                                <div style="margin-right: 25px;"><small><?php echo $date_display; ?></small></div>
                                            </div>
                                            <small class="<?php echo (string)$conv['conversation_id'] === (string)$conversation_id ? 'text-white-50' : 'text-muted'; ?> d-block"><?php echo $preview ? htmlspecialchars($preview) : 'No messages'; ?></small>
                                            <?php if (isset($conv['last_message_time']) && !empty($conv['last_message_time'])): ?>
                                                <small class="<?php echo (string)$conv['conversation_id'] === (string)$conversation_id ? 'text-white-50' : 'text-muted'; ?>"><?php echo date('g:i A', strtotime($conv['last_message_time'])); ?></small>
                                            <?php elseif (isset($conv['start_time']) && !empty($conv['start_time'])): ?>
                                                <small class="<?php echo (string)$conv['conversation_id'] === (string)$conversation_id ? 'text-white-50' : 'text-muted'; ?>"><?php echo date('g:i A', strtotime($conv['start_time'])); ?></small>
                                            <?php endif; ?>
                                        </a>
                                        <!-- Delete button -->
                                        <form method="post" class="delete-conversation-form" style="position: absolute; top: 8px; right: 8px;" onsubmit="return confirm('Are you sure you want to delete this conversation? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete_conversation">
                                            <input type="hidden" name="conversation_id" value="<?php echo htmlspecialchars($conv['conversation_id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete conversation">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-2">
                        <a href="ai-assistant.php" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-plus-circle me-1"></i> New Conversation
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="col-md-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <?php if (isset($current_conversation) && is_array($current_conversation) && !empty($current_conversation) && isset($current_conversation[0]['title'])): ?>
                            <h5 class="mb-0"><?php echo htmlspecialchars($current_conversation[0]['title']); ?></h5>
                        <?php else: ?>
                            <h5 class="mb-0">AcademicEase AI Assistant</h5>
                        <?php endif; ?>
                        <div>
                            <button class="btn btn-sm btn-light" id="renameConversationBtn" title="Rename Conversation">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="ai-assistant.php" class="btn btn-sm btn-light ms-2" title="New Conversation">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Custom Rename conversation dialog (not using Bootstrap modal) -->
                        <div class="custom-modal" id="renameConversationModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999; overflow: auto;">
                            <div class="custom-modal-dialog" style="margin: 10% auto; width: 90%; max-width: 500px; background-color: #fff; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5); position: relative;">
                                <div class="custom-modal-content" style="position: relative; background-color: #fff; border-radius: 5px; overflow: hidden;">
                                    <div class="custom-modal-header" style="padding: 15px; border-bottom: 1px solid #e9ecef; display: flex; justify-content: space-between; align-items: center; background-color: #f8f9fa;">
                                        <h5 style="margin: 0; font-weight: 600; color: #212529;">Rename Conversation</h5>
                                        <button type="button" id="closeRenameModal" style="background: none; border: none; font-size: 1.5rem; font-weight: 700; line-height: 1; cursor: pointer; color: #6c757d;">&times;</button>
                                    </div>
                                    <form action="ai-assistant.php?conversation_id=<?php echo $conversation_id; ?>" method="post" id="renameConversationForm">
                                        <div class="custom-modal-body" style="padding: 20px;">
                                            <input type="hidden" name="action" value="rename_conversation">
                                            <div style="margin-bottom: 15px;">
                                                <label for="conversationTitle" style="display: block; margin-bottom: 8px; font-weight: 500; color: #495057;">Conversation Title</label>
                                                <input type="text" class="form-control" id="conversationTitle" name="conversation_title" value="<?php echo (isset($current_conversation) && is_array($current_conversation) && !empty($current_conversation) && isset($current_conversation[0]['title'])) ? htmlspecialchars($current_conversation[0]['title']) : 'New Conversation'; ?>" required style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; box-shadow: inset 0 1px 2px rgba(0,0,0,0.075);">
                                            </div>
                                        </div>
                                        <div class="custom-modal-footer" style="padding: 15px; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 10px; background-color: #f8f9fa;">
                                            <button type="button" id="cancelRenameModal" class="btn btn-secondary" style="font-weight: 500;">Cancel</button>
                                            <button type="submit" class="btn btn-primary" style="font-weight: 500;">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chat-container mb-3" id="chatContainer">
                            <?php if (empty($messages)): ?>
                                <div class="message ai-message">
                                    <p class="mb-0">Hello! I'm your AcademicEase AI assistant. I'm here to help you understand concepts and learn more effectively.</p>
                                    <p class="mb-0 mt-2">I can assist you with:</p>
                                    <ul>
                                        <li>Understanding homework concepts with explanations and examples</li>
                                        <li>Breaking down complex topics into simpler components</li>
                                        <li>Demonstrating math problem-solving approaches with step-by-step explanations</li>
                                        <li>Explaining scientific concepts with real-world examples</li>
                                        <li>Providing essay structure guidance and brainstorming ideas</li>
                                        <li>Sharing effective study strategies and learning techniques</li>
                                    </ul>
                                    <p class="mb-0 mt-2">I'll guide you through concepts rather than solving your homework directly, helping you develop your own understanding and skills.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message <?php echo isset($message['sender']) && $message['sender'] === 'user' ? 'user-message' : 'ai-message'; ?>">
                                        <p class="mb-0"><?php echo isset($message['content']) ? nl2br(htmlspecialchars($message['content'])) : '(No message content)'; ?></p>
                                        
                                        <?php if (isset($message['media_url']) && !empty($message['media_url'])): ?>
                                            <div class="message-image mt-2 mb-2">
                                                <img src="../<?php echo htmlspecialchars($message['media_url']); ?>" alt="Uploaded image" class="img-fluid rounded" style="max-height: 200px;">
                                            </div>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted"><?php echo isset($message['timestamp']) ? date('g:i A', strtotime($message['timestamp'])) : 'Unknown time'; ?></small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-white">
                            <form id="messageForm" method="post" action="ai-assistant.php?conversation_id=<?php echo urlencode($conversation_id); ?>" enctype="multipart/form-data">
                                <!-- Hidden inputs for conversation tracking -->
                                <input type="hidden" name="conversation_id" value="<?php echo htmlspecialchars($conversation_id); ?>">
                                
                                <!-- Model Selection -->
                                <div class="model-selection mb-3">
                                    <label for="ai-model" class="form-label">AI Model (Optional):</label>
                                    <select id="ai-model" name="ai_model" class="form-select form-select-sm">
                                        <option value="auto">Auto-select (Recommended)</option>
                                        <option value="anthropic/claude-haiku-4.5">Claude Haiku 4.5</option>
                                        <option value="gpt-3.5-turbo:free">GPT-3.5 Turbo (Free)</option>
                                        <option value="meta-llama/llama-3.1-8b-instruct">Llama 3.1 8B</option>
                                    </select>
                                    <small class="form-text text-muted">The system will automatically select the best model if "Auto-select" is chosen.</small>
                                </div>
                                
                                <!-- Message input -->
                                <div class="input-group mb-3">
                                    <input type="text" id="messageInput" name="message" class="form-control" placeholder="Type your question here..." required>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                                    <button type="button" id="imageUploadBtn" class="btn btn-outline-secondary"><i class="bi bi-image"></i></button>
                                </div>
                                
                                <!-- Image upload input -->
                                <input type="file" id="imageUpload" name="image" accept="image/*" style="display: none;">
                                
                                <!-- Image preview container -->
                                <div id="imagePreviewContainer" style="display: none; margin-top: 10px; position: relative;">
                                    <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 5px;">
                                    <button type="button" id="removeImageBtn" class="btn btn-sm btn-danger" style="position: absolute; top: 5px; right: 5px;"><i class="bi bi-x"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Features Card -->
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">AI Assistant Features</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-mortarboard fs-1 text-primary mb-2"></i>
                                        <h5 class="card-title">Learning Support</h5>
                                        <p class="card-text small">Understand homework concepts with explanations and examples to build your knowledge.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-book fs-1 text-primary mb-2"></i>
                                        <h5 class="card-title">Concept Explanations</h5>
                                        <p class="card-text small">Understand complex topics with clear, simple explanations.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <i class="bi bi-pencil-square fs-1 text-primary mb-2"></i>
                                        <h5 class="card-title">Exam Preparation</h5>
                                        <p class="card-text small">Practice with sample questions and get tips for exam success.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="helpModalLabel">How to Use the AI Assistant</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-chat-dots me-2"></i>Asking Questions</h6>
                            <p>Simply type your question in the text box and press Send. You can ask about:</p>
                            <ul>
                                <li>Homework problems</li>
                                <li>Subject concepts</li>
                                <li>Exam preparation</li>
                                <li>Study techniques</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-mic me-2"></i>Voice Input</h6>
                            <p>Click the Voice button to speak your question instead of typing.</p>
                            
                            <h6><i class="bi bi-image me-2"></i>Image Upload</h6>
                            <p>Upload images of problems or diagrams for the AI to analyze.</p>
                        </div>
                    </div>
                    <hr>
                    <h6><i class="bi bi-lightbulb me-2"></i>Tips for Better Results</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul>
                                <li>Be specific with your questions</li>
                                <li>Provide context (e.g., your grade level)</li>
                                <li>For math problems, include all relevant information</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul>
                                <li>Ask for step-by-step explanations</li>
                                <li>Use follow-up questions for clarification</li>
                                <li>Try different approaches if needed</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===== CUSTOM MODAL HANDLING =====
            // Custom implementation to replace Bootstrap modal and fix blinking issues
            const renameModal = document.getElementById('renameConversationModal');
            const renameConversationBtn = document.getElementById('renameConversationBtn');
            const closeRenameModalBtn = document.getElementById('closeRenameModal');
            const cancelRenameModalBtn = document.getElementById('cancelRenameModal');
            
            if (renameModal && renameConversationBtn && closeRenameModalBtn && cancelRenameModalBtn) {
                // Simple function to show the modal
                function showRenameModal() {
                    renameModal.style.display = 'block';
                    document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
                    document.getElementById('conversationTitle').focus();
                }
                
                // Simple function to hide the modal
                function hideRenameModal() {
                    renameModal.style.display = 'none';
                    document.body.style.overflow = ''; // Restore scrolling
                }
                
                // Add click event to show modal
                renameConversationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    showRenameModal();
                });
                
                // Add click events to hide modal
                closeRenameModalBtn.addEventListener('click', hideRenameModal);
                cancelRenameModalBtn.addEventListener('click', hideRenameModal);
                
                // Close modal when clicking outside of it
                renameModal.addEventListener('click', function(e) {
                    if (e.target === renameModal) {
                        hideRenameModal();
                    }
                });
                
                // Close modal with Escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && renameModal.style.display === 'block') {
                        hideRenameModal();
                    }
                });
            }
            
            // ===== CHAT CONTAINER SCROLLING =====
            // Scroll chat to bottom and improve visibility of conversation history
            const chatContainer = document.getElementById('chatContainer');
            if (chatContainer) {
                // Add a small delay to ensure all content is loaded before scrolling
                setTimeout(() => {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }, 100);
                
                // Add a CSS class to improve the chat container's scrolling behavior
                chatContainer.style.overflowY = 'auto';
                chatContainer.style.maxHeight = '60vh';
                chatContainer.style.display = 'flex';
                chatContainer.style.flexDirection = 'column';
                
                // Add a scroll button to allow users to easily scroll to the top of the conversation
                const scrollTopBtn = document.createElement('button');
                scrollTopBtn.className = 'btn btn-sm btn-outline-primary position-absolute';
                scrollTopBtn.style.top = '10px';
                scrollTopBtn.style.right = '10px';
                scrollTopBtn.style.zIndex = '100';
                scrollTopBtn.innerHTML = '<i class="bi bi-arrow-up"></i> View History';
                scrollTopBtn.style.display = 'none'; // Hide initially
                
                // Add the button to the parent of the chat container
                chatContainer.parentNode.style.position = 'relative';
                chatContainer.parentNode.appendChild(scrollTopBtn);
                
                // Show/hide the scroll button based on scroll position
                chatContainer.addEventListener('scroll', function() {
                    if (chatContainer.scrollTop < chatContainer.scrollHeight - chatContainer.clientHeight - 100) {
                        scrollTopBtn.style.display = 'block';
                    } else {
                        scrollTopBtn.style.display = 'none';
                    }
                });
                
                // Scroll to top when button is clicked
                scrollTopBtn.addEventListener('click', function() {
                    chatContainer.scrollTop = 0;
                });
            }
            
            // ===== IMAGE UPLOAD FUNCTIONALITY =====
            // First check for the new image upload elements
            let imageUploadBtn = document.getElementById('image-upload-btn');
            let imageUpload = document.getElementById('image-upload');
            let imagePreview = document.getElementById('image-preview');
            let imagePreviewContainer = document.getElementById('image-preview-container');
            let removeImageBtn = document.getElementById('remove-image-btn');
            
            // If not found, try the old element IDs
            if (!imageUploadBtn) imageUploadBtn = document.getElementById('imageUploadBtn');
            if (!imageUpload) imageUpload = document.getElementById('imageUpload');
            if (!imagePreview) imagePreview = document.getElementById('imagePreview');
            if (!imagePreviewContainer) imagePreviewContainer = document.getElementById('imagePreviewContainer');
            if (!removeImageBtn) removeImageBtn = document.getElementById('removeImageBtn');
            
            if (imageUploadBtn && imageUpload && imagePreview && imagePreviewContainer && removeImageBtn) {
                // Trigger file input when button is clicked
                imageUploadBtn.addEventListener('click', function() {
                    imageUpload.click();
                });
                
                // Show preview when image is selected
                imageUpload.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreviewContainer.style.display = 'block';
                        }
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
                
                // Remove image when button is clicked
                removeImageBtn.addEventListener('click', function() {
                    imageUpload.value = '';
                    imagePreview.src = '';
                    imagePreviewContainer.style.display = 'none';
                });
            }
            
            // ===== SEARCH FUNCTIONALITY =====
            // Search conversations functionality
            const searchConversations = document.getElementById('searchConversations');
            if (searchConversations) {
                searchConversations.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const conversationItems = document.querySelectorAll('.conversation-list .list-group-item');
                    
                    conversationItems.forEach(item => {
                        const title = item.querySelector('h6').textContent.toLowerCase();
                        const preview = item.querySelector('.text-muted').textContent.toLowerCase();
                        
                        if (title.includes(searchTerm) || preview.includes(searchTerm)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            // ===== FORM SUBMISSION =====
            // Form submission using AJAX to prevent page reload
            const messageForm = document.getElementById('messageForm');
            if (messageForm) {
                messageForm.addEventListener('submit', function(e) {
                    e.preventDefault(); // Prevent traditional form submission
                    
                    // Show loading indicator
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const messageInput = document.getElementById('messageInput');
                    const userMessage = messageInput.value.trim();
                    
                    if (!userMessage) return; // Don't submit empty messages
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
                    
                    // Add user message to chat immediately
                    const chatContainer = document.getElementById('chatContainer');
                    const userMessageDiv = document.createElement('div');
                    userMessageDiv.className = 'message user-message';
                    userMessageDiv.innerHTML = `<p class="mb-0">${userMessage.replace(/\n/g, '<br>')}</p>
                                              <small class="text-muted">${new Date().toLocaleTimeString([], {hour: 'numeric', minute:'2-digit'})}</small>`;
                    chatContainer.appendChild(userMessageDiv);
                    
                    // Scroll to the new message
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                    
                    // Prepare form data
                    const formData = new FormData(this);
                    
                    // Send AJAX request
                    fetch(this.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        // Create a temporary div to parse the HTML response
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = html;
                        
                        // Extract the AI response from the HTML
                        const newMessages = tempDiv.querySelectorAll('#chatContainer .message');
                        if (newMessages.length > 0) {
                            // Get the last message (AI response)
                            const aiMessage = newMessages[newMessages.length - 1];
                            if (aiMessage.classList.contains('ai-message')) {
                                chatContainer.appendChild(aiMessage.cloneNode(true));
                            }
                        }
                        
                        // Scroll to the new message
                        chatContainer.scrollTop = chatContainer.scrollHeight;
                        
                        // Reset form
                        messageInput.value = '';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send"></i>';
                        
                        // Reset image preview if any
                        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
                        if (imagePreviewContainer) {
                            imagePreviewContainer.style.display = 'none';
                        }
                        const imageUpload = document.getElementById('imageUpload');
                        if (imageUpload) {
                            imageUpload.value = '';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Show error message
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger';
                        errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i> Failed to send message. Please try again.';
                        chatContainer.appendChild(errorDiv);
                        
                        // Reset button
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-send"></i>';
                    });
                });
            }
        });
    </script>
    
    <!-- Performance optimization script -->
    <script src="/assets/js/performance.js"></script>
</body>
</html>
