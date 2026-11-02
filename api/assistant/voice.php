<?php
/**
 * Academic Ease - AI Assistant Voice API
 * Handles processing of voice recordings sent to the Gemini AI
 */

// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/gemini_helper.php'; // Include Gemini helper functions

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

// Check if audio was uploaded
if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'No audio uploaded or upload error'
    ]);
    exit;
}

// Validate audio file
$file = $_FILES['audio'];
$allowedTypes = ['audio/wav', 'audio/mpeg', 'audio/mp3', 'audio/webm'];

// Note: Browser may not always set the correct MIME type for audio recordings
// So we'll be more lenient with audio file validation

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
    $uploadDir = '../../uploads/audio/' . date('Y/m/d');
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_recording.wav';
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
            'topic' => 'Voice Question'
        ]);
        
        $conversationId = $conversation[0]['conversation_id'];
    }
    
    // Upload audio to Supabase Storage
    // Use user ID for secure file organization
    $userId = $_SESSION['user_id'];
    
    // Upload file with user ID for better security
    $storageResponse = supabaseUploadFile('academic-ease', 'uploads/' . $filename, $uploadPath, $userId);
    
    if (isset($storageResponse['error'])) {
        throw new Exception('Failed to upload audio to storage: ' . $storageResponse['message']);
    }
    
    // Get public URL for the uploaded audio
    $mediaUrl = supabaseGetPublicUrl('academic-ease', 'uploads/' . $filename, $userId);
    
    // Save user message (audio) to database
    supabaseInsert('messages', [
        'conversation_id' => $conversationId,
        'sender' => 'user',
        'content' => 'Voice recording',
        'content_type' => 'voice',
        'media_url' => $mediaUrl,
        'timestamp' => date('c')
    ]);
    
    // Process audio with speech-to-text and then Gemini AI
    // For this implementation, we'll simulate speech-to-text by assuming a question about the selected subject
    // In a real implementation, you would integrate with a speech-to-text service like Google Speech-to-Text
    
    // Simulated transcription based on subject
    $transcription = getSimulatedTranscription($subject);
    
    // Process transcription with Gemini AI
    $aiResponse = processWithGemini($transcription, $conversationId, $subject);
    
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
        'media_url' => $mediaUrl,
        'transcription' => $transcription
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log('AI Assistant Voice Error: ' . $e->getMessage());
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'An error occurred while processing your voice recording. Please try again.'
    ]);
}

/**
 * Get simulated transcription based on subject
 * 
 * Note: In a real implementation, this would be replaced with actual speech-to-text processing
 * 
 * @param string $subject Subject context
 * @return string Simulated transcription
 */
function getSimulatedTranscription($subject) {
    $transcriptions = [
        'Mathematics' => 'Can you explain how to solve quadratic equations?',
        'Science' => 'What is the process of photosynthesis?',
        'English' => 'Can you explain the main themes in Things Fall Apart?',
        'Social Studies' => 'What are the major natural resources in Ghana?',
        'ICT' => 'What is the difference between RAM and ROM?',
        'BECE' => 'What topics should I focus on for BECE Science?',
        'WASSCE' => 'How do I prepare for the WASSCE Mathematics exam?',
        'General' => 'Can you help me understand this concept better?'
    ];
    
    return isset($transcriptions[$subject]) ? $transcriptions[$subject] : $transcriptions['General'];
}

/**
 * Process transcription with Gemini AI
 * 
 * @param string $transcription Transcribed text from audio
 * @param string $conversationId Conversation ID
 * @param string $subject Subject context
 * @return string AI response
 */
function processWithGemini($transcription, $conversationId, $subject) {
    // Get conversation history for context
    $history = getConversationHistory($conversationId);
    
    // Prepare prompt with educational context
    $prompt = prepareEducationalPrompt($transcription, $history, $subject);
    
    // Call Gemini API using helper function
    return callGeminiApi($prompt);
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
 * Prepare educational prompt for Gemini
 * 
 * @param string $transcription Transcribed text from audio
 * @param array $history Conversation history
 * @param string $subject Subject context
 * @return string Formatted prompt
 */
function prepareEducationalPrompt($transcription, $history, $subject) {
    // Start with system prompt
    $prompt = "You are Academic Ease, an AI tutor designed to help Ghanaian JHS and SHS students with their studies. ";
    $prompt .= "You provide clear, step-by-step explanations aligned with the Ghanaian curriculum. ";
    
    if ($subject !== 'General') {
        $prompt .= "The subject is {$subject}. ";
    }
    
    $prompt .= "You should be helpful, educational, and encouraging. ";
    $prompt .= "If you don't know something, admit it rather than making up information. ";
    $prompt .= "Always provide explanations that help students understand concepts, not just answers.\n\n";
    
    // Add conversation history for context
    if (!empty($history)) {
        foreach ($history as $msg) {
            $role = $msg['sender'] === 'user' ? 'Student' : 'Academic Ease';
            $prompt .= $role . ": " . $msg['content'] . "\n";
        }
    }
    
    // Add current transcription
    $prompt .= "Student: " . $transcription . "\n";
    $prompt .= "Academic Ease: ";
    
    return $prompt;
}
