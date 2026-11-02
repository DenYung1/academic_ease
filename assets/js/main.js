/**
 * Academic Ease - Main JavaScript
 * Handles client-side functionality for the AI Homework/Study Assistant platform
 */

// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    initTooltips();
    
    // Initialize AI chat functionality if on the chat page
    if (document.querySelector('.chat-container')) {
        initChat();
    }
    
    // Initialize file upload preview for homework images
    if (document.querySelector('#homework-image-upload')) {
        initImageUpload();
    }
    
    // Initialize subscription form validation
    if (document.querySelector('#subscription-form')) {
        initSubscriptionForm();
    }
    
    // Initialize progress charts if on the dashboard
    if (document.querySelector('#progress-chart')) {
        initProgressCharts();
    }
});

/**
 * Initialize Bootstrap tooltips
 */
function initTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize AI chat functionality
 */
function initChat() {
    const chatForm = document.querySelector('#chat-form');
    const chatInput = document.querySelector('#chat-input');
    const chatMessages = document.querySelector('.chat-messages');
    const chatImageUpload = document.querySelector('#chat-image-upload');
    const chatVoiceRecord = document.querySelector('#chat-voice-record');
    
    // Handle chat form submission
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const message = chatInput.value.trim();
        if (message === '') return;
        
        // Add user message to chat
        addMessage('user', message);
        
        // Clear input
        chatInput.value = '';
        
        // Show typing indicator
        showTypingIndicator();
        
        // Send message to AI (AJAX request)
        sendMessageToAI(message);
    });
    
    // Handle image upload
    if (chatImageUpload) {
        chatImageUpload.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Show loading state
                showTypingIndicator();
                
                // Upload image to server
                uploadImage(file);
            }
        });
    }
    
    // Handle voice recording
    if (chatVoiceRecord) {
        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;
        
        chatVoiceRecord.addEventListener('click', function() {
            if (!isRecording) {
                // Start recording
                navigator.mediaDevices.getUserMedia({ audio: true })
                    .then(stream => {
                        mediaRecorder = new MediaRecorder(stream);
                        mediaRecorder.start();
                        
                        mediaRecorder.addEventListener('dataavailable', event => {
                            audioChunks.push(event.data);
                        });
                        
                        mediaRecorder.addEventListener('stop', () => {
                            const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                            audioChunks = [];
                            
                            // Show loading state
                            showTypingIndicator();
                            
                            // Upload audio to server
                            uploadAudio(audioBlob);
                        });
                        
                        isRecording = true;
                        chatVoiceRecord.classList.add('recording');
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
                chatVoiceRecord.classList.remove('recording');
                chatVoiceRecord.innerHTML = '<i class="bi bi-mic-fill"></i>';
            }
        });
    }
}

/**
 * Add a message to the chat
 * 
 * @param {string} sender - 'user' or 'ai'
 * @param {string} content - Message content
 * @param {string} type - 'text', 'image', or 'voice'
 * @param {string} mediaUrl - URL for image or voice message
 */
function addMessage(sender, content, type = 'text', mediaUrl = null) {
    const chatMessages = document.querySelector('.chat-messages');
    const messageElement = document.createElement('div');
    messageElement.classList.add('message', `message-${sender}`, 'animate-fade-in');
    
    let messageContent = '';
    
    if (type === 'text') {
        // Format text message with markdown-like syntax
        messageContent = formatMessage(content);
    } else if (type === 'image') {
        messageContent = `<img src="${mediaUrl}" alt="Shared image" class="img-fluid rounded"/>`;
    } else if (type === 'voice') {
        messageContent = `
            <audio controls>
                <source src="${mediaUrl}" type="audio/wav">
                Your browser does not support the audio element.
            </audio>
        `;
    }
    
    messageElement.innerHTML = messageContent;
    chatMessages.appendChild(messageElement);
    
    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

/**
 * Format message text with markdown-like syntax
 * 
 * @param {string} text - Message text
 * @return {string} Formatted HTML
 */
function formatMessage(text) {
    // Replace URLs with clickable links
    text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
    
    // Replace **bold** with <strong>
    text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Replace *italic* with <em>
    text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Replace `code` with <code>
    text = text.replace(/`(.*?)`/g, '<code>$1</code>');
    
    // Replace line breaks with <br>
    text = text.replace(/\n/g, '<br>');
    
    return text;
}

/**
 * Show typing indicator in chat
 */
function showTypingIndicator() {
    const chatMessages = document.querySelector('.chat-messages');
    const typingIndicator = document.createElement('div');
    typingIndicator.classList.add('message', 'message-ai', 'typing-indicator');
    typingIndicator.innerHTML = '<span></span><span></span><span></span>';
    typingIndicator.id = 'typing-indicator';
    chatMessages.appendChild(typingIndicator);
    
    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

/**
 * Hide typing indicator in chat
 */
function hideTypingIndicator() {
    const typingIndicator = document.querySelector('#typing-indicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
}

/**
 * Send message to AI via AJAX
 * 
 * @param {string} message - User message
 */
function sendMessageToAI(message) {
    // Create form data
    const formData = new FormData();
    formData.append('message', message);
    
    // Get conversation ID if it exists
    const conversationId = document.querySelector('#conversation-id')?.value;
    if (conversationId) {
        formData.append('conversation_id', conversationId);
    }
    
    // Send AJAX request
    fetch('/api/assistant/question.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide typing indicator
        hideTypingIndicator();
        
        if (data.error) {
            // Show error message
            addMessage('ai', 'Sorry, I encountered an error. Please try again later.');
            console.error('AI response error:', data.error);
        } else {
            // Add AI response to chat
            addMessage('ai', data.response);
            
            // Update conversation ID if provided
            if (data.conversation_id) {
                if (document.querySelector('#conversation-id')) {
                    document.querySelector('#conversation-id').value = data.conversation_id;
                } else {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'conversation-id';
                    input.value = data.conversation_id;
                    document.querySelector('#chat-form').appendChild(input);
                }
            }
        }
    })
    .catch(error => {
        // Hide typing indicator
        hideTypingIndicator();
        
        // Show error message
        addMessage('ai', 'Sorry, I encountered an error. Please try again later.');
        console.error('AI request error:', error);
    });
}

/**
 * Upload image to server
 * 
 * @param {File} file - Image file
 */
function uploadImage(file) {
    // Create form data
    const formData = new FormData();
    formData.append('image', file);
    
    // Get conversation ID if it exists
    const conversationId = document.querySelector('#conversation-id')?.value;
    if (conversationId) {
        formData.append('conversation_id', conversationId);
    }
    
    // Add user message to chat (image preview)
    const reader = new FileReader();
    reader.onload = function(e) {
        addMessage('user', null, 'image', e.target.result);
    };
    reader.readAsDataURL(file);
    
    // Send AJAX request
    fetch('/api/assistant/image.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide typing indicator
        hideTypingIndicator();
        
        if (data.error) {
            // Show error message
            addMessage('ai', 'Sorry, I encountered an error processing your image. Please try again.');
            console.error('Image upload error:', data.error);
        } else {
            // Add AI response to chat
            addMessage('ai', data.response);
            
            // Update conversation ID if provided
            if (data.conversation_id) {
                if (document.querySelector('#conversation-id')) {
                    document.querySelector('#conversation-id').value = data.conversation_id;
                } else {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'conversation-id';
                    input.value = data.conversation_id;
                    document.querySelector('#chat-form').appendChild(input);
                }
            }
        }
    })
    .catch(error => {
        // Hide typing indicator
        hideTypingIndicator();
        
        // Show error message
        addMessage('ai', 'Sorry, I encountered an error processing your image. Please try again.');
        console.error('Image upload error:', error);
    });
}

/**
 * Upload audio to server
 * 
 * @param {Blob} audioBlob - Audio blob
 */
function uploadAudio(audioBlob) {
    // Create form data
    const formData = new FormData();
    formData.append('audio', audioBlob, 'recording.wav');
    
    // Get conversation ID if it exists
    const conversationId = document.querySelector('#conversation-id')?.value;
    if (conversationId) {
        formData.append('conversation_id', conversationId);
    }
    
    // Create audio URL for preview
    const audioUrl = URL.createObjectURL(audioBlob);
    
    // Add user message to chat (audio player)
    addMessage('user', null, 'voice', audioUrl);
    
    // Send AJAX request
    fetch('/api/assistant/voice.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide typing indicator
        hideTypingIndicator();
        
        if (data.error) {
            // Show error message
            addMessage('ai', 'Sorry, I encountered an error processing your voice message. Please try again.');
            console.error('Voice upload error:', data.error);
        } else {
            // Add AI response to chat
            addMessage('ai', data.response);
            
            // Update conversation ID if provided
            if (data.conversation_id) {
                if (document.querySelector('#conversation-id')) {
                    document.querySelector('#conversation-id').value = data.conversation_id;
                } else {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.id = 'conversation-id';
                    input.value = data.conversation_id;
                    document.querySelector('#chat-form').appendChild(input);
                }
            }
        }
    })
    .catch(error => {
        // Hide typing indicator
        hideTypingIndicator();
        
        // Show error message
        addMessage('ai', 'Sorry, I encountered an error processing your voice message. Please try again.');
        console.error('Voice upload error:', error);
    });
}

/**
 * Initialize image upload preview
 */
function initImageUpload() {
    const fileInput = document.querySelector('#homework-image-upload');
    const previewContainer = document.querySelector('#image-preview-container');
    
    fileInput.addEventListener('change', function() {
        previewContainer.innerHTML = '';
        
        if (this.files && this.files.length > 0) {
            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                
                if (!file.type.match('image.*')) {
                    continue;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.classList.add('image-preview');
                    previewDiv.innerHTML = `
                        <img src="${e.target.result}" alt="Preview" class="img-fluid rounded">
                        <button type="button" class="btn-close remove-image" aria-label="Remove"></button>
                    `;
                    previewContainer.appendChild(previewDiv);
                    
                    // Add event listener to remove button
                    previewDiv.querySelector('.remove-image').addEventListener('click', function() {
                        previewDiv.remove();
                        fileInput.value = '';
                    });
                };
                
                reader.readAsDataURL(file);
            }
        }
    });
}

