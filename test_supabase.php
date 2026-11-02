<?php
// Include configuration
require_once 'includes/config.php';

// Set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

echo "<h1>Supabase Connection Test</h1>";

// Test Supabase connection
$url = SUPABASE_URL . '/rest/v1/users?limit=1';
$headers = [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<h2>Connection Test Results:</h2>";
echo "<p>Status Code: " . $statusCode . "</p>";

if ($curlError) {
    echo "<p>Error: " . $curlError . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars($response) . "</p>";
}

// Test RLS policy
echo "<h2>RLS Policy Test:</h2>";
echo "<p>Attempting to insert a test record...</p>";

// Function to generate a valid UUID v4
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$testUser = [
    'user_id' => generate_uuid(),
    'email' => 'test-' . uniqid() . '@example.com',
    'phone_number' => '+1234567890',
    'password_hash' => 'test_hash',
    'full_name' => 'Test User',
    'user_type' => 'student',
    'school_level' => 'JHS3',
    'school_name' => null,
    'created_at' => date('c'),
    'last_login' => date('c'),
    'subscription_status' => 'trial',
    'subscription_plan' => null,
    'subscription_start_date' => date('c'),
    'subscription_end_date' => date('c', strtotime('+1 day')),
    'subscription_auto_renew' => false
];

$url = SUPABASE_URL . '/rest/v1/users';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testUser));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p>Insert Status Code: " . $statusCode . "</p>";
if ($curlError) {
    echo "<p>Insert Error: " . $curlError . "</p>";
} else {
    echo "<p>Insert Response: " . htmlspecialchars($response) . "</p>";
}

// Check if the response contains the RLS policy error
if (strpos($response, 'row-level security policy') !== false) {
    echo "<h3>RLS Policy Issue Detected</h3>";
    echo "<p>Your Supabase RLS policy is preventing inserts. Here are some solutions:</p>";
    echo "<ol>";
    echo "<li>Make sure you've created an INSERT policy for the 'users' table with target role 'anon'</li>";
    echo "<li>Ensure both USING and WITH CHECK expressions are set to 'true'</li>";
    echo "<li>Try creating the policy with this SQL: <pre>CREATE POLICY \"Anyone can register\" ON \"public\".\"users\" FOR INSERT TO anon USING (true) WITH CHECK (true);</pre></li>";
    echo "<li>Alternatively, you can temporarily disable RLS for testing: <pre>ALTER TABLE \"public\".\"users\" DISABLE ROW LEVEL SECURITY;</pre></li>";
    echo "</ol>";
}
?>
