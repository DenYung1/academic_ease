<?php
/**
 * Quiz Storage Helper Functions
 * Provides file-based storage for quizzes to ensure persistence
 */

/**
 * Get the path to the quiz storage file
 * 
 * @return string Path to the quiz storage file
 */
function getQuizStoragePath() {
    return __DIR__ . '/../data/quizzes.json';
}

/**
 * Ensure the data directory exists
 */
function ensureDataDirectoryExists() {
    $dataDir = __DIR__ . '/../data';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
}

/**
 * Save a quiz to the storage file
 * 
 * @param array $quiz The quiz data to save
 * @return bool True if successful, false otherwise
 */
function saveQuiz($quiz) {
    ensureDataDirectoryExists();
    
    // Generate a unique ID if not provided
    if (!isset($quiz['quiz_id'])) {
        $quiz['quiz_id'] = uniqid('quiz_', true);
    }
    
    // Get existing quizzes
    $quizzes = getAllQuizzes();
    
    // Add or update the quiz
    $quizzes[$quiz['quiz_id']] = $quiz;
    
    // Save back to file
    $success = file_put_contents(getQuizStoragePath(), json_encode($quizzes, JSON_PRETTY_PRINT));
    
    return $success !== false;
}

/**
 * Get all quizzes from the storage file
 * 
 * @return array Array of quizzes
 */
function getAllQuizzes() {
    ensureDataDirectoryExists();
    
    $path = getQuizStoragePath();
    
    if (!file_exists($path)) {
        return [];
    }
    
    $content = file_get_contents($path);
    
    if (empty($content)) {
        return [];
    }
    
    $quizzes = json_decode($content, true);
    
    if (!is_array($quizzes)) {
        return [];
    }
    
    return $quizzes;
}

/**
 * Delete a quiz from the storage file
 * 
 * @param string $quizId The ID of the quiz to delete
 * @return bool True if successful, false otherwise
 */
function deleteQuiz($quizId) {
    $quizzes = getAllQuizzes();
    
    if (!isset($quizzes[$quizId])) {
        return false;
    }
    
    unset($quizzes[$quizId]);
    
    $success = file_put_contents(getQuizStoragePath(), json_encode($quizzes, JSON_PRETTY_PRINT));
    
    return $success !== false;
}

/**
 * Get a single quiz by ID
 * 
 * @param string $quizId The ID of the quiz to get
 * @return array|null The quiz data or null if not found
 */
function getQuizById($quizId) {
    $quizzes = getAllQuizzes();
    
    return isset($quizzes[$quizId]) ? $quizzes[$quizId] : null;
}

/**
 * Get the path to the quiz results storage file
 * 
 * @return string Path to the quiz results storage file
 */
function getQuizResultsStoragePath() {
    return __DIR__ . '/../data/quiz_results.json';
}

/**
 * Save a quiz result to the storage file
 * 
 * @param array $resultData The quiz result data to save
 * @return bool True if successful, false otherwise
 */
