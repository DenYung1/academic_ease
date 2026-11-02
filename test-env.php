<?php
// Include configuration
require_once 'includes/config.php';

// Test if environment variables are loaded
echo "Testing environment variables:\n";
echo "----------------------------\n";
echo "APP_NAME: " . APP_NAME . "\n";
echo "APP_VERSION: " . APP_VERSION . "\n";
echo "DB_HOST: " . DB_HOST . "\n";

// Test if sensitive variables are loaded (showing only if they exist, not their values)
echo "\nSensitive variables:\n";
echo "----------------------------\n";
echo "SUPABASE_URL: " . (SUPABASE_URL ? "Set ✓" : "Not set ✗") . "\n";
echo "SUPABASE_KEY: " . (SUPABASE_KEY ? "Set ✓" : "Not set ✗") . "\n";
echo "GEMINI_API_KEY: " . (GEMINI_API_KEY ? "Set ✓" : "Not set ✗") . "\n";
echo "WHATSAPP_ACCESS_TOKEN: " . (WHATSAPP_ACCESS_TOKEN ? "Set ✓" : "Not set ✗") . "\n";
echo "PAYSTACK_SECRET_KEY: " . (PAYSTACK_SECRET_KEY ? "Set ✓" : "Not set ✗") . "\n";
echo "JWT_SECRET: " . (JWT_SECRET ? "Set ✓" : "Not set ✗") . "\n";

echo "\nTest completed successfully!";
?>
