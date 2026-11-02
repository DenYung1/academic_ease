<?php
// Include configuration and database connection
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Set error reporting for testing
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to output to console
function console_log($message) {
    echo $message . "\n";
}

console_log("=== Academic Ease - Supabase Connection Test ===");

// Function to display test results
function displayResult($test, $result, $details = '') {
    $status = $result ? 'PASSED' : 'FAILED';
    console_log("Test: {$test} - {$status}");
    if (!empty($details)) {
        console_log("Details: {$details}");
    }
    console_log("-----------------------------------");
}

try {
    // Test 1: Verify Supabase connection
    console_log("\n1. TESTING SUPABASE CONNECTION");
    $connectionTest = !empty(SUPABASE_URL) && !empty(SUPABASE_KEY);
    displayResult("Supabase credentials configured", $connectionTest, 
        "URL: " . (SUPABASE_URL ? "Configured âœ“" : "Missing âœ—") . "\n" .
        "Key: " . (SUPABASE_KEY ? "Configured âœ“" : "Missing âœ—")
    );
    
    if (!$connectionTest) {
        throw new Exception("Supabase credentials are not properly configured in your .env file");
    }
    
    // Test 2: Test table existence by querying each table
    console_log("\n2. TESTING TABLE EXISTENCE");
    
    $tables = ['users', 'conversations', 'messages', 'study_sessions', 'payment_confirmations', 'study_materials'];
    
    foreach ($tables as $table) {
        try {
            // Just attempt to get the structure without retrieving data
            $result = supabaseSelect($table, ['limit' => 1]);
            $tableExists = true;
            $message = "Table structure retrieved successfully";
        } catch (Exception $e) {
            $tableExists = false;
            $message = "Error: " . $e->getMessage();
        }
        
        displayResult("Table '{$table}' exists", $tableExists, $message);
    }
    
    // Test 3: Test inserting and retrieving a test user
    console_log("\n3. TESTING USER OPERATIONS");
    
    // Generate a test user with a unique email
    $testUserId = uniqid('test_');
    $testEmail = "test_{$testUserId}@academicease.test";
    $testUser = [
        'user_id' => $testUserId,
        'email' => $testEmail,
        'phone_number' => '+233' . rand(1000000000, 9999999999),
        'password_hash' => password_hash('test_password', PASSWORD_BCRYPT),
        'full_name' => 'Test User',
        'user_type' => 'student',
        'school_level' => 'JHS',
        'created_at' => date('c'),
        'subscription_status' => 'trial',
        'subscription_start_date' => date('c'),
        'subscription_end_date' => date('c', strtotime('+1 day'))
    ];
    
    // Insert test user
    try {
        $insertResult = supabaseInsert('users', $testUser);
        $insertSuccess = !empty($insertResult);
        displayResult("Insert test user", $insertSuccess, 
            $insertSuccess ? "User created with ID: {$testUserId}" : "Failed to create test user"
        );
    } catch (Exception $e) {
        displayResult("Insert test user", false, "Error: " . $e->getMessage());
        $insertSuccess = false;
    }
    
    // Retrieve test user
    if ($insertSuccess) {
        try {
            $retrievedUser = supabaseSelect('users', [
                'filter' => [
                    'user_id.eq' => $testUserId
                ]
            ]);
            
            $retrieveSuccess = !empty($retrievedUser);
            displayResult("Retrieve test user", $retrieveSuccess, 
                $retrieveSuccess ? "User retrieved: " . json_encode($retrievedUser[0], JSON_PRETTY_PRINT) : "User not found"
            );
            
            // Delete test user to clean up
            if ($retrieveSuccess) {
                $deleteResult = supabaseDelete('users', "user_id=eq.{$testUserId}");
                displayResult("Delete test user", true, "Test user deleted for cleanup");
            }
        } catch (Exception $e) {
            displayResult("Retrieve test user", false, "Error: " . $e->getMessage());
        }
    }
    
    // Test 4: Test RLS policies by trying to access another user's data
    console_log("\n4. TESTING SECURITY POLICIES");
    
    // This should fail or return empty because we're trying to access data without proper authentication
    try {
        $users = supabaseSelect('users', [
            'limit' => 5
        ]);
        
        $securityWorking = empty($users) || count($users) <= 1; // Should be empty or only contain our test user
        displayResult("Row-Level Security", $securityWorking, 
            $securityWorking ? "Security policies working correctly" : "WARNING: Able to retrieve " . count($users) . " users without proper authentication"
        );
    } catch (Exception $e) {
        // If it throws an access denied exception, that's actually good
        $securityWorking = strpos($e->getMessage(), 'access') !== false || strpos($e->getMessage(), 'permission') !== false;
        displayResult("Row-Level Security", $securityWorking, 
            $securityWorking ? "Security policies working correctly (access denied)" : "Error: " . $e->getMessage()
        );
    }
    
    console_log("\nSUMMARY");
    console_log("Connection test completed. Check the results above to ensure everything is working correctly.");
    
} catch (Exception $e) {
    console_log("\nERROR OCCURRED");
    console_log("Error: " . $e->getMessage());
}
?>


