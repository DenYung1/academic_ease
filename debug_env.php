<?php
// Load environment variables
require_once __DIR__ . '/includes/config.php';

// Check if the OpenRouter API key is loaded
echo "OPENROUTER_API_KEY from getenv(): " . (getenv('OPENROUTER_API_KEY') ? "Set (length: " . strlen(getenv('OPENROUTER_API_KEY')) . ")" : "Not set") . "\n";

// Check if it's loaded in $_ENV
echo "OPENROUTER_API_KEY from \$_ENV: " . (isset($_ENV['OPENROUTER_API_KEY']) ? "Set (length: " . strlen($_ENV['OPENROUTER_API_KEY']) . ")" : "Not set") . "\n";

// Check if it's loaded in $_SERVER
echo "OPENROUTER_API_KEY from \$_SERVER: " . (isset($_SERVER['OPENROUTER_API_KEY']) ? "Set (length: " . strlen($_SERVER['OPENROUTER_API_KEY']) . ")" : "Not set") . "\n";

// Check if it's being loaded in the AI config
require_once __DIR__ . '/includes/ai_config.php';
$ai_config = require __DIR__ . '/includes/ai_config.php';
echo "OPENROUTER_API_KEY from ai_config: " . ($ai_config['openrouter']['api_key'] !== 'your_openrouter_api_key_here' ? "Set (length: " . strlen($ai_config['openrouter']['api_key']) . ")" : "Not set correctly") . "\n";
?>
