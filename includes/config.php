<?php
/**
 * Configuration settings for Academic Ease
 * Loads environment variables from .env file
 */

// Load environment variables without requiring Composer on shared hosting.
$dotenvPath = __DIR__ . '/../.env';
if (is_readable($dotenvPath)) {
    $envContent = file_get_contents($dotenvPath);
    if ($envContent !== false) {
        $lines = preg_split('/\r\n|\r|\n/', $envContent);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            if ($key !== '') {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    } else {
        error_log('Unable to read environment file: ' . $dotenvPath);
    }
} else {
    error_log('Environment file is missing or unreadable: ' . $dotenvPath);
}

// Helper function to get environment variables with fallback
function env($key, $default = null) {
    return isset($_ENV[$key]) ? $_ENV[$key] : $default;
}

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid request token.');
    }
}

// Application settings
define('APP_NAME', env('APP_NAME', 'Academic Ease'));
define('APP_VERSION', env('APP_VERSION', '1.0.0'));
define('APP_URL', env('APP_URL', 'http://localhost/academic_ease'));
define('APP_EMAIL', env('APP_EMAIL', 'info@academicease.com'));

// Database configuration
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'academic_ease'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

// Supabase configuration
define('SUPABASE_URL', env('SUPABASE_URL', ''));
define('SUPABASE_KEY', env('SUPABASE_KEY', ''));
define('SUPABASE_SERVICE_KEY', env('SUPABASE_SERVICE_KEY', ''));

// Gemini AI API configuration
define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));

// WhatsApp Business API configuration
define('WHATSAPP_PHONE_NUMBER_ID', env('WHATSAPP_PHONE_NUMBER_ID', ''));
define('WHATSAPP_ACCESS_TOKEN', env('WHATSAPP_ACCESS_TOKEN', ''));
define('WHATSAPP_VERIFY_TOKEN', env('WHATSAPP_VERIFY_TOKEN', 'academic_ease_webhook_verify_token'));

// Payment gateway configuration
define('PAYSTACK_SECRET_KEY', env('PAYSTACK_SECRET_KEY', ''));
define('PAYSTACK_PUBLIC_KEY', env('PAYSTACK_PUBLIC_KEY', ''));

// Subscription plans (in Ghana Cedis)
define('SUBSCRIPTION_WEEKLY_PRICE', (int)env('SUBSCRIPTION_WEEKLY_PRICE', 20));
define('SUBSCRIPTION_MONTHLY_PRICE', (int)env('SUBSCRIPTION_MONTHLY_PRICE', 60));
define('SUBSCRIPTION_TERMLY_PRICE', (int)env('SUBSCRIPTION_TERMLY_PRICE', 150));

// Security settings
define('HASH_COST', (int)env('HASH_COST', 10)); // For bcrypt password hashing
define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 86400)); // 24 hours in seconds
define('JWT_SECRET', env('JWT_SECRET', 'your_jwt_secret_key_here'));
define('JWT_EXPIRY', (int)env('JWT_EXPIRY', 3600)); // 1 hour in seconds

// File upload settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Time zone
date_default_timezone_set('Africa/Accra');

// Create logs directory if it doesn't exist
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
