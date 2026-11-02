<?php
/**
 * Academic Ease - AI Assistant Image API
 * Handles processing of image uploads sent to the AI service
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

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Check if image was uploaded
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'No image uploaded or upload error'
    ]);
    exit;
}

// Validate image file
$file = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];

if (!in_array($file['type'], $allowedTypes)) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'
    ]);
    exit;
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'File is too large. Maximum size is ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB.'
    ]);
    exit;
}

// Get conversation ID if provided
$conversationId = isset($_POST['conversation_id']) ? $_POST['conversation_id'] : null;
$subject = isset($_POST['subject']) ? $_POST['subject'] : 'General';

try {
    // Create upload directory if it doesn't exist
    $uploadDir = '../../uploads/images/' . date('Y/m/d');
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . basename($file['name']);
    $uploadPath = $uploadDir . '/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
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
            'subject' => $subject,
            'topic' => 'Image Analysis'
        ]);
        
        $conversationId = $conversation[0]['conversation_id'];
    }
    
    // Upload image to Supabase Storage
    // Use user ID for secure file organization
    $userId = $_SESSION['user_id'];
    
    // Upload file with user ID for better security
    $storageResponse = supabaseUploadFile('academic-ease', 'uploads/' . $filename, $uploadPath, $userId);
    
    if (isset($storageResponse['error'])) {
        throw new Exception('Failed to upload image to storage: ' . $storageResponse['message']);
    }
    
    // Get public URL for the uploaded image
    $mediaUrl = supabaseGetPublicUrl('academic-ease', 'uploads/' . $filename, $userId);
    
    // Save user message (image) to database
    supabaseInsert('messages', [
        'conversation_id' => $conversationId,
        'sender' => 'user',
        'content' => 'Image uploaded',
        'content_type' => 'image',
        'media_url' => $mediaUrl,
        'timestamp' => date('c')
    ]);
    
    // Get user message if provided
    $userMessage = isset($_POST['message']) ? trim($_POST['message']) : 'Please analyze this image.';
    
    // Process image with OpenRouter AI
    $relativePath = str_replace('../../', '', $uploadPath);
    $aiResponse = processImageWithOpenRouter($userMessage, $relativePath, $conversationId);
    
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
        'conversation_id' => $conversationId,
        'media_url' => $mediaUrl
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('AI Assistant Image Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred while processing your image. Please try again.'
    ]);
}

/**
 * Process image with AI Service
 * 
 * @param string $imagePath Path to image file
 * @param string $subject Subject context
 * @param string $conversationId Conversation ID
 * @return string AI response
 */
function processImageWithGemini($imagePath, $subject, $conversationId) {
    // Get conversation history
    $history = getConversationHistory($conversationId);
    
    // Read image file as base64
    $imageData = base64_encode(file_get_contents($imagePath));
    
    // Get MIME type of the image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $imagePath);
    finfo_close($finfo);
    
    // Prepare prompt with educational context
    $prompt = prepareImagePrompt($subject, $history);
    
    // Generate response using Gemini API directly
    try {
        // Check if Gemini API key is set
        if (!isGeminiApiKeySet()) {
            error_log('Gemini API key is not set');
            return "I'm sorry, the AI service is not properly configured. Please contact support.";
        }
        
        // Call Gemini API for image analysis
        $response = analyzeImageWithGemini($prompt, $imageData, $mimeType);
        
        // Log successful API call
        error_log('Gemini Vision API call successful');
        
        return $response;
    } catch (Exception $e) {
        error_log('AI Service Error: ' . $e->getMessage());
        return "I'm sorry, I'm having trouble analyzing this image right now. Please try again in a few moments or describe the content of the image in a text message instead.";
    }
}

/**
 * Get conversation history
 * 
 * @param string $conversationId Conversation ID
 * @param int $limit Maximum number of messages to retrieve
 * @return array Conversation messages
 */
function getConversationHistory($conversationId, $limit = 5) {
    if (!$conversationId) {
        return [];
    }
    
    // Get recent messages from conversation
    $messages = supabaseSelect('messages', [
        'filter' => [
            'conversation_id.eq' => $conversationId,
            'content_type.eq' => 'text'
        ],
        'order' => 'timestamp.desc',
        'limit' => $limit
    ]);
    
    // Reverse to get chronological order
    return array_reverse($messages);
}

/**
 * Prepare prompt for image analysis with Gemini that focuses on explaining concepts rather than solving homework
 * 
 * @param string $subject Subject context
 * @param array $history Conversation history
 * @return string Formatted prompt
 */
function prepareImagePrompt($subject, $history) {
    // Start with system prompt focused on teaching rather than solving
    $prompt = "You are Academic Ease, an AI educational assistant designed to help Ghanaian JHS and SHS students understand their schoolwork better.\n";
    $prompt .= "You're analyzing an image that may contain a homework problem, educational content, or a student question.\n\n";
    
    if ($subject !== 'General') {
        $prompt .= "The subject context is {$subject}.\n\n";
    }
    
    $prompt .= "IMPORTANT EDUCATIONAL GUIDELINES:\n";
    $prompt .= "1. NEVER solve homework problems directly for students. Instead, explain the underlying concepts and guide them to find the solution themselves.\n";
    $prompt .= "2. When shown a homework problem, first identify the topic and explain the relevant concepts, then provide a similar example with a worked solution to demonstrate the approach.\n";
    $prompt .= "3. If it's a diagram or concept, explain it thoroughly in an educational manner, relating it to real-world applications where possible.\n";
    $prompt .= "4. If it appears to be an exam or test question, provide guidance on the approach without giving the exact answer.\n";
    $prompt .= "5. Use the Socratic method - suggest guiding questions that would lead students to discover answers themselves.\n";
    $prompt .= "6. If you detect that a student is asking you to complete an assignment for them, politely redirect them to learning the material.\n";
    $prompt .= "7. Always be encouraging and supportive, emphasizing that making mistakes is part of the learning process.\n\n";
    
    $prompt .= "EXAMPLES OF GOOD RESPONSES:\n";
    $prompt .= "- If shown a math problem, explain the mathematical concept involved and the general approach to solving such problems, then suggest they try applying these principles.\n";
    $prompt .= "- If shown a science diagram, explain what it represents, the key components, and how they relate to each other, then ask questions to check understanding.\n";
    $prompt .= "- If shown an essay question, discuss the topic and provide an outline structure, but don't write the actual essay.\n\n";
    
    // Add conversation history for context if available
    if (!empty($history)) {
        $prompt .= "Previous conversation: ";
        foreach ($history as $msg) {
            $role = $msg['sender'] === 'user' ? 'Student' : 'Academic Ease';
            $prompt .= "\n{$role}: {$msg['content']}";
        }
        $prompt .= "\n";
    }
    
    return $prompt;
}