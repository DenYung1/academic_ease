<?php
/**
 * OpenRouterClient - A PHP client for interacting with OpenRouter.ai API
 * 
 * This class provides methods to interact with various AI models through OpenRouter.ai
 */
class OpenRouterClient {
    private $apiKey;
    private $baseUrl = 'https://openrouter.ai/api/v1';
    private $defaultModel = 'anthropic/claude-haiku-4.5';
    private $fallbackModels = [
        'meta-llama/llama-3.1-8b-instruct',
        'google/gemini-2.0-flash-001'
    ];
    private $debug = false;
    
    /**
     * Constructor
     * 
     * @param string $apiKey OpenRouter API key
     */
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }
    
    /**
     * Generate a completion using the OpenRouter API
     * 
     * @param string $prompt The prompt to generate a completion for
     * @param array $options Additional options for the API request
     * @return string The generated completion
     */
    public function generateCompletion($prompt, $options = []) {
        $model = $options['model'] ?? $this->defaultModel;
        $task = $options['task'] ?? 'general';
        $hasImages = isset($options['image_url']) && !empty($options['image_url']);
        $inputLength = strlen($prompt);
        
        // Select appropriate model if not explicitly specified
        if (!isset($options['model'])) {
            $model = $this->selectModelForTask($task, $inputLength, $hasImages);
        }
        
        // Prepare payload
        $payload = [
            'model' => $model,
            'prompt' => $this->optimizePrompt($prompt, $task),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1024
        ];
        
        // Add image if provided
        if ($hasImages) {
            $payload['image_url'] = $options['image_url'];
        }
        
        // Make request with fallback
        $response = $this->handleRequestWithFallback('/completions', $payload);
        
        // Extract and return the completion text
        if (isset($response['choices']) && !empty($response['choices'])) {
            return $response['choices'][0]['text'];
        }
        
        throw new Exception("Failed to generate completion");
    }
    
    /**
     * Generate a chat completion using the OpenRouter API
     * 
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Additional options for the API request
     * @return string The generated response
     */
    public function generateChatCompletion($messages, $options = []) {
        if ($this->debug) {
            error_log('OpenRouterClient: generateChatCompletion called with options: ' . json_encode($options));
        }
        
        $model = $options['model'] ?? $this->defaultModel;
        $task = $options['task'] ?? 'general';
        $hasImages = false;
        $inputLength = 0;
        
        // Check for images and calculate input length
        foreach ($messages as $message) {
            if (isset($message['content'])) {
                if (is_string($message['content'])) {
                    $inputLength += strlen($message['content']);
                } elseif (is_array($message['content'])) {
                    foreach ($message['content'] as $part) {
                        if (isset($part['type']) && $part['type'] === 'image_url') {
                            $hasImages = true;
                        } elseif (isset($part['type']) && $part['type'] === 'text') {
                            $inputLength += strlen($part['text']);
                        }
                    }
                }
            }
        }
        
        // Select appropriate model if not explicitly specified
        if (!isset($options['model']) || $options['model'] === 'auto') {
            $model = $this->selectModelForTask($task, $inputLength, $hasImages);
            if ($this->debug) {
                error_log('OpenRouterClient: Auto-selected model: ' . $model);
            }
        }
        
        // Prepare payload
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 1024
        ];
        
        if ($this->debug) {
            error_log('OpenRouterClient: Sending request with payload: ' . json_encode($payload));
        }
        
        try {
            error_log('OpenRouterClient: About to call handleRequestWithFallback');
            $response = $this->handleRequestWithFallback('/chat/completions', $payload);
            error_log('OpenRouterClient: Successfully received response from handleRequestWithFallback');
            
            if ($this->debug) {
                error_log('OpenRouterClient: Received response: ' . json_encode($response));
            }
        } catch (Exception $e) {
            error_log('OpenRouterClient: Error in handleRequestWithFallback: ' . $e->getMessage());
            // Return a fallback response instead of re-throwing the exception
            return "I'm sorry, but I encountered an error while trying to process your request. The error was: " . $e->getMessage();
        }
        
        // Extract and return the response text
        if (isset($response['choices']) && !empty($response['choices'])) {
            if (isset($response['choices'][0]['message']['content'])) {
                return $response['choices'][0]['message']['content'];
            } elseif (isset($response['choices'][0]['text'])) {
                // Some models return text directly
                return $response['choices'][0]['text'];
            } else {
                error_log('OpenRouterClient: Unexpected response format: ' . json_encode($response));
                // Return a fallback message instead of throwing an exception
                return "I'm sorry, but I couldn't generate a proper response at this time. Please try again or try a different question.";
            }
        }
        
        error_log('OpenRouterClient: Failed to generate chat completion. Response: ' . json_encode($response));
        // Return a fallback message instead of throwing an exception
        return "I apologize, but I'm having trouble processing your request. Please try again later.";
    }
    
    /**
     * Select the appropriate model based on task type, input length, and presence of images
     * 
     * @param string $task The type of task
     * @param int $inputLength The length of the input text
     * @param bool $hasImages Whether the input contains images
     * @return string The selected model ID
     */
    private function selectModelForTask($task, $inputLength, $hasImages) {
        // For image inputs, use a vision-capable model
        if ($hasImages) {
            return 'google/gemini-2.0-flash-001'; // Good for visual content
        }
        
        // For long inputs, use a model with high context window
        if ($inputLength > 10000) {
            return 'anthropic/claude-sonnet-4'; // Best for long contexts
        }
        
        // Select based on task type
        switch ($task) {
            case 'math':
            case 'science':
            case 'programming':
                return 'google/gemini-2.0-flash-001'; // Good for technical tasks
            case 'creative':
            case 'writing':
                return 'anthropic/claude-sonnet-4'; // Good for creative tasks
            case 'education':
            case 'tutoring':
                return 'meta-llama/llama-3.1-8b-instruct'; // Good for educational content
            default:
                return $this->defaultModel;
        }
    }
    
    /**
     * Optimize the prompt for better results
     * 
     * @param string $prompt The original prompt
     * @param string $task The type of task
     * @return string The optimized prompt
     */
    public function optimizePrompt($prompt, $task) {
        // Add task-specific instructions
        $taskPrefixes = [
            'math_problem' => "Solve this math problem step by step:\n\n",
            'essay_writing' => "Write a well-structured essay on the following topic:\n\n",
            'science_question' => "Explain this scientific concept in detail:\n\n",
            'programming' => "Help with the following programming task:\n\n",
            'general' => "You are an AI assistant for Academic Ease, an educational platform for students in Ghana. Answer the following question helpfully and accurately:\n\n"
        ];
        
        if (isset($taskPrefixes[$task])) {
            return $taskPrefixes[$task] . $prompt;
        }
        
        return $prompt;
    }
    
    /**
     * Handle API request with fallback to other models if needed
     * 
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @return array API response
     */
    private function handleRequestWithFallback($endpoint, $payload) {
        // Try with the specified model first
        $model = $payload['model'];
        $errors = [];
        
        error_log("OpenRouter: Attempting request with model: $model");
        
        try {
            // Clone payload to avoid modifying the original
            $currentPayload = $payload;
            
            // Make the request
            $response = $this->makeRequest($endpoint, $currentPayload);
            
            // If successful, return the response
            if (isset($response['choices']) && !empty($response['choices'])) {
                // Log successful model use
                $this->logModelPerformance($model, $payload['task'] ?? 'general', 0, true);
                error_log("OpenRouter: Successfully used model: $model");
                return $response;
            } else {
                error_log("OpenRouter: Model $model returned empty choices array");
                $errors[] = "Model $model returned empty choices array";
            }
        } catch (Exception $e) {
            // Log error and continue to fallback models
            $errorMsg = "Error with primary model $model: " . $e->getMessage();
            error_log("OpenRouter: $errorMsg");
            $errors[] = $errorMsg;
            $this->logModelPerformance($model, $payload['task'] ?? 'general', 0, false);
        }
        
        // Try fallback models if primary fails
        foreach ($this->fallbackModels as $fallbackModel) {
            try {
                // Skip if it's the same as the failed model
                if ($fallbackModel === $model) continue;
                
                error_log("OpenRouter: Trying fallback model: $fallbackModel");
                
                // Clone payload and update model
                $fallbackPayload = $payload;
                $fallbackPayload['model'] = $fallbackModel;
                
                // Make the request with fallback model
                $response = $this->makeRequest($endpoint, $fallbackPayload);
                
                // If successful, return the response
                if (isset($response['choices']) && !empty($response['choices'])) {
                    // Log successful model use
                    $this->logModelPerformance($fallbackModel, $payload['task'] ?? 'general', 0, true);
                    error_log("OpenRouter: Successfully used fallback model: $fallbackModel");
                    return $response;
                } else {
                    error_log("OpenRouter: Fallback model $fallbackModel returned empty choices array");
                    $errors[] = "Fallback model $fallbackModel returned empty choices array";
                }
            } catch (Exception $e) {
                // Log error and continue to next model
                $errorMsg = "Error with fallback model $fallbackModel: " . $e->getMessage();
                error_log("OpenRouter: $errorMsg");
                $errors[] = $errorMsg;
                $this->logModelPerformance($fallbackModel, $payload['task'] ?? 'general', 0, false);
                continue;
            }
        }
        
        $errorDetails = implode(" | ", $errors);
        error_log("OpenRouter: All model attempts failed: $errorDetails");
        throw new Exception("All model attempts failed: $errorDetails");
    }
    
    /**
     * Make an API request to OpenRouter
     * 
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @return array API response
     */
    private function makeRequest($endpoint, $payload) {
        $url = $this->baseUrl . $endpoint;
        
        // Log the API request for debugging
        error_log('OpenRouter Request to: ' . $url);
        error_log('OpenRouter request started for model: ' . ($payload['model'] ?? 'unknown'));
        
        // Check if API key is set
        if (empty($this->apiKey) || $this->apiKey === 'your_openrouter_api_key_here') {
            error_log('OpenRouter Error: API key is not set or invalid');
            // Return a mock response instead of throwing an exception
            return [
                'choices' => [
                    [
                        'message' => [
                            'content' => "I'm sorry, but the OpenRouter API key is not configured. Please add your API key to the .env file. You can get an API key from https://openrouter.ai/"
                        ]
                    ]
                ]
            ];
        }
        
        // Set default values if not provided
        if (!isset($payload['temperature'])) {
            $payload['temperature'] = 0.7;
        }
        
        if (!isset($payload['max_tokens'])) {
            $payload['max_tokens'] = 1024;
        }
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'HTTP-Referer: https://academic-ease.com', // Your domain
            'X-Title: Academic Ease AI Assistant'
        ];
        
        // Initialize cURL session
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increase timeout to 60 seconds
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Execute cURL request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Log detailed request information
        error_log('OpenRouter Request Details:');
        error_log('URL: ' . $url);
        error_log('Headers: ' . json_encode($headers));
        
        // Handle cURL errors
        if (curl_errno($ch)) {
            $error = 'cURL Error: ' . curl_error($ch) . ' (Code: ' . curl_errno($ch) . ')';
            error_log('OpenRouter ' . $error);
            curl_close($ch);
            throw new Exception($error);
        }
        
        curl_close($ch);
        
        // Log the API response for debugging
        error_log('OpenRouter Response Code: ' . $httpCode);
        error_log('OpenRouter Response: ' . substr($response, 0, 500) . (strlen($response) > 500 ? '...' : ''));
        
        // Handle HTTP errors
        if ($httpCode >= 400) {
            $errorData = json_decode($response, true);
            $errorMessage = isset($errorData['error']['message']) ? $errorData['error']['message'] : $response;
            error_log('OpenRouter API Error: ' . $errorMessage . ' (Code: ' . $httpCode . ')');
            
            // Check for specific error types
            if (strpos($errorMessage, 'not a valid model ID') !== false) {
                // Model ID error - try with a fallback model
                error_log('Invalid model ID detected, falling back to default model');
                return $this->handleRequestWithFallback($endpoint, array_merge($payload, ['model' => $this->defaultModel]));
            } else if ($httpCode == 429) {
                // Rate limit error
                throw new Exception('OpenRouter API rate limit exceeded. Please try again in a few moments.');
            } else {
                throw new Exception('OpenRouter API error: ' . $errorMessage);
            }
        }
        
        // Parse JSON response
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('OpenRouter JSON Parse Error: ' . json_last_error_msg());
            throw new Exception('Failed to parse API response: ' . json_last_error_msg());
        }
        
        return $decodedResponse;
    }
    
    /**
     * Log model performance for future optimization
     * 
     * @param string $model Model ID
     * @param string $task Task type
     * @param int $responseTime Response time in milliseconds
     * @param bool $success Whether the request was successful
     */
    private function logModelPerformance($model, $task, $responseTime, $success) {
        // This would typically store data in a database
        // For now, we'll just log it
        error_log("Model performance: $model, Task: $task, Success: " . ($success ? 'Yes' : 'No'));
    }
}
