# OpenRouter API Troubleshooting Guide

## Common Issues and Solutions

### "An error occurred while processing your message"

This error typically occurs when the AI assistant can't connect to the OpenRouter API. Here are the most common causes and solutions:

1. **Missing or Invalid API Key**
   - Make sure you have added your OpenRouter API key to the `.env` file
   - The key should be in this format: `OPENROUTER_API_KEY=your_actual_api_key_here`
   - Restart your web server after updating the `.env` file

2. **Network Connection Issues**
   - Check if your server can connect to external services
   - If using a local development environment, ensure your firewall isn't blocking outgoing connections

3. **Rate Limiting**
   - OpenRouter has rate limits for free accounts
   - Check your OpenRouter dashboard to see if you've hit your quota

4. **Model Availability**
   - Some models might be temporarily unavailable
   - Try selecting a different model from the dropdown

## How to Verify Your Setup

1. Check your `.env` file:
   ```
   OPENROUTER_API_KEY=your_actual_api_key_here
   ```

2. Verify the API key is being loaded correctly:
   - Look at the error logs for any "API key is not configured" messages
   - Make sure PHP can read environment variables (check `phpinfo()`)

3. Test with a simple message:
   - Try sending a very short message like "Hello"
   - This minimizes the chance of hitting token limits

## Getting a Free OpenRouter API Key

1. Visit [OpenRouter.ai](https://openrouter.ai/)
2. Sign up for a free account
3. Navigate to the API Keys section
4. Create a new API key
5. Copy the key to your `.env` file

## Need More Help?

If you're still experiencing issues after trying these solutions, check the error logs for more specific error messages that can help identify the problem.