function saveQuizResult($resultData) {
    ensureDataDirectoryExists();
    
    // Generate a unique ID if not provided
    if (!isset($resultData['result_id'])) {
        $resultData['result_id'] = uniqid('result_', true);
    }
    
    // Add question results if available
    if (isset($_POST['answers']) && isset($resultData['quiz_id'])) {
        $answers = $_POST['answers'];
        $resultData['answers'] = $answers;
        
        // Get quiz questions to store detailed results
        $quiz = getQuizById($resultData['quiz_id']);
        if ($quiz && isset($quiz['questions'])) {
            $question_results = [];
            foreach ($quiz['questions'] as $question) {
                $question_id = $question['question_id'];
                $user_answer = isset($answers[$question_id]) ? $answers[$question_id] : '';
                $correct_answer = $question['correct_answer'];
                
                // Check if this is a text input question or multiple choice
                $isTextInput = isset($question['answer_type']) && $question['answer_type'] === 'text';
                
                if ($isTextInput) {
                    // For text input, do a case-insensitive trim comparison
                    // Also handle numeric answers that might have different formats (e.g., "7" vs 7)
                    if (is_numeric($user_answer) && is_numeric($correct_answer)) {
                        // For numeric answers, compare as numbers to avoid format issues
                        $is_correct = (floatval(trim($user_answer)) == floatval(trim($correct_answer)));
                    } else {
                        // For text answers, do a case-insensitive comparison
                        $is_correct = (strtolower(trim($user_answer)) === strtolower(trim($correct_answer)));
                    }
                } else {
                    // For multiple choice, we standardize to uppercase letter comparison
                    // The student's answer is already a letter (A, B, C, D, E)
                    $user_letter = strtoupper($user_answer);
                    
                    // The correct answer should be a letter (A, B, C, D, E) after our standardization
                    // But we handle all possible formats for backward compatibility
                    if (in_array(strtoupper($correct_answer), ['A', 'B', 'C', 'D', 'E'])) {
                        // If the correct answer is stored as a letter
                        $is_correct = ($user_letter === strtoupper($correct_answer));
                    } else if (is_numeric($correct_answer) && $correct_answer >= 0 && $correct_answer <= 4) {
                        // If the correct answer is stored as a numeric index (0-4)
                        $letter_map = ['A', 'B', 'C', 'D', 'E'];
                        $is_correct = ($user_letter === $letter_map[(int)$correct_answer]);
                    } else {
                        // For text-based correct answers, we need to find which option it matches
                        $correct_letter = null;
                        
                        // Check which option matches the correct answer text
                        $options = [
                            'A' => $question['option_a'] ?? '',
                            'B' => $question['option_b'] ?? '',
                            'C' => $question['option_c'] ?? '',
                            'D' => $question['option_d'] ?? '',
                            'E' => $question['option_e'] ?? ''
                        ];
                        
                        foreach ($options as $letter => $text) {
                            if ($text === $correct_answer) {
                                $correct_letter = $letter;
                                break;
                            }
                        }
                        
                        if ($correct_letter) {
                            $is_correct = ($user_letter === $correct_letter);
                        } else {
                            // Fallback to direct comparison if no match found
                            $is_correct = ($user_answer === $correct_answer);
                        }
                    }
                }
                
                $question_results[$question_id] = [
                    'user_answer' => $user_answer,
                    'correct_answer' => $correct_answer,
                    'is_correct' => $is_correct
                ];
            }
            $resultData['question_results'] = $question_results;
        }
    }
    
    // Get existing results
    $results = getAllQuizResults();
    
    // Add the new result
    $results[] = $resultData;
    
    // Save back to file
    $success = file_put_contents(getQuizResultsStoragePath(), json_encode($results, JSON_PRETTY_PRINT));
    
    return $success !== false;
}

/**
 * Get all quiz results from the storage file
 * 
 * @return array Array of quiz results
 */
function getAllQuizResults() {
    ensureDataDirectoryExists();
    
    $path = getQuizResultsStoragePath();
    
    if (!file_exists($path)) {
        return [];
    }
    
    $content = file_get_contents($path);
    
    if (empty($content)) {
        return [];
    }
    
    $results = json_decode($content, true);
    
    if (!is_array($results)) {
        return [];
    }
    
    return $results;
}

/**
 * Get quiz results for a specific user
 * 
 * @param string $userId The ID of the user
 * @param string $quizId Optional quiz ID to filter results
 * @return array Array of quiz results for the user
 */
function getQuizResultsForUser($userId, $quizId = null) {
    $allResults = getAllQuizResults();
    $userResults = [];
    
    foreach ($allResults as $result) {
        // Check if this result belongs to the user
        if (isset($result['user_id']) && $result['user_id'] === $userId) {
            // If a quiz ID is specified, only include results for that quiz
            if ($quizId === null || (isset($result['quiz_id']) && $result['quiz_id'] === $quizId)) {
                $userResults[] = $result;
            }
        }
    }
    
    // Sort by completion date (newest first)
    usort($userResults, function($a, $b) {
        $dateA = isset($a['completion_date']) ? strtotime($a['completion_date']) : 0;
        $dateB = isset($b['completion_date']) ? strtotime($b['completion_date']) : 0;
        return $dateB - $dateA;
    });
    
    return $userResults;
}

/**
 * Get the latest quiz result for a specific user and quiz
 * 
 * @param string $userId The ID of the user
 * @param string $quizId The ID of the quiz
 * @return array|null The quiz result or null if not found
 */
function getLatestQuizResult($userId, $quizId) {
    $results = getQuizResultsForUser($userId, $quizId);
    
    // Return the first result (which is the most recent due to sorting)
    return !empty($results) ? $results[0] : null;
}
