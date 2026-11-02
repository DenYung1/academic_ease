# Setting Up OpenRouter API Key

## Adding to Your .env File

Add the following line to your `.env` file:

```
# OpenRouter API settings
OPENROUTER_API_KEY=your_openrouter_api_key_here
```

Replace `your_openrouter_api_key_here` with the actual API key you obtained from OpenRouter.ai.

## Loading Environment Variables

The Academic Ease application is already set up to load environment variables from the `.env` file. The OpenRouter client will automatically use this environment variable when making API requests.

## Verifying the Setup

To verify that your environment variable is properly set up:

1. After adding the API key to your `.env` file, restart your web server
2. Navigate to the AI Assistant page in your application
3. Try sending a message to test the connection

## Security Best Practices

- Never commit your `.env` file to version control
- Restrict access to your `.env` file on the server
- Regularly rotate your API keys
- Set appropriate rate limits in your OpenRouter dashboard

## For Local Development

If you're developing locally and don't want to modify your `.env` file, you can also set the environment variable directly in your terminal session:

### Windows (Command Prompt)
```
set OPENROUTER_API_KEY=your_openrouter_api_key_here
```

### Windows (PowerShell)
```
$env:OPENROUTER_API_KEY="your_openrouter_api_key_here"
```

### Linux/Mac
```
export OPENROUTER_API_KEY=your_openrouter_api_key_here
```

Remember to restart your web server after setting the environment variable.
