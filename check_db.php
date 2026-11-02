<?php
// Script to check database contents
require_once './includes/db_connect.php';

// Get all users
$all_users = supabaseRequest('users', 'GET', null, true);
echo "Total users: " . count($all_users) . "\n\n";

// Count students
$students = [];
$parents = [];
$linked_students = [];
$linked_parents = [];

foreach ($all_users as $user) {
    if (isset($user['user_type']) && $user['user_type'] === 'student') {
        $students[] = $user;
        
        // Check if this student is linked to a parent
        if (isset($user['school_name']) && strpos($user['school_name'], 'PARENT:') === 0) {
            $linked_students[] = $user;
        }
    }
    
    if (isset($user['user_type']) && $user['user_type'] === 'parent') {
        $parents[] = $user;
        
        // Check if this parent has linked children
        if (isset($user['school_name']) && strpos($user['school_name'], 'CHILD:') === 0) {
            $linked_parents[] = $user;
        }
    }
}

echo "Students: " . count($students) . "\n";
echo "Parents: " . count($parents) . "\n";
echo "Students linked to parents: " . count($linked_students) . "\n";
echo "Parents with linked children: " . count($linked_parents) . "\n\n";

// Show student details
echo "STUDENT DETAILS:\n";
echo "----------------\n";
foreach ($students as $student) {
    echo "ID: " . (isset($student['user_id']) ? $student['user_id'] : 'N/A') . "\n";
    echo "Name: " . (isset($student['full_name']) ? $student['full_name'] : 'N/A') . "\n";
    echo "Email: " . (isset($student['email']) ? $student['email'] : 'N/A') . "\n";
    echo "School Level: " . (isset($student['school_level']) ? $student['school_level'] : 'N/A') . "\n";
    echo "School Name: " . (isset($student['school_name']) ? $student['school_name'] : 'N/A') . "\n";
    echo "----------------\n";
}

// Show parent details
echo "\nPARENT DETAILS:\n";
echo "----------------\n";
foreach ($parents as $parent) {
    echo "ID: " . (isset($parent['user_id']) ? $parent['user_id'] : 'N/A') . "\n";
    echo "Name: " . (isset($parent['full_name']) ? $parent['full_name'] : 'N/A') . "\n";
    echo "Email: " . (isset($parent['email']) ? $parent['email'] : 'N/A') . "\n";
    echo "School Name: " . (isset($parent['school_name']) ? $parent['school_name'] : 'N/A') . "\n";
    echo "----------------\n";
}
