<?php
/**
 * Generate Standard Quizzes Script
 * 
 * This script automatically generates standard quizzes for all subjects
 * following the Ghana Education Service (GES) standards.
 */

// Determine the correct path based on how the script is being called
$base_path = __DIR__ . '/../';

// Include necessary files
require_once $base_path . 'includes/config.php';
require_once $base_path . 'includes/db_connect.php';
require_once $base_path . 'includes/quiz_storage.php';

// Set error reporting for debugging
ini_set('display_errors', 0);
error_log('Starting standard quiz generation script');

// Define quiz templates by subject and level
$quizTemplates = [
    // JHS Mathematics - Advanced Algebra (Difficult)
    [
        'title' => 'JHS Mathematics - Advanced Algebra (Difficult)',
        'description' => 'A challenging quiz on advanced algebraic concepts for JHS students. This quiz contains both multiple-choice and text input questions.',
        'subject' => 'mathematics',
        'level' => 'jhs',
        'questions' => [
            [
                'question_text' => 'Solve for x: 2(3x - 4) - 3(x + 2) = 5x - 12',
                'answer_type' => 'text',
                'correct_answer' => '2',
                'explanation' => 'Expanding the brackets: 6x - 8 - 3x - 6 = 5x - 12. Simplifying: 3x - 14 = 5x - 12. Rearranging: 3x - 5x = -12 + 14. Therefore -2x = 2, so x = -1.',
                'order' => 1
            ],
            [
                'question_text' => 'If f(x) = 2xÂ² - 3x + 1 and g(x) = x - 2, find f(g(3)).',
                'answer_type' => 'text',
                'correct_answer' => '3',
                'explanation' => 'g(3) = 3 - 2 = 1. Then f(g(3)) = f(1) = 2(1)Â² - 3(1) + 1 = 2 - 3 + 1 = 0.',
                'order' => 2
            ]
        ]
    ],
    
    // SHS Physics - Advanced Mechanics (Difficult)
    [
        'title' => 'SHS Physics - Advanced Mechanics (Difficult)',
        'description' => 'A challenging quiz on advanced mechanics concepts for SHS students. This quiz contains both multiple-choice and theory questions.',
        'subject' => 'physics',
        'level' => 'shs',
        'questions' => [
            [
                'question_text' => 'A 2 kg object is moving with a velocity of 5 m/s when it experiences a constant force of 10 N in the direction of motion for 3 seconds. What is its final velocity?',
                'answer_type' => 'text',
                'correct_answer' => '20',
                'explanation' => 'Using the equation v = u + at, where a = F/m = 10/2 = 5 m/sÂ². So v = 5 + 5 Ã— 3 = 5 + 15 = 20 m/s.',
                'order' => 1
            ],
            [
                'question_text' => 'Explain the concept of simple harmonic motion and derive the equation for the period of a simple pendulum. Discuss the assumptions made in this derivation.',
                'question_type' => 'theory',
                'model_answer' => 'Simple Harmonic Motion (SHM) is a type of periodic motion where the restoring force is directly proportional to the displacement from the equilibrium position and acts in the opposite direction to the displacement.',
                'order' => 2
            ]
        ]
    ]
];

// Function to generate a unique ID
function generateUniqueId() {
    return uniqid('quiz_') . '_' . time();
}

/**
 * Check if a quiz with the same title and subject already exists
 * 
 * @param string $title The quiz title
 * @param string $subject The quiz subject
 * @param string $level The education level (jhs/shs)
 * @return bool True if the quiz already exists, false otherwise
 */
function quizExists($title, $subject, $level) {
    // Get all existing quizzes
    $existingQuizzes = getAllQuizzes();
    
    foreach ($existingQuizzes as $quiz) {
        // Check if there's a quiz with the same title, subject, and level
        if (isset($quiz['title']) && isset($quiz['subject']) && isset($quiz['level']) &&
            $quiz['title'] === $title && 
            $quiz['subject'] === $subject && 
            $quiz['level'] === $level) {
            return true;
        }
    }
    
    return false;
}

// Counter for tracking created quizzes
$createdCount = 0;
$errorCount = 0;

// Process each quiz template
foreach ($quizTemplates as $template) {
    try {
        // Check if this quiz already exists
        if (quizExists($template['title'], $template['subject'], $template['level'])) {
            error_log("Skipping duplicate quiz: {$template['title']}");
            continue; // Skip to the next template
        }
        
        // Create quiz data structure
        $quizId = generateUniqueId();
        $quiz = [
            'quiz_id' => $quizId,
            'title' => $template['title'],
            'description' => $template['description'],
            'subject' => $template['subject'],
            'level' => $template['level'],
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => 'system',
            'questions' => []
        ];
        
        // Add questions to the quiz
        foreach ($template['questions'] as $index => $questionData) {
            $questionId = $quizId . '_q' . ($index + 1);
            
            // Check if this is a text input question or multiple choice
            $isTextInput = isset($questionData['answer_type']) && $questionData['answer_type'] === 'text';
            $isTheory = isset($questionData['question_type']) && $questionData['question_type'] === 'theory';
            
            if ($isTheory) {
                // Theory question (essay/long answer)
                $question = [
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'question_text' => $questionData['question_text'],
                    'answer_type' => 'theory',
                    'model_answer' => $questionData['model_answer'],
                    'explanation' => isset($questionData['explanation']) ? $questionData['explanation'] : '',
                    'order' => isset($questionData['order']) ? $questionData['order'] : ($index + 1)
                ];
            } elseif ($isTextInput) {
                // Text input question (short answer)
                $question = [
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'question_text' => $questionData['question_text'],
                    'answer_type' => 'text',
                    'correct_answer' => $questionData['correct_answer'],
                    'explanation' => isset($questionData['explanation']) ? $questionData['explanation'] : '',
                    'order' => isset($questionData['order']) ? $questionData['order'] : ($index + 1)
                ];
            } else {
                // Multiple choice question
                $question = [
                    'question_id' => $questionId,
                    'quiz_id' => $quizId,
                    'question_text' => isset($questionData['question_text']) ? $questionData['question_text'] : $questionData['question'],
                    'option_a' => $questionData['options'][0],
                    'option_b' => $questionData['options'][1],
                    'option_c' => $questionData['options'][2],
                    'option_d' => $questionData['options'][3],
                    'correct_answer' => $questionData['correct_answer'],
                    'explanation' => isset($questionData['explanation']) ? $questionData['explanation'] : '',
                    'order' => isset($questionData['order']) ? $questionData['order'] : ($index + 1)
                ];
                
                // Add option E if it exists
                if (isset($questionData['options'][4])) {
                    $question['option_e'] = $questionData['options'][4];
                }
            }
            
            $quiz['questions'][] = $question;
        }
        
        // Save the quiz to storage
        $result = saveQuiz($quiz);
        
        if ($result) {
            $createdCount++;
            error_log("Created quiz: {$template['title']}");
        } else {
            $errorCount++;
            error_log("Failed to create quiz: {$template['title']}");
        }
    } catch (Exception $e) {
        $errorCount++;
        error_log("Error creating quiz {$template['title']}: " . $e->getMessage());
    }
}

// Output results
echo "Quiz generation completed.\n";
echo "Successfully created: $createdCount quizzes\n";
echo "Errors: $errorCount\n";
echo "See error log for details.\n";

// Function to run the script from the browser
if (isset($_GET['run']) && $_GET['run'] === 'true') {
    header('Content-Type: text/plain');
    echo "Running standard quiz generation...\n";
    echo "Created $createdCount quizzes with $errorCount errors.\n";
    echo "Refresh the quizzes page to see the new quizzes.";
}
?>
