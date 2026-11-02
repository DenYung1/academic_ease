# Setting Up OpenRouter API for Academic Ease

## 1. Get Your OpenRouter API Key

1. Visit [OpenRouter.ai](https://openrouter.ai/)
2. Sign up for a free account
3. Navigate to the API Keys section in your dashboard
4. Create a new API key
5. Copy the API key to use in the next step

## 2. Add the API Key to Your .env File

Edit your `.env` file in the root directory of Academic Ease and add the following line:

```
OPENROUTER_API_KEY=your_api_key_here
```

Replace `your_api_key_here` with the actual API key you copied from OpenRouter.

## 3. Verify the Configuration

Run the test script to verify that your OpenRouter integration is working:

```
php test_ai_services.php
```

## 4. Understanding the Free Tier

OpenRouter's free tier provides access to several AI models:

- **Google Gemini 2.5 Pro** - Best for research and long contexts
- **Meta Llama 4 Maverick** - Good for visual content and math
- **Mistral Small** - Balanced performance for general questions
- **DeepSeek models** - Specialized for math, science, and programming

The free tier has rate limits, but it's sufficient for educational use in Ghana.

## 5. Fallback Options

Academic Ease is designed with fallback mechanisms:

1. **Primary**: OpenRouter (cloud-based, requires internet)
2. **Secondary**: Ollama (local, requires installation)

This ensures AI assistance is available even if one service is unavailable.

## 6. Educational Focus

Remember that Academic Ease's AI is configured to focus on explaining concepts rather than solving homework directly. This educational approach helps students understand the material better by:

- Guiding them through concepts
- Providing similar examples rather than direct answers
- Using the Socratic method to ask guiding questions
- Leading students to discover answers themselves

This approach is implemented in both text-based question handling and image analysis components.
