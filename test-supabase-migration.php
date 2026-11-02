<?php
/**
 * Academic Ease - Supabase Migration Test Script
 * This script tests if your new Supabase connection is working correctly
 */

// Include configuration and database connection
require_once './includes/config.php';
require_once './includes/db_connect.php';

// Display header
echo "===================================\n";
echo "Academic Ease - Supabase Migration Test\n";
echo "===================================\n\n";

// Check Supabase credentials
if (empty(SUPABASE_URL) || empty(SUPABASE_KEY)) {
    echo "❌ ERROR: Supabase credentials are not set in your .env file.\n";
    exit(1);
}

echo "🔍 Testing Supabase connection...\n";

// Test database connection
try {
    // Try to fetch a single row from users table
    $result = supabaseSelect('users', ['limit' => 1]);
    
    if (isset($result['error'])) {
        echo "❌ Database connection error: " . $result['message'] . "\n";
    } else {
        echo "✅ Database connection successful!\n";
    }
} catch (Exception $e) {
    echo "❌ Database connection exception: " . $e->getMessage() . "\n";
}

// Test storage connection
echo "\n🔍 Testing Supabase Storage...\n";

try {
    // Create a test file
    $testFilePath = './test-upload.txt';
    file_put_contents($testFilePath, 'This is a test file for Supabase storage.');
    
    // Create a test user ID
    $testUserId = 'test-' . uniqid();
    echo "📋 Using test user ID: {$testUserId}\n";
    
    // Test 1: Upload with user ID (secure approach)
    echo "\n📤 Test 1: Upload with user ID (secure approach)\n";
    $uploadResult = supabaseUploadFile('academic-ease', 'test/test-upload.txt', $testFilePath, $testUserId);
    
    if (isset($uploadResult['error'])) {
        echo "❌ Secure storage upload error: " . $uploadResult['message'] . "\n";
    } else {
        echo "✅ Secure storage upload successful!\n";
        
        // Get public URL
        $publicUrl = supabaseGetPublicUrl('academic-ease', 'test/test-upload.txt', $testUserId);
        echo "📄 Secure public URL: " . $publicUrl . "\n";
    }
    
    // Test 2: Upload to public folder (for public assets)
    echo "\n📤 Test 2: Upload to public folder\n";
    $uploadResult = supabaseUploadFile('academic-ease', 'public/test-upload.txt', $testFilePath);
    
    if (isset($uploadResult['error'])) {
        echo "❌ Public storage upload error: " . $uploadResult['message'] . "\n";
    } else {
        echo "✅ Public storage upload successful!\n";
        
        // Get public URL
        $publicUrl = supabaseGetPublicUrl('academic-ease', 'public/test-upload.txt');
        echo "📄 Public URL: " . $publicUrl . "\n";
    }
    
    // Clean up test file
    unlink($testFilePath);
} catch (Exception $e) {
    echo "❌ Storage exception: " . $e->getMessage() . "\n";
}

echo "\n===================================\n";
echo "Migration test completed.\n";
echo "===================================\n";
