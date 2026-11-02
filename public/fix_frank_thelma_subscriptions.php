<?php
/**
 * Fix Frank and Thelma's subscription synchronization issue
 * This is a one-time script to fix the specific issue with these accounts
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/subscription_helper.php';

// Set error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

echo "<h1>Fixing Frank and Thelma's Subscription Synchronization</h1>";

// Step 1: Find Frank's account
$frank = supabaseRequest("users?email=eq.frank@gmail.com", 'GET', null, true);

if (empty($frank) || !isset($frank[0])) {
    die("Error: Could not find Frank's account");
}

$frank = $frank[0];
echo "<p>Found Frank's account: " . htmlspecialchars($frank['full_name']) . " (ID: " . $frank['user_id'] . ")</p>";
echo "<p>Current subscription: " . $frank['subscription_status'] . " (Plan: " . $frank['subscription_plan'] . ", Expires: " . $frank['subscription_end_date'] . ")</p>";

// Step 2: Find Thelma's account
$thelma = supabaseRequest("users?email=eq.thelma@gmail.com", 'GET', null, true);

if (empty($thelma) || !isset($thelma[0])) {
    die("Error: Could not find Thelma's account");
}

$thelma = $thelma[0];
echo "<p>Found Thelma's account: " . htmlspecialchars($thelma['full_name']) . " (ID: " . $thelma['user_id'] . ")</p>";
echo "<p>Current subscription: " . $thelma['subscription_status'] . " (Plan: " . $thelma['subscription_plan'] . ", Expires: " . $thelma['subscription_end_date'] . ")</p>";

// Step 3: Verify parent-child relationship
$relationship = supabaseRequest("parent_child?parent_id=eq." . $thelma['user_id'] . "&child_id=eq." . $frank['user_id'], 'GET', null, true);

if (empty($relationship)) {
    echo "<p>Warning: No parent-child relationship found. Creating one now...</p>";
    
    // Create parent-child relationship
    $relationship_data = [
        'parent_id' => $thelma['user_id'],
        'child_id' => $frank['user_id'],
        'created_at' => date('c')
    ];
    
    $result = supabaseRequest("parent_child", 'POST', $relationship_data, true);
    
    if (empty($result)) {
        die("Error: Failed to create parent-child relationship");
    }
    
    echo "<p>Created parent-child relationship between Thelma and Frank</p>";
} else {
    echo "<p>Verified parent-child relationship between Thelma and Frank</p>";
}

// Step 4: Determine which subscription to use
// We'll use the one with the later expiration date
$frank_expiry = strtotime($frank['subscription_end_date']);
$thelma_expiry = strtotime($thelma['subscription_end_date']);

echo "<p>Frank's subscription expires: " . date('Y-m-d H:i:s', $frank_expiry) . "</p>";
echo "<p>Thelma's subscription expires: " . date('Y-m-d H:i:s', $thelma_expiry) . "</p>";

$subscription_status = '';
$subscription_plan = '';
$subscription_end_date = '';

if ($frank_expiry > $thelma_expiry) {
    echo "<p>Using Frank's subscription details (later expiry date)</p>";
    $subscription_status = $frank['subscription_status'];
    $subscription_plan = $frank['subscription_plan'];
    $subscription_end_date = $frank['subscription_end_date'];
} else {
    echo "<p>Using Thelma's subscription details (later expiry date)</p>";
    $subscription_status = $thelma['subscription_status'];
    $subscription_plan = $thelma['subscription_plan'];
    $subscription_end_date = $thelma['subscription_end_date'];
}

// Check if subscription is actually expired
$expiry_time = strtotime($subscription_end_date);
$current_time = time();

if ($expiry_time <= $current_time) {
    echo "<p>The subscription has expired. Setting status to 'expired'</p>";
    $subscription_status = 'expired';
}

echo "<p>Final subscription details to apply: Status=$subscription_status, Plan=$subscription_plan, Expires=$subscription_end_date</p>";

// Step 5: Update both accounts with the same subscription details
echo "<h2>Updating Subscriptions</h2>";

// Update Thelma (parent)
$thelma_update = [
    'subscription_status' => $subscription_status,
    'subscription_plan' => $subscription_plan,
    'subscription_end_date' => $subscription_end_date,
    'last_updated' => date('c')
];

$thelma_result = supabaseRequest("users?user_id=eq." . $thelma['user_id'], 'PATCH', $thelma_update, true);

if (!empty($thelma_result)) {
    echo "<p>Successfully updated Thelma's subscription</p>";
} else {
    echo "<p>Error: Failed to update Thelma's subscription</p>";
}

// Update Frank (child)
$frank_update = [
    'subscription_status' => $subscription_status,
    'subscription_plan' => $subscription_plan,
    'subscription_end_date' => $subscription_end_date,
    'last_updated' => date('c')
];

$frank_result = supabaseRequest("users?user_id=eq." . $frank['user_id'], 'PATCH', $frank_update, true);

if (!empty($frank_result)) {
    echo "<p>Successfully updated Frank's subscription</p>";
} else {
    echo "<p>Error: Failed to update Frank's subscription</p>";
}

echo "<h2>Subscription Synchronization Complete</h2>";
echo "<p>Both accounts now have the same subscription details:</p>";
echo "<ul>";
echo "<li>Status: " . $subscription_status . "</li>";
echo "<li>Plan: " . $subscription_plan . "</li>";
echo "<li>Expiration Date: " . $subscription_end_date . "</li>";
echo "</ul>";

echo "<p><a href='admin/users.php'>Return to User Management</a></p>";
?>
