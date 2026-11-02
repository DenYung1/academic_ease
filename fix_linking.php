<?php
// Include required files
require_once 'includes/config.php';
require_once 'includes/db_connect.php';

// Get the current user ID (parent)
session_start();
$parent_id = $_SESSION['user_id'];
$parent_name = $_SESSION['name'];

echo "Current logged-in parent: $parent_name (ID: $parent_id)\n\n";

// Get all students
$students = supabaseRequest('users?user_type=eq.student', 'GET', null, true);

echo "Available students to link:\n";
foreach ($students as $index => $student) {
    echo ($index + 1) . ". " . $student['full_name'] . " (Email: " . $student['email'] . ")\n";
}

echo "\nEnter the number of the student you want to link: ";
$choice = trim(fgets(STDIN));
$student_index = (int)$choice - 1;

if (!isset($students[$student_index])) {
    echo "Invalid selection. Please try again.\n";
    exit;
}

$student = $students[$student_index];
$student_id = $student['user_id'];
$student_name = $student['full_name'];

echo "\nLinking student $student_name to parent $parent_name...\n";

// Update the student's school_name field
$update_data = [
    'school_name' => 'PARENT:' . $parent_id
];

$update_result = supabaseRequest("users?user_id=eq." . urlencode($student_id), 'PATCH', $update_data, true);

if (!empty($update_result)) {
    echo "Success! Student linked successfully.\n";
    
    // Clear session data to force refresh
    unset($_SESSION['linked_students']);
    unset($_SESSION['linked_children']);
    unset($_SESSION['has_linked_children']);
    
    echo "\nPlease go back to the parent dashboard to see your linked student.\n";
} else {
    echo "Failed to link student. Please try again.\n";
}
?>
