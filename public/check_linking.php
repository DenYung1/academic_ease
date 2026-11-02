<?php
// This is a diagnostic script to check the parent-child linking status
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/parent_child_helper.php';

// Set the parent and student emails
$parent_email = 'peter@gmail.com';
$student_email = 'linda@gmail.com';

echo "<h1>Academic Ease - Parent-Child Linking Diagnostic</h1>";

// Get the parent
$parent_data = supabaseRequest("users?email=eq." . urlencode($parent_email), 'GET', null, true);
if (empty($parent_data)) {
    echo "<p style='color:red'>Error: Parent not found with email $parent_email</p>";
    exit;
}
$parent = $parent_data[0];
$parent_id = $parent['user_id'];

echo "<h2>Parent Information</h2>";
echo "<pre>";
// Only show relevant fields
echo "User ID: " . $parent['user_id'] . "\n";
echo "Email: " . $parent['email'] . "\n";
echo "Full Name: " . $parent['full_name'] . "\n";
echo "User Type: " . $parent['user_type'] . "\n";
echo "School Name: " . $parent['school_name'] . "\n";
echo "Metadata: " . print_r(json_decode($parent['metadata'] ?? '{}', true), true) . "\n";
echo "</pre>";

// Get the student
$student_data = supabaseRequest("users?email=eq." . urlencode($student_email), 'GET', null, true);
if (empty($student_data)) {
    echo "<p style='color:red'>Error: Student not found with email $student_email</p>";
    exit;
}
$student = $student_data[0];
$student_id = $student['user_id'];

echo "<h2>Student Information</h2>";
echo "<pre>";
// Only show relevant fields
echo "User ID: " . $student['user_id'] . "\n";
echo "Email: " . $student['email'] . "\n";
echo "Full Name: " . $student['full_name'] . "\n";
echo "User Type: " . $student['user_type'] . "\n";
echo "School Name: " . $student['school_name'] . "\n";
echo "</pre>";

// Check the linking status
echo "<h2>Linking Status</h2>";

// Method 1: Check if student's school_name contains PARENT:parent_id
$method1 = (strpos($student['school_name'] ?? '', "PARENT:$parent_id") === 0);
echo "<p>Method 1 (Student's school_name): " . ($method1 ? "<span style='color:green'>LINKED</span>" : "<span style='color:red'>NOT LINKED</span>") . "</p>";

// Method 2: Check if parent's school_name contains CHILD:student_id
$method2 = (strpos($parent['school_name'] ?? '', "CHILD:$student_id") === 0);
echo "<p>Method 2 (Parent's school_name): " . ($method2 ? "<span style='color:green'>LINKED</span>" : "<span style='color:red'>NOT LINKED</span>") . "</p>";

// Method 3: Check if parent's metadata contains student_id
$parent_metadata = json_decode($parent['metadata'] ?? '{}', true);
$method3 = (isset($parent_metadata['linked_children']) && in_array($student_id, $parent_metadata['linked_children']));
echo "<p>Method 3 (Parent's metadata): " . ($method3 ? "<span style='color:green'>LINKED</span>" : "<span style='color:red'>NOT LINKED</span>") . "</p>";

// Get linked children using the helper function
echo "<h2>Linked Children from Helper Function</h2>";
$linked_children = get_linked_children($parent_id);
echo "<pre>";
print_r($linked_children);
echo "</pre>";

// Fix the linking if needed
echo "<h2>Fix Options</h2>";
echo "<form method='post'>";
echo "<input type='hidden' name='action' value='fix_linking'>";
echo "<button type='submit' style='padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer;'>Fix Linking Issues</button>";
echo "</form>";

// Process the fix if requested
if (isset($_POST['action']) && $_POST['action'] === 'fix_linking') {
    echo "<h2>Fixing Linking Issues...</h2>";
    
    // Method 1: Update student's school_name
    $student_update = [
        'school_name' => 'PARENT:' . $parent_id
    ];
    $student_result = supabaseRequest("users?user_id=eq." . urlencode($student_id), 'PATCH', $student_update, true);
    echo "<p>Method 1 (Update student's school_name): " . (!empty($student_result) ? "<span style='color:green'>SUCCESS</span>" : "<span style='color:red'>FAILED</span>") . "</p>";
    
    // Method 2: Update parent's school_name
    $parent_update = [
        'school_name' => 'CHILD:' . $student_id . ':' . $student['full_name']
    ];
    $parent_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $parent_update, true);
    echo "<p>Method 2 (Update parent's school_name): " . (!empty($parent_result) ? "<span style='color:green'>SUCCESS</span>" : "<span style='color:red'>FAILED</span>") . "</p>";
    
    // Method 3: Update parent's metadata
    $parent_data = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'GET', null, true);
    if (!empty($parent_data)) {
        $parent_metadata = isset($parent_data[0]['metadata']) && !empty($parent_data[0]['metadata']) ? 
            json_decode($parent_data[0]['metadata'], true) : [];
        
        if (!is_array($parent_metadata)) {
            $parent_metadata = [];
        }
        
        if (!isset($parent_metadata['linked_children'])) {
            $parent_metadata['linked_children'] = [];
        }
        
        if (!in_array($student_id, $parent_metadata['linked_children'])) {
            $parent_metadata['linked_children'][] = $student_id;
        }
        
        $metadata_update = [
            'metadata' => json_encode($parent_metadata)
        ];
        $metadata_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $metadata_update, true);
        echo "<p>Method 3 (Update parent's metadata): " . (!empty($metadata_result) ? "<span style='color:green'>SUCCESS</span>" : "<span style='color:red'>FAILED</span>") . "</p>";
    }
    
    echo "<p>All fixes applied. <a href='parent-dashboard.php' style='color: blue;'>Go to Parent Dashboard</a></p>";
}

// Add a link to the parent dashboard
echo "<p><a href='parent-dashboard.php' style='color: blue;'>Go to Parent Dashboard</a></p>";
?>