/**
 * Initialize subscription form validation
 */
function initSubscriptionForm() {
    const form = document.querySelector('#subscription-form');
    
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
    
    // Handle payment method selection
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const paymentDetails = document.querySelectorAll('.payment-details');
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            paymentDetails.forEach(detail => {
                detail.style.display = 'none';
            });
            
            const selectedMethod = this.value;
            document.querySelector(`#${selectedMethod}-details`).style.display = 'block';
        });
    });
}

/**
 * Initialize progress charts on dashboard
 */
function initProgressCharts() {
    // Load Chart.js if not already loaded
    if (typeof Chart === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        script.onload = createCharts;
        document.head.appendChild(script);
    } else {
        createCharts();
    }
    
    function createCharts() {
        // Subject progress chart
        const subjectProgressCtx = document.querySelector('#subject-progress-chart').getContext('2d');
        const subjectProgressChart = new Chart(subjectProgressCtx, {
            type: 'radar',
            data: {
                labels: ['Mathematics', 'Science', 'English', 'Social Studies', 'ICT'],
                datasets: [{
                    label: 'Your Progress',
                    data: [85, 70, 90, 65, 75],
                    backgroundColor: 'rgba(26, 115, 232, 0.2)',
                    borderColor: 'rgba(26, 115, 232, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(26, 115, 232, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(26, 115, 232, 1)'
                }]
            },
            options: {
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        }
                    }
                }
            }
        });
        
        // Weekly activity chart
        const weeklyActivityCtx = document.querySelector('#weekly-activity-chart').getContext('2d');
        const weeklyActivityChart = new Chart(weeklyActivityCtx, {
            type: 'bar',
            data: {
                labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                datasets: [{
                    label: 'Study Hours',
                    data: [1.5, 2, 0.5, 1, 3, 2.5, 1],
                    backgroundColor: 'rgba(52, 168, 83, 0.7)'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                }
            }
        });
    }
}

/**
 * Service Worker Registration for PWA
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Use the correct path to the service worker file
        navigator.serviceWorker.register('./service-worker.js')
            .then(function(registration) {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
            })
            .catch(function(err) {
                console.log('ServiceWorker registration failed: ', err);
                // Log more detailed error information
                if (err.name === 'SecurityError') {
                    console.log('Service worker registration failed due to security restrictions');
                } else if (err.name === 'TypeError') {
                    console.log('Service worker URL is invalid or the response is not JavaScript');
                }
            });
    });
}
