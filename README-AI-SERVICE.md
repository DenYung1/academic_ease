# Academic Ease AI Service

This document explains how to use the new AI Service system in Academic Ease, which provides robust fallback mechanisms when the primary AI provider (Gemini) is unavailable or has exceeded its quota.

## Configuration

To configure the AI Service, add the following variables to your `.env` file:

```
# Gemini AI API configuration (primary provider)
GEMINI_API_KEY="your_gemini_api_key"

# OpenAI API configuration (optional fallback)
OPENAI_API_KEY="your_openai_api_key"

# HuggingFace API configuration (optional fallback)
HUGGINGFACE_API_KEY="your_huggingface_api_key"
```

## How It Works

The AI Service automatically tries each configured provider in order until it finds one that works. The default order is:

1. Gemini (Google's AI)
2. OpenAI (if configured)
3. HuggingFace (if configured)
4. Local fallback (always available)

If all online providers fail, the system will use a local fallback that provides general educational guidance.

## Usage Examples

### Basic Text Generation

```php
// Include the AI service
require_once 'includes/ai_service.php';

// Create an instance
$aiService = new AIService();

// Generate text
try {
    $response = $aiService->generateText("Explain photosynthesis to a JHS student");
    echo "Response from " . $aiService->getCurrentProvider() . ": " . $response;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Image Analysis

```php
// Include the AI service
require_once 'includes/ai_service.php';

// Create an instance
$aiService = new AIService();

// Analyze an image
try {
    $imageData = base64_encode(file_get_contents('path/to/image.jpg'));
    $mimeType = 'image/jpeg';
    $prompt = "Explain what's in this image in educational terms";
    
    $response = $aiService->analyzeImage($prompt, $imageData, $mimeType);
    echo "Response from " . $aiService->getCurrentProvider() . ": " . $response;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Custom Fallback Order

You can customize the order in which providers are tried:

```php
$aiService = new AIService();
$aiService->setFallbackOrder([
    AIService::PROVIDER_OPENAI,
    AIService::PROVIDER_GEMINI,
    AIService::PROVIDER_HUGGINGFACE,
    AIService::PROVIDER_LOCAL
]);
```

## Troubleshooting

### Gemini API Quota Exceeded

If you see an error like "You exceeded your current quota, please check your plan and billing details", you need to:

1. Go to the [Google Cloud Console](https://console.cloud.google.com/billing)
2. Link your project to a billing account
3. You may need to upgrade from the free tier
4. Once billing is set up, try again

Alternatively, you can configure one of the fallback providers (OpenAI or HuggingFace).

### Testing the AI Service

Use the included test script to verify your configuration:

```bash
php test-ai-service.php
```

This will test text generation and fallback mechanisms to ensure everything is working properly.
