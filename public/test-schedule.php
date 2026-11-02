<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Create a test study plan
if (isset($_GET['test']) && $_GET['test'] === 'create_plan') {
    // Generate a UUID for plan_id
    $plan_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    // Create a test study plan
    $test_plan = [
        'plan_id' => $plan_id,
        'user_id' => $_SESSION['user_id'],
        'plan_name' => 'Test Study Plan',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+30 days')),
        'goal' => 'Test study plan for schedule testing',
        'created_at' => date('c')
    ];
    
    // Insert the test plan
    $result = supabaseRequest('study_plans', 'POST', [$test_plan], true);
    
    echo '<h2>Test Plan Creation Result:</h2>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
    
    // Store the plan_id in session for later use
    $_SESSION['test_plan_id'] = $plan_id;
    echo '<p>Plan ID stored in session: ' . $plan_id . '</p>';
    echo '<p><a href="test-schedule.php?test=insert" class="btn btn-primary">Now Insert Test Schedule Item</a></p>';
}

// Simple test to insert a schedule item
if (isset($_GET['test']) && $_GET['test'] === 'insert') {
    // Use the plan_id from session if available, otherwise generate a new one
    if (isset($_SESSION['test_plan_id'])) {
        $plan_id = $_SESSION['test_plan_id'];
        echo '<p>Using plan_id from session: ' . $plan_id . '</p>';
    } else {
        // Generate a proper UUID for plan_id
        $plan_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        echo '<p>Warning: No plan_id found in session. Generated new plan_id: ' . $plan_id . '</p>';
        echo '<p>You should <a href="test-schedule.php?test=create_plan">create a test plan</a> first.</p>';
    }
    
    // Generate a UUID for schedule_id
    $schedule_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    
    // Create a test schedule item with all required fields
    $test_item = [
        'schedule_id' => $schedule_id, // This appears to be required
        'user_id' => $_SESSION['user_id'],
        'plan_id' => $plan_id, // Use UUID format for plan_id
        'subject' => 'mathematics',
        'day_of_week' => 'monday',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'notes' => 'Test schedule item',
        'created_at' => date('c')
    ];
    
    // Insert the test item
    $result = supabaseRequest('study_schedules', 'POST', [$test_item], true);
    
    echo '<h2>Test Insert Result:</h2>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
}

// Check the database structure
if (isset($_GET['test']) && $_GET['test'] === 'structure') {
    // Get the database structure
    $url = SUPABASE_URL . '/rest/v1/study_schedules?limit=0';
    $headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_SERVICE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_KEY
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo '<h2>Database Structure Check:</h2>';
    echo '<p>Status: ' . $status . '</p>';
    
    if ($error) {
        echo '<p>Error: ' . $error . '</p>';
    }
    
    echo '<pre>';
    echo htmlspecialchars($response);
    echo '</pre>';
}

// Simple test to retrieve schedule items
if (isset($_GET['test']) && $_GET['test'] === 'get') {
    // Get all schedule items for this user
    $filter = "user_id=eq." . $_SESSION['user_id'];
    $result = supabaseRequest("study_schedules?$filter", 'GET', null, true);
    
    echo '<h2>Test Get Result:</h2>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
}

// Check existing study plans
if (isset($_GET['test']) && $_GET['test'] === 'plans') {
    // Get all study plans for this user
    $filter = "user_id=eq." . $_SESSION['user_id'];
    $result = supabaseRequest("study_plans?$filter", 'GET', null, true);
    
    echo '<h2>Study Plans:</h2>';
    echo '<pre>';
    print_r($result);
    echo '</pre>';
}

// Display a simple test interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Schedule Test</h1>
        <div class="alert alert-info">
            <strong>Important:</strong> You must create a test plan first before inserting schedule items.
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Step 1: Create Plan</h5>
                        <p>Create a test study plan first</p>
                        <a href="test-schedule.php?test=create_plan" class="btn btn-danger">Create Test Plan</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Step 2: Insert Item</h5>
                        <p>Insert a test schedule item for Monday at 9:00-10:00</p>
                        <a href="test-schedule.php?test=insert" class="btn btn-primary">Insert Test Item</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Test Get</h5>
                        <p>Retrieve all schedule items for the current user</p>
                        <a href="test-schedule.php?test=get" class="btn btn-success">Get Schedule Items</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Check Structure</h5>
                        <p>Check the database table structure</p>
                        <a href="test-schedule.php?test=structure" class="btn btn-info">Check Structure</a>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Check Study Plans</h5>
                        <p>View existing study plans</p>
                        <a href="test-schedule.php?test=plans" class="btn btn-warning">View Plans</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
