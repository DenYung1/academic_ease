<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
// Note: functions.php is not required

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to view questions'
    ]);
    exit;
}

// Get question ID from request
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Question ID is required'
    ]);
    exit;
}

$question_id = $_GET['id'];

// Fetch question data from Supabase
$endpoint = 'past_questions?question_id=eq.' . urlencode($question_id);
$questions = supabaseRequest($endpoint, 'GET', null, true);

// Check if question exists
if (empty($questions)) {
    echo json_encode([
        'success' => false,
        'message' => 'Question not found'
    ]);
    exit;
}

$question = $questions[0];

// Return question data
echo json_encode([
    'success' => true,
    'title' => $question['title'] ?? 'Untitled Question',
    'content' => $question['content'] ?? $question['description'] ?? $question['question_text'] ?? '',
    'file_path' => $question['file_path'] ?? '',
    'image_url' => $question['image_url'] ?? '',
    'subject' => $question['subject'] ?? '',
    'level' => $question['level'] ?? '',
    'difficulty' => $question['difficulty'] ?? 'Medium',
]);
