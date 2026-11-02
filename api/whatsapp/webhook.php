<?php
/**
 * Academic Ease - WhatsApp Integration Webhook
 * Handles incoming messages from WhatsApp Business API
 */

// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';

// Set headers
header('Content-Type: application/json');

// Log incoming webhook data
$webhook_log = fopen('../../logs/whatsapp_webhook.log', 'a');
$input = file_get_contents('php://input');
fwrite($webhook_log, date('Y-m-d H:i:s') . ' - Webhook received' . PHP_EOL);
fclose($webhook_log);

// Parse the incoming JSON
$data = json_decode($input, true);

// Verify webhook
if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe' && isset($_GET['hub_verify_token'])) {
    if ($_GET['hub_verify_token'] === WHATSAPP_VERIFY_TOKEN) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        exit;
    }
}

$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expectedSignature = 'sha256=' . hash_hmac('sha256', $input, WHATSAPP_ACCESS_TOKEN);
if (empty($signature) || !hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
    exit;
}

// Process incoming messages
try {
    // Check if this is a WhatsApp message notification
    if (isset($data['entry']) && is_array($data['entry'])) {
        foreach ($data['entry'] as $entry) {
            if (isset($entry['changes']) && is_array($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if (isset($change['value']['messages']) && is_array($change['value']['messages'])) {
                        foreach ($change['value']['messages'] as $message) {
                            // Get message details
                            $from = $message['from']; // The WhatsApp number that sent the message
                            $message_id = $message['id'];
                            $timestamp = $message['timestamp'];
                            
                            // Get user by WhatsApp number
                            $users = supabaseSelect('users', [
                                'filter' => [
                                    'whatsapp_number.eq' => $from
                                ]
                            ]);
                            
                            // If user doesn't exist, create a new user with trial subscription
                            if (empty($users)) {
                                // Create a new user
                                $user_data = [
                                    'name' => 'WhatsApp User',
                                    'whatsapp_number' => $from,
                                    'user_type' => 'student',
                                    'registration_date' => date('c'),
                                    'subscription_status' => 'trial',
                                    'subscription_plan' => 'trial',
                                    'subscription_start_date' => date('c'),
                                    'subscription_end_date' => date('c', strtotime('+7 days'))
                                ];
                                
                                $new_user = supabaseInsert('users', $user_data);
                                
                                if (isset($new_user['error'])) {
                                    throw new Exception('Failed to create new user: ' . $new_user['message']);
                                }
                                
                                $user_id = $new_user[0]['user_id'];
                                
                                // Send welcome message
                                sendWhatsAppMessage($from, "Welcome to Academic Ease! 🎓\n\nI'm your AI study assistant, ready to help with your homework and studies. You're on a 7-day free trial.\n\nHow can I help you today?");
                            } else {
                                $user_id = $users[0]['user_id'];
                                
                                // Check if subscription is active
                                $subscription_status = $users[0]['subscription_status'];
                                $subscription_end_date = strtotime($users[0]['subscription_end_date']);
                                $current_time = time();
                                
                                if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
                                    $subscription_status === 'expired') {
                                    // Subscription expired
                                    sendWhatsAppMessage($from, "Your Academic Ease subscription has expired. Please visit our website to renew your subscription: " . APP_URL);
                                    continue;
                                }
                            }
                            
                            // Process different message types
                            if (isset($message['text'])) {
                                // Text message
                                $text = $message['text']['body'];
                                processTextMessage($user_id, $from, $text);
                            } elseif (isset($message['image'])) {
                                // Image message
                                $image_id = $message['image']['id'];
                                processImageMessage($user_id, $from, $image_id);
                            } elseif (isset($message['audio'])) {
                                // Audio message
                                $audio_id = $message['audio']['id'];
                                processAudioMessage($user_id, $from, $audio_id);
                            } else {
                                // Unsupported message type
                                sendWhatsAppMessage($from, "I can only process text, image, and audio messages at the moment. Please try again with one of these formats.");
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Return success response
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    // Log error
    error_log('WhatsApp Webhook Error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Webhook processing failed']);
}

/**
 * Process text message from WhatsApp
 * 
 * @param string $user_id User ID
 * @param string $from WhatsApp number
 * @param string $text Message text
 */
function processTextMessage($user_id, $from, $text) {
    // Check for commands
    if (strtolower($text) === 'help') {
        sendWhatsAppMessage($from, "Academic Ease Help:\n\n" .
            "• Ask any homework question\n" .
            "• Send an image of your homework\n" .
            "• Send a voice note with your question\n" .
            "• Type 'subscription' to check your subscription status\n" .
            "• Type 'help' to see this message again");
        return;
    }
    
    if (strtolower($text) === 'subscription') {
        // Get user subscription details
        $users = supabaseSelect('users', [
            'filter' => [
                'user_id.eq' => $user_id
            ]
        ]);
        
        if (!empty($users)) {
            $user = $users[0];
            $subscription_status = $user['subscription_status'];
            $subscription_plan = $user['subscription_plan'];
            $subscription_end_date = new DateTime($user['subscription_end_date']);
            $current_date = new DateTime();
            
            $days_left = $current_date->diff($subscription_end_date)->days;
            
            $message = "Your Subscription Details:\n\n" .
                "Status: " . ucfirst($subscription_status) . "\n" .
                "Plan: " . ucfirst($subscription_plan) . "\n" .
                "Expires: " . $subscription_end_date->format('j M Y') . "\n" .
                "Days Left: " . $days_left . "\n\n" .
                "Visit " . APP_URL . " to manage your subscription.";
            
            sendWhatsAppMessage($from, $message);
        }
        return;
    }
    
    // Get or create conversation
    $conversation_id = getOrCreateConversation($user_id, 'whatsapp');
    
    // Save user message to database
    supabaseInsert('messages', [
        'conversation_id' => $conversation_id,
        'sender' => 'user',
        'content' => $text,
        'content_type' => 'text',
        'timestamp' => date('c')
    ]);
    
    // Get conversation history for context
    $history = getConversationHistory($conversation_id);
    
    // Prepare prompt with educational context
    $prompt = prepareEducationalPrompt($text, $history);
    
    // Call Gemini API
    $response = callGeminiApi($prompt);
    
    // Save AI response to database
    supabaseInsert('messages', [
        'conversation_id' => $conversation_id,
        'sender' => 'ai',
        'content' => $response,
        'content_type' => 'text',
        'timestamp' => date('c')
    ]);
    
    // Update conversation end time
    supabaseUpdate('conversations', [
        'end_time' => date('c')
    ], 'conversation_id=eq.' . $conversation_id);
    
    // Send response back to WhatsApp
    sendWhatsAppMessage($from, $response);
}

/**
 * Process image message from WhatsApp
 * 
 * @param string $user_id User ID
 * @param string $from WhatsApp number
 * @param string $image_id Image ID
 */
function processImageMessage($user_id, $from, $image_id) {
    // Send acknowledgment
    sendWhatsAppMessage($from, "I received your image. Let me analyze it...");
    
    // Get image content from WhatsApp
    $image_url = getMediaUrl($image_id);
    
    if (!$image_url) {
        sendWhatsAppMessage($from, "Sorry, I couldn't process your image. Please try again.");
        return;
    }
    
    // Download image
    $image_data = file_get_contents($image_url);
    
    if (!$image_data) {
        sendWhatsAppMessage($from, "Sorry, I couldn't download your image. Please try again.");
        return;
    }
    
    // Create upload directory if it doesn't exist
    $upload_dir = '../../uploads/images/whatsapp/' . date('Y/m/d');
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Save image to file
    $filename = uniqid() . '.jpg';
    $file_path = $upload_dir . '/' . $filename;
    file_put_contents($file_path, $image_data);
    
    // Get or create conversation
    $conversation_id = getOrCreateConversation($user_id, 'whatsapp');
    
    // Save user message (image) to database
    supabaseInsert('messages', [
        'conversation_id' => $conversation_id,
        'sender' => 'user',
        'content' => 'Image uploaded',
        'content_type' => 'image',
        'media_url' => $file_path,
        'timestamp' => date('c')
    ]);
    
    // Process image with Gemini AI
    $response = processImageWithGemini($file_path, $conversation_id);
    
    // Save AI response to database
    supabaseInsert('messages', [
        'conversation_id' => $conversation_id,
        'sender' => 'ai',
        'content' => $response,
        'content_type' => 'text',
        'timestamp' => date('c')
    ]);
    
    // Update conversation end time
    supabaseUpdate('conversations', [
        'end_time' => date('c')
    ], 'conversation_id=eq.' . $conversation_id);
    
    // Send response back to WhatsApp
    sendWhatsAppMessage($from, $response);
}

/**
 * Process audio message from WhatsApp
 * 
 * @param string $user_id User ID
 * @param string $from WhatsApp number
 * @param string $audio_id Audio ID
 */
function processAudioMessage($user_id, $from, $audio_id) {
    // Send acknowledgment
    sendWhatsAppMessage($from, "I received your voice message. Let me process it...");
    
    // Get audio content from WhatsApp
    $audio_url = getMediaUrl($audio_id);
    
    if (!$audio_url) {
        sendWhatsAppMessage($from, "Sorry, I couldn't process your voice message. Please try again.");
        return;
    }
    
    // In a real implementation, you would:
    // 1. Download the audio file
    // 2. Convert it to the right format if needed
    // 3. Use a speech-to-text service to transcribe it
    
    // For this implementation, we'll simulate a transcription
    $transcription = "This is a simulated transcription of the voice message. In a real implementation, you would use a speech-to-text service.";
    
    // Get or create conversation
    $conversation_id = getOrCreateConversation($user_id, 'whatsapp');
    
    // Save user message (audio) to database
    supabaseInsert('messages', [
        'conversation_id' => $conversation_id,
        'sender' => 'user',
        'content' => $transcription,
        'content_type' => 'voice',
        'timestamp' => date('c')
    ]);
    
    // Get conversation history for context
    $history = getConversationHistory($conversation_id);
    
    // Prepare prompt with educational context
    $prompt = prepareEducationalPrompt($transcription, $history);
    
    // Call Gemini API
    $response = callGeminiApi($prompt);
    
    // Save AI response to database
    supabaseInsert('messages', [
        'conversation_id' => $conversation_id,
        'sender' => 'ai',
        'content' => $response,
        'content_type' => 'text',
        'timestamp' => date('c')
    ]);
    
    // Update conversation end time
    supabaseUpdate('conversations', [
        'end_time' => date('c')
    ], 'conversation_id=eq.' . $conversation_id);
    
    // Send response back to WhatsApp
    sendWhatsAppMessage($from, $response);
}

/**
 * Get or create a conversation for the user
 * 
 * @param string $user_id User ID
 * @param string $platform Platform (whatsapp)
 * @return string Conversation ID
 */
function getOrCreateConversation($user_id, $platform) {
    // Check for an existing active conversation
    $conversations = supabaseSelect('conversations', [
        'filter' => [
            'user_id.eq' => $user_id,
            'platform.eq' => $platform
        ],
        'order' => 'start_time.desc',
        'limit' => 1
    ]);
    
    if (!empty($conversations)) {
        $last_conversation = $conversations[0];
        $last_time = strtotime($last_conversation['end_time'] ?? $last_conversation['start_time']);
        
        // If the last conversation was within the last hour, continue it
        if (time() - $last_time < 3600) {
            return $last_conversation['conversation_id'];
        }
    }
    
    // Create a new conversation
    $new_conversation = supabaseInsert('conversations', [
        'user_id' => $user_id,
        'start_time' => date('c'),
        'platform' => $platform,
        'subject' => 'General',
        'topic' => 'WhatsApp Conversation'
    ]);
    
    return $new_conversation[0]['conversation_id'];
}

/**
 * Get conversation history
 * 
 * @param string $conversation_id Conversation ID
 * @param int $limit Maximum number of messages to retrieve
 * @return array Conversation messages
 */
function getConversationHistory($conversation_id, $limit = 5) {
    // Get recent messages from conversation
    $messages = supabaseSelect('messages', [
        'filter' => [
            'conversation_id.eq' => $conversation_id
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
 * @param string $message User message
 * @param array $history Conversation history
 * @return string Formatted prompt
 */
function prepareEducationalPrompt($message, $history) {
    // Start with system prompt
    $prompt = "You are Academic Ease, an AI tutor designed to help Ghanaian JHS and SHS students with their studies. ";
    $prompt .= "You provide clear, step-by-step explanations aligned with the Ghanaian curriculum. ";
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
    
    // Add current message
    $prompt .= "Student: " . $message . "\n";
    $prompt .= "Academic Ease: ";
    
    return $prompt;
}

/**
 * Process image with Gemini AI
 * 
 * @param string $imagePath Path to the image file
 * @param string $conversationId Conversation ID
 * @return string AI response
 */
function processImageWithGemini($imagePath, $conversationId) {
    // Get conversation history for context
    $history = getConversationHistory($conversationId);
    
    // Read image file as base64
    $imageData = base64_encode(file_get_contents($imagePath));
    
    // Prepare prompt with educational context
    $prompt = prepareImagePrompt($history);
    
    // Call Gemini Vision API
    $response = callGeminiVisionApi($prompt, $imageData);
    
    return $response;
}

/**
 * Prepare prompt for image analysis
 * 
 * @param array $history Conversation history
 * @return string Formatted prompt
 */
function prepareImagePrompt($history) {
    // Start with system prompt
    $prompt = "You are Academic Ease, an AI tutor designed to help Ghanaian JHS and SHS students with their studies. ";
    $prompt .= "You're analyzing an image of a homework problem or educational content. ";
    $prompt .= "Provide a detailed, step-by-step explanation of the content in the image. ";
    $prompt .= "If it's a homework problem, solve it completely showing all steps. ";
    $prompt .= "If it's a concept or diagram, explain it thoroughly in an educational manner. ";
    $prompt .= "Always provide explanations that help students understand concepts, not just answers.";
    
    // Add conversation history for context if available
    if (!empty($history)) {
        $prompt .= "\n\nPrevious conversation: ";
        foreach ($history as $msg) {
            $role = $msg['sender'] === 'user' ? 'Student' : 'Academic Ease';
            $prompt .= "\n{$role}: {$msg['content']}";
        }
    }
    
    return $prompt;
}

/**
 * Call Gemini API
 * 
 * @param string $prompt Formatted prompt
 * @return string AI response
 */
function callGeminiApi($prompt) {
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
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 1024
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
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
    
    // Handle SSL certificate verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    // For production, use a proper certificate bundle:
    // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('Gemini API Error: ' . curl_error($ch));
        curl_close($ch);
        return "I'm sorry, I'm having trouble processing your message right now. Please try again in a moment.";
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Extract text from response
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } else {
        error_log('Unexpected Gemini API response format: ' . $response);
        return "I'm sorry, I couldn't generate a helpful response. Please try rephrasing your question.";
    }
}

/**
 * Call Gemini Vision API
 * 
 * @param string $prompt Formatted prompt
 * @param string $imageData Base64-encoded image data
 * @return string AI response
 */
function callGeminiVisionApi($prompt, $imageData) {
    // Gemini Vision API endpoint
    // Using the latest API version and model name
    $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-1.5-pro-vision:generateContent?key=' . GEMINI_API_KEY;
    
    // Prepare request data
    $data = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => 'image/jpeg',
                            'data' => $imageData
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.4,
            'topK' => 32,
            'topP' => 0.95,
            'maxOutputTokens' => 1024
        ],
        'safetySettings' => [
            [
                'category' => 'HARM_CATEGORY_HARASSMENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_HATE_SPEECH',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ],
            [
                'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
            ]
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
    
    // Handle SSL certificate verification
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    // For production, use a proper certificate bundle:
    // curl_setopt($ch, CURLOPT_CAINFO, '/path/to/cacert.pem');
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('Gemini Vision API Error: ' . curl_error($ch));
        curl_close($ch);
        return "I'm sorry, I'm having trouble analyzing this image right now. Please try again in a moment.";
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Extract text from response
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    } else {
        error_log('Unexpected Gemini Vision API response format: ' . $response);
        return "I'm sorry, I couldn't analyze this image properly. Please try uploading a clearer image or describing your question in text.";
    }
}

/**
 * Send message to WhatsApp
 * 
 * @param string $to Recipient WhatsApp number
 * @param string $message Message text
 * @return bool Success status
 */
function sendWhatsAppMessage($to, $message) {
    // WhatsApp Business API endpoint
    $url = 'https://graph.facebook.com/v17.0/' . WHATSAPP_PHONE_NUMBER_ID . '/messages';
    
    // Prepare request data
    $data = [
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => $to,
        'type' => 'text',
        'text' => [
            'preview_url' => false,
            'body' => $message
        ]
    ];
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN,
        'Content-Type: application/json'
    ]);
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('WhatsApp API Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if message was sent successfully
    if (isset($responseData['messages']) && isset($responseData['messages'][0]['id'])) {
        return true;
    } else {
        error_log('WhatsApp API Error: ' . $response);
        return false;
    }
}

/**
 * Get media URL from WhatsApp
 * 
 * @param string $media_id Media ID
 * @return string|false Media URL or false on failure
 */
function getMediaUrl($media_id) {
    // WhatsApp Business API endpoint for media
    $url = 'https://graph.facebook.com/v17.0/' . $media_id;
    
    // Initialize cURL session
    $ch = curl_init($url);
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN
    ]);
    
    // Execute cURL request
    $response = curl_exec($ch);
    
    // Check for errors
    if (curl_errno($ch)) {
        error_log('WhatsApp Media API Error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    // Close cURL session
    curl_close($ch);
    
    // Parse response
    $responseData = json_decode($response, true);
    
    // Check if media URL is available
    if (isset($responseData['url'])) {
        // Get the media content
        $media_url = $responseData['url'];
        $ch = curl_init($media_url);
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . WHATSAPP_ACCESS_TOKEN
        ]);
        
        // Execute cURL request
        $media_content = curl_exec($ch);
        
        // Check for errors
        if (curl_errno($ch)) {
            error_log('WhatsApp Media Download Error: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }
        
        // Close cURL session
        curl_close($ch);
        
        return $media_url;
    } else {
        error_log('WhatsApp Media API Error: ' . $response);
        return false;
    }
}
