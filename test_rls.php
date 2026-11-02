<?php
// Include configuration
require_once 'includes/config.php';

// Set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

echo "<h1>Supabase RLS Policy Test</h1>";

// Display Supabase configuration (without showing actual keys)
echo "<h2>Supabase Configuration:</h2>";
echo "<p>SUPABASE_URL: " . (defined('SUPABASE_URL') ? "Defined" : "Not defined") . "</p>";
echo "<p>SUPABASE_KEY: " . (defined('SUPABASE_KEY') ? "Defined (length: " . strlen(SUPABASE_KEY) . ")" : "Not defined") . "</p>";

// Test direct SQL execution to bypass RLS
echo "<h2>Testing Direct SQL Execution:</h2>";
echo "<p>Attempting to create a test user using SQL...</p>";

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

// Create a test user with a unique email
$test_email = 'test_' . time() . '@example.com';
$test_user_id = generate_uuid();

// SQL query to insert a user directly
$sql = "INSERT INTO users (user_id, email, phone_number, password_hash, full_name, user_type, created_at, subscription_status) 
        VALUES ('$test_user_id', '$test_email', '+1234567890', 'test_hash', 'Test User', 'student', NOW(), 'trial')";

// URL for SQL execution
$url = SUPABASE_URL . '/rest/v1/rpc/execute_sql';

// Headers with service role key if available
$headers = [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY
];

// Data for the SQL execution
$data = [
    'sql' => $sql
];

// Execute the SQL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "<p>SQL Execution Status Code: " . $statusCode . "</p>";
if ($curlError) {
    echo "<p>Error: " . $curlError . "</p>";
} else {
    echo "<p>Response: " . htmlspecialchars($response) . "</p>";
}

// Test RLS policy with a temporary bypass
echo "<h2>Alternative Solution:</h2>";
echo "<p>Here are some options to fix the RLS policy issue:</p>";
echo "<ol>";
echo "<li>Make sure you've created an INSERT policy for the 'users' table with target role 'anon'</li>";
echo "<li>Ensure both USING and WITH CHECK expressions are set to 'true'</li>";
echo "<li>Try using the service role key instead of the anon key (requires updating your .env file)</li>";
echo "<li>Temporarily disable RLS for testing: <pre>ALTER TABLE \"public\".\"users\" DISABLE ROW LEVEL SECURITY;</pre></li>";
echo "<li>Create a stored procedure to handle user registration that bypasses RLS</li>";
echo "</ol>";

echo "<h2>Temporary Workaround:</h2>";
echo "<p>If you need to get the application working immediately, you can:</p>";
echo "<ol>";
echo "<li>Create a few test users directly in the Supabase dashboard</li>";
echo "<li>Use those pre-created users to test the rest of your application</li>";
echo "<li>Fix the RLS policy issue later when you have more time</li>";
echo "</ol>";
?>
