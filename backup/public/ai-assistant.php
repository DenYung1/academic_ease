<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    $users = supabaseSelect('users', [
        'filter' => [
            'user_id.eq' => $_SESSION['user_id']
        ]
    ]);
    
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
    
    // Check if user has selected a plan but not completed payment
    // This handles existing users who have incomplete subscriptions
    if ($subscription_status === 'trial' && empty($user['subscription_plan'])) {
        // User has a trial but hasn't selected a paid plan yet
        // Redirect to subscription page to complete their subscription
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    
    // Calculate remaining time for trial users
    $is_trial = ($subscription_status === 'trial');
    $time_remaining = $subscription_end_date - $current_time;
    $hours_remaining = floor($time_remaining / 3600);
    $minutes_remaining = floor(($time_remaining % 3600) / 60);
    
    // Get conversation history if conversation ID is provided
    $conversation_id = isset($_GET['conversation']) ? $_GET['conversation'] : null;
    $messages = [];
    
    if ($conversation_id) {
        // Verify conversation belongs to user
        $conversation = supabaseSelect('conversations', [
            'filter' => [
                'conversation_id.eq' => $conversation_id,
                'user_id.eq' => $_SESSION['user_id']
            ]
        ]);
        
        if (empty($conversation)) {
            $conversation_id = null;
        } else {
            // Get messages for this conversation
            $messages = supabaseSelect('messages', [
                'filter' => [
                    'conversation_id.eq' => $conversation_id
                ],
                'order' => 'timestamp.asc'
            ]);
        }
    }
    
    // Get recent conversations for sidebar
    $recent_conversations = supabaseSelect('conversations', [
        'filter' => [
            'user_id.eq' => $_SESSION['user_id']
        ],
        'order' => 'start_time.desc',
        'limit' => 10
    ]);
    
} catch (Exception $e) {
    error_log('AI Assistant Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the AI assistant. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../assets/img/logo.png" alt="Academic Ease Logo" height="40">
                Academic Ease
            </a>
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
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="subscription.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
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
            <div class="col-lg-3 mb-4 mb-lg-0">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Conversations</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="d-grid gap-2 p-3">
                            <a href="ai-assistant.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>New Conversation
                            </a>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php if (empty($recent_conversations)): ?>
                                <div class="list-group-item text-center text-muted py-4">
                                    No conversations yet
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_conversations as $conv): ?>
                                    <a href="ai-assistant.php?conversation=<?php echo $conv['conversation_id']; ?>" class="list-group-item list-group-item-action <?php echo ($conversation_id === $conv['conversation_id']) ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1 text-truncate"><?php echo htmlspecialchars($conv['subject']); ?></h6>
                                            <small><?php echo date('j M', strtotime($conv['start_time'])); ?></small>
                                        </div>
                                        <small class="text-truncate d-block"><?php echo htmlspecialchars($conv['topic']); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Chat Area -->
            <div class="col-lg-9">
                <?php if ($is_trial): ?>
                <div class="alert alert-info mb-3">
                    <strong>Free Trial:</strong> You have <?php echo $hours_remaining; ?> hours and <?php echo $minutes_remaining; ?> minutes remaining in your free trial.
                    <a href="subscribe-direct.php" class="alert-link">Upgrade now</a> to continue using all features after your trial expires.
                </div>
                <?php endif; ?>
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">AI Homework Assistant</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light dropdown-toggle" type="button" id="subjectDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Select Subject
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="subjectDropdown">
                                <li><a class="dropdown-item" href="#" data-subject="Mathematics">Mathematics</a></li>
                                <li><a class="dropdown-item" href="#" data-subject="Science">Science</a></li>
                                <li><a class="dropdown-item" href="#" data-subject="English">English</a></li>
                                <li><a class="dropdown-item" href="#" data-subject="Social Studies">Social Studies</a></li>
                                <li><a class="dropdown-item" href="#" data-subject="ICT">ICT</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" data-subject="BECE">BECE Preparation</a></li>
                                <li><a class="dropdown-item" href="#" data-subject="WASSCE">WASSCE Preparation</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="chat-container">
                            <div class="chat-messages" id="chat-messages">
                                <?php if (empty($messages)): ?>
                                    <!-- Welcome message for new conversation -->
                                    <div class="message message-ai">
                                        <p>Hello <?php echo htmlspecialchars($user['full_name']); ?>! I'm your Academic Ease AI assistant. How can I help you with your studies today?</p>
                                        <p>You can ask me questions about your homework, request explanations for difficult concepts, or get help preparing for exams like BECE or WASSCE.</p>
                                        <p>Try asking something like:</p>
                                        <ul>
                                            <li>"Can you explain how to solve quadratic equations?"</li>
                                            <li>"What are the key themes in 'Things Fall Apart'?"</li>
                                            <li>"Help me understand photosynthesis"</li>
                                            <li>"What topics should I focus on for BECE Science?"</li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <!-- Display existing conversation -->
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message message-<?php echo $message['sender']; ?>">
                                            <?php 
                                            if ($message['content_type'] === 'text') {
                                                echo nl2br(htmlspecialchars($message['content']));
                                            } elseif ($message['content_type'] === 'image') {
                                                echo '<img src="' . htmlspecialchars($message['media_url']) . '" alt="Shared image" class="img-fluid rounded">';
                                            } elseif ($message['content_type'] === 'voice') {
                                                echo '<audio controls><source src="' . htmlspecialchars($message['media_url']) . '" type="audio/wav">Your browser does not support the audio element.</audio>';
                                            }
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="chat-input">
                                <form id="chat-form">
                                    <?php if ($conversation_id): ?>
                                        <input type="hidden" id="conversation-id" value="<?php echo $conversation_id; ?>">
                                    <?php endif; ?>
                                    <input type="hidden" id="current-subject" value="General">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="chat-input" placeholder="Ask me anything..." required>
                                        <button type="button" class="btn btn-outline-secondary" id="chat-image-upload">
                                            <i class="bi bi-image"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" id="chat-voice-record">
                                            <i class="bi bi-mic-fill"></i>
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send-fill"></i>
                                        </button>
                                    </div>
                                    <input type="file" id="image-upload-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                                    <div class="form-text mt-2">
                                        <div class="d-flex align-items-center">
                                            <select class="form-select form-select-sm me-2" id="subject-select">
                                                <option value="General">General</option>
                                                <option value="Mathematics">Mathematics</option>
                                                <option value="Science">Science</option>
                                                <option value="English">English</option>
                                                <option value="Social Studies">Social Studies</option>
                                                <option value="ICT">ICT</option>
                                                <option value="BECE">BECE</option>
                                                <option value="WASSCE">WASSCE</option>
                                            </select>
                                            <small class="text-muted">Select subject for better answers</small>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tips Section -->
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Tips for Better Results</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-lightbulb text-warning fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Be Specific</h6>
                                        <p class="small text-muted mb-0">Include subject, topic, and specific question details for more accurate answers.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-camera text-primary fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Upload Images</h6>
                                        <p class="small text-muted mb-0">Take a photo of your homework problem for step-by-step solutions.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-arrow-repeat text-success fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6>Follow Up</h6>
                                        <p class="small text-muted mb-0">Ask follow-up questions if you need more clarification on any explanation.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 Academic Ease. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted me-3">Terms of Service</a>
                    <a href="support.php" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Check if subscription is about to expire
            <?php if ($is_trial && ($hours_remaining < 3)): ?>
            const trialWarning = document.createElement('div');
            trialWarning.classList.add('alert', 'alert-warning', 'mt-3');
            trialWarning.innerHTML = '<strong>Warning:</strong> Your free trial will expire soon. <a href="subscribe-direct.php" class="alert-link">Upgrade now</a> to avoid interruption.';
            document.querySelector('.chat-container').prepend(trialWarning);
            <?php endif; ?>
            const chatForm = document.getElementById('chat-form');
            const chatInput = document.getElementById('chat-input');
            const chatMessages = document.getElementById('chat-messages');
            const chatImageUpload = document.getElementById('chat-image-upload');
            const chatImageUploadBtn = document.getElementById('chat-image-upload-btn');
            const chatVoiceRecord = document.getElementById('chat-voice-record');
            const subjectDropdownItems = document.querySelectorAll('[data-subject]');
            const subjectDropdownButton = document.getElementById('subjectDropdown');
            const currentSubjectInput = document.getElementById('current-subject');
            
            // Scroll to bottom of chat
            function scrollToBottom() {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Scroll to bottom on load
            scrollToBottom();
            
            // Handle form submission for text questions
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const message = chatInput.value.trim();
                if (!message) return;
                
                // Add user message to chat
                const userMessageElement = document.createElement('div');
                userMessageElement.classList.add('message', 'message-user');
                userMessageElement.textContent = message;
                
                chatMessages.appendChild(userMessageElement);
                
                // Clear input
                chatInput.value = '';
                
                // Add typing indicator
                const typingIndicator = document.createElement('div');
                typingIndicator.id = 'typing-indicator';
                typingIndicator.classList.add('message', 'message-ai', 'typing');
                typingIndicator.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
                chatMessages.appendChild(typingIndicator);
                
                // Scroll to bottom
                scrollToBottom();
                
                // Get current conversation ID (might have been updated during the session)
                const currentConversationId = document.getElementById('conversation-id')?.value || conversationId;
                const currentSubject = subjectSelect.value;
                
                // Send message to API
                fetch('../api/assistant/question.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        question: message,
                        conversation_id: currentConversationId || null,
                        subject: currentSubject
                    })
                })
                .then(response => response.json())
                .then(data => {
                    // Remove typing indicator
                    document.getElementById('typing-indicator')?.remove();
                    
                    if (data.error) {
                        // Check if subscription has expired
                        if (data.subscription_expired) {
                            // Show subscription expired message with link to renew
                            const errorElement = document.createElement('div');
                            errorElement.classList.add('message', 'message-ai', 'error');
                            errorElement.innerHTML = `${data.message} <a href="subscribe-direct.php?expired=1" class="btn btn-primary btn-sm mt-2">Renew Subscription</a>`;
                            chatMessages.appendChild(errorElement);
                            
                            // Disable chat input
                            chatInput.disabled = true;
                            chatInput.placeholder = 'Your subscription has expired. Please renew to continue.';
                            document.querySelector('button[type="submit"]').disabled = true;
                            chatImageUpload.disabled = true;
                            chatVoiceRecord.disabled = true;
                            
                            // Show subscription expired alert at the top
                            const expiredAlert = document.createElement('div');
                            expiredAlert.classList.add('alert', 'alert-danger', 'mt-3');
                            expiredAlert.innerHTML = '<strong>Subscription Expired:</strong> Your subscription has expired. Please renew to continue using the AI Assistant.';
                            document.querySelector('.chat-container').prepend(expiredAlert);
                        } else {
                            // Show regular error message
                            const errorElement = document.createElement('div');
                            errorElement.classList.add('message', 'message-ai', 'error');
                            errorElement.textContent = data.message || 'Sorry, I encountered an error. Please try again.';
                            chatMessages.appendChild(errorElement);
                        }
                    } else {
                        // Add AI response to chat
                        const aiMessageElement = document.createElement('div');
                        aiMessageElement.classList.add('message', 'message-ai');
                        aiMessageElement.innerHTML = data.response.replace(/\n/g, '<br>');
                        chatMessages.appendChild(aiMessageElement);
                        
                        // Update conversation ID if provided
                        if (data.conversation_id && !document.getElementById('conversation-id')) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.id = 'conversation-id';
                            input.value = data.conversation_id;
                            chatForm.appendChild(input);
                        }
                    }
                    
                    // Scroll to bottom
                    scrollToBottom();
                    
                    console.error('API error:', error);
                });
            });
            
            // Handle image upload button click
            chatImageUpload.addEventListener('click', function() {
                imageUploadInput.click();
            });
            
            // Handle image file selection
            imageUploadInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    
                    // Create image preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Add user message (image) to chat
                        const userMessageElement = document.createElement('div');
                        userMessageElement.classList.add('message', 'message-user');
                        userMessageElement.innerHTML = `<img src="${e.target.result}" alt="Uploaded image" class="img-fluid rounded">`;
                        chatMessages.appendChild(userMessageElement);
                        
                        // Scroll to bottom
                        scrollToBottom();
                        
                        // Show typing indicator
                        const typingIndicator = document.createElement('div');
                        typingIndicator.classList.add('message', 'message-ai', 'typing-indicator');
                        typingIndicator.innerHTML = '<span></span><span></span><span></span>';
                        typingIndicator.id = 'typing-indicator';
                        chatMessages.appendChild(typingIndicator);
                        scrollToBottom();
                        
                        // Prepare form data
                        const formData = new FormData();
                        formData.append('image', file);
                        formData.append('subject', currentSubjectInput.value);
                        
                        // Add conversation ID if it exists
                        const conversationId = document.getElementById('conversation-id');
                        if (conversationId) {
                            formData.append('conversation_id', conversationId.value);
                        }
                        
                        // Send AJAX request
                        fetch('/api/assistant/image.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Remove typing indicator
                            document.getElementById('typing-indicator')?.remove();
                            
                            if (data.error) {
                                // Show error message
                                const errorElement = document.createElement('div');
                                errorElement.classList.add('message', 'message-ai', 'error');
                                errorElement.textContent = 'Sorry, I encountered an error processing your image. Please try again.';
                                chatMessages.appendChild(errorElement);
                            } else {
                                // Add AI response to chat
                                const aiMessageElement = document.createElement('div');
                                aiMessageElement.classList.add('message', 'message-ai');
                                aiMessageElement.innerHTML = data.response.replace(/\n/g, '<br>');
                                chatMessages.appendChild(aiMessageElement);
                                
                                // Update conversation ID if provided
                                if (data.conversation_id && !conversationId) {
                                    const input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.id = 'conversation-id';
                                    input.value = data.conversation_id;
                                    chatForm.appendChild(input);
                                    
                                    // Update URL without reloading
                                    history.pushState(null, '', 'ai-assistant.php?conversation=' + data.conversation_id);
                                }
                            }
                            
                            // Scroll to bottom
                            scrollToBottom();
                        })
                        .catch(error => {
                            // Remove typing indicator
                            document.getElementById('typing-indicator')?.remove();
                            
                            // Show error message
                            const errorElement = document.createElement('div');
                            errorElement.classList.add('message', 'message-ai', 'error');
                            errorElement.textContent = 'Sorry, I encountered an error processing your image. Please try again.';
                            chatMessages.appendChild(errorElement);
                            
                            // Scroll to bottom
                            scrollToBottom();
                            
                            console.error('Image upload error:', error);
                        });
                    };
                    reader.readAsDataURL(file);
                }
            });
            
            // Handle voice recording
            let mediaRecorder;
            let audioChunks = [];
            let isRecording = false;
            
            chatVoiceRecord.addEventListener('click', function() {
                if (!isRecording) {
                    // Start recording
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(stream => {
                            mediaRecorder = new MediaRecorder(stream);
                            audioChunks = [];
                            
                            mediaRecorder.addEventListener('dataavailable', event => {
                                audioChunks.push(event.data);
                            });
                            
                            mediaRecorder.addEventListener('stop', () => {
                                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                                const audioUrl = URL.createObjectURL(audioBlob);
                                
                                // Add user message (audio) to chat
                                const userMessageElement = document.createElement('div');
                                userMessageElement.classList.add('message', 'message-user');
                                userMessageElement.innerHTML = `
                                    <audio controls>
                                        <source src="${audioUrl}" type="audio/wav">
                                        Your browser does not support the audio element.
                                    </audio>
                                `;
                                chatMessages.appendChild(userMessageElement);
                                
                                // Scroll to bottom
                                scrollToBottom();
                                
                                // Show typing indicator
                                const typingIndicator = document.createElement('div');
                                typingIndicator.classList.add('message', 'message-ai', 'typing-indicator');
                                typingIndicator.innerHTML = '<span></span><span></span><span></span>';
                                typingIndicator.id = 'typing-indicator';
                                chatMessages.appendChild(typingIndicator);
                                scrollToBottom();
                                
                                // Prepare form data
                                const formData = new FormData();
                                formData.append('audio', audioBlob, 'recording.wav');
                                formData.append('subject', currentSubjectInput.value);
                                
                                // Add conversation ID if it exists
                                const conversationId = document.getElementById('conversation-id');
                                if (conversationId) {
                                    formData.append('conversation_id', conversationId.value);
                                }
                                
                                // Send AJAX request
                                fetch('/api/assistant/voice.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    // Remove typing indicator
                                    document.getElementById('typing-indicator')?.remove();
                                    
                                    if (data.error) {
                                        // Show error message
                                        const errorElement = document.createElement('div');
                                        errorElement.classList.add('message', 'message-ai', 'error');
                                        errorElement.textContent = 'Sorry, I encountered an error processing your voice message. Please try again.';
                                        chatMessages.appendChild(errorElement);
                                    } else {
                                        // Add AI response to chat
                                        const aiMessageElement = document.createElement('div');
                                        aiMessageElement.classList.add('message', 'message-ai');
                                        aiMessageElement.innerHTML = data.response.replace(/\n/g, '<br>');
                                        chatMessages.appendChild(aiMessageElement);
                                        
                                        // Update conversation ID if provided
                                        if (data.conversation_id && !conversationId) {
                                            const input = document.createElement('input');
                                            input.type = 'hidden';
                                            input.id = 'conversation-id';
                                            input.value = data.conversation_id;
                                            chatForm.appendChild(input);
                                            
                                            // Update URL without reloading
                                            history.pushState(null, '', 'ai-assistant.php?conversation=' + data.conversation_id);
                                        }
                                    }
                                    
                                    // Scroll to bottom
                                    scrollToBottom();
                                })
                                .catch(error => {
                                    // Remove typing indicator
                                    document.getElementById('typing-indicator')?.remove();
                                    
                                    // Show error message
                                    const errorElement = document.createElement('div');
                                    errorElement.classList.add('message', 'message-ai', 'error');
                                    errorElement.textContent = 'Sorry, I encountered an error processing your voice message. Please try again.';
                                    chatMessages.appendChild(errorElement);
                                    
                                    // Scroll to bottom
                                    scrollToBottom();
                                    
                                    console.error('Voice upload error:', error);
                                });
                            });
                            
                            mediaRecorder.start();
                            isRecording = true;
                            chatVoiceRecord.classList.add('btn-danger');
                            chatVoiceRecord.innerHTML = '<i class="bi bi-stop-fill"></i>';
                        })
                        .catch(error => {
                            console.error('Error accessing microphone:', error);
                            alert('Could not access microphone. Please check your browser permissions.');
                        });
                } else {
                    // Stop recording
                    mediaRecorder.stop();
                    isRecording = false;
                    chatVoiceRecord.classList.remove('btn-danger');
                    chatVoiceRecord.innerHTML = '<i class="bi bi-mic-fill"></i>';
                }
            });
        });
    </script>
</body>
</html>
