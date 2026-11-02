<?php
/**
 * Quiz Bridge
 * 
 * This file serves as a bridge between the file-based quiz storage and the student-facing quiz page.
 * It provides functions to retrieve quizzes in a format compatible with the existing quiz.php page.
 */

require_once __DIR__ . '/quiz_storage.php';

/**
 * Get quizzes for student display
 * 
 * @param string $subject Subject filter
 * @param string $level Level filter (jhs, shs)
 * @return array Array of quizzes matching the filters
 */
function getQuizzesForStudents($subject = null, $level = null) {
    // Get all quizzes from file storage
    $allQuizzes = getAllQuizzes();
    $quizzes = array_values($allQuizzes); // Convert to indexed array
    
    // Apply filters if provided
    if ($subject !== null || $level !== null) {
        $filteredQuizzes = [];
        
        foreach ($quizzes as $quiz) {
            $matchesSubject = ($subject === null || $quiz['subject'] === $subject);
            $matchesLevel = ($level === null || $quiz['level'] === $level);
            
            if ($matchesSubject && $matchesLevel) {
                $filteredQuizzes[] = $quiz;
            }
        }
        
        $quizzes = $filteredQuizzes;
    }
    
    // Sort quizzes by title
    usort($quizzes, function($a, $b) {
        return strcmp($a['title'], $b['title']);
    });
    
    return $quizzes;
}

/**
 * Get a specific quiz by ID (this function is already defined in quiz_storage.php,
 * so we'll just use that one instead of redefining it here)
 */
// Function getQuizById is already defined in quiz_storage.php

// This function has been moved to quiz_storage.php to avoid duplication
// Keeping this comment for reference

/**
 * Get quiz results for a specific user
 * 
 * @param int $userId The user ID
 * @return array Array of quiz results for the user
 */
function getUserQuizResults($userId) {
    $resultsPath = __DIR__ . '/../data/quiz_results.json';
    
    if (!file_exists($resultsPath)) {
        return [];
    }
    
    $resultsJson = file_get_contents($resultsPath);
    if (empty($resultsJson)) {
        return [];
    }
    
    $allResults = json_decode($resultsJson, true) ?: [];
    
    // Filter results for the specific user
    $userResults = [];
    foreach ($allResults as $result) {
        if (isset($result['user_id']) && $result['user_id'] == $userId) {
            $userResults[] = $result;
        }
    }
    
    // Sort by completion date (newest first)
    usort($userResults, function($a, $b) {
        return strtotime($b['completion_date']) - strtotime($a['completion_date']);
    });
    
    return $userResults;
}
?>
