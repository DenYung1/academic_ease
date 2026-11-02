<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/quiz_bridge.php'; // Include the quiz bridge for file-based quizzes

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Use direct API request with service role key to bypass RLS policies
    $filter = "user_id=eq." . $_SESSION['user_id'];
    $users = supabaseRequest("users?$filter", 'GET', null, true);
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $user = $users[0];
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if ($subscription_status === 'pending') {
        // Payment is pending, redirect to subscription page
        header('Location: subscribe-direct.php?pending=1');
        exit;
    } else if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    } else if ($subscription_status !== 'active' && $subscription_status !== 'trial') {
        // User doesn't have an active subscription or trial
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    
    // Get quiz parameters
    $subject = isset($_GET['subject']) ? $_GET['subject'] : 'mathematics';
    $level = isset($_GET['level']) ? $_GET['level'] : 'jhs';
    $quiz_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    // Get available quizzes
    if ($quiz_id !== null && $quiz_id !== '') {
        // Try to get the quiz from file storage first
        $quiz = getQuizById($quiz_id);
        
        // If not found in file storage, try Supabase
        if ($quiz === null && is_numeric($quiz_id) && $quiz_id > 0) {
            $filter = "quiz_id=eq." . $quiz_id;
            $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
            
            if (!empty($quizzes)) {
                $quiz = $quizzes[0];
            }
        }
        
        if ($quiz === null) {
            $error = 'Quiz not found.';
        } else {
            // Get quiz questions
            if (isset($quiz['questions'])) {
                // Questions are already included in the file-based quiz
                $questions = $quiz['questions'];
            } else {
                // Get questions from Supabase for legacy quizzes
                $filter = "quiz_id=eq." . $quiz_id;
                $order = "order=question_order.asc";
                $questions = supabaseRequest("quiz_questions?$filter&$order", 'GET', null, true);
            }
        }
    } else {
        // Get available quizzes for the selected subject and level
        // First get quizzes from file storage
        $fileQuizzes = getQuizzesForStudents($subject, $level);
        
        // Then get quizzes from Supabase
        $filter = "subject=eq." . $subject . "&level=eq." . $level;
        $order = "order=title.asc";
        $dbQuizzes = supabaseRequest("quizzes?$filter&$order", 'GET', null, true) ?: [];
        
        // Merge the quizzes, avoiding duplicates by quiz_id
        $quizzes = $fileQuizzes;
        $existingIds = array_column($fileQuizzes, 'quiz_id');
        
        foreach ($dbQuizzes as $dbQuiz) {
            if (!in_array($dbQuiz['quiz_id'], $existingIds)) {
                $quizzes[] = $dbQuiz;
            }
        }
    }
    
    // Process quiz submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
        $quiz_id = $_POST['quiz_id'];
        $answers = $_POST['answers'] ?? [];
        
        // Try to get the quiz from file storage first
        $quiz = getQuizById($quiz_id);
        
        // If found in file storage, use its questions
        if ($quiz !== null && isset($quiz['questions'])) {
            $questions = $quiz['questions'];
        } else {
            // Otherwise get questions from Supabase for legacy quizzes
            $filter = "quiz_id=eq." . $quiz_id;
            $questions = supabaseRequest("quiz_questions?$filter", 'GET', null, true) ?: [];
        }
        
        $score = 0;
        $total_questions = count($questions);
        $question_results = [];
        
        foreach ($questions as $question) {
            // Make sure question is an array and has required keys
            if (!is_array($question) || !isset($question['question_id']) || !isset($question['correct_answer'])) {
                error_log('Quiz Error: Invalid question format - ' . json_encode($question));
                continue;
            }
            
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
                    
                    // Get the question details to check options
                    foreach ($questions as $q) {
                        if ($q['question_id'] === $question_id) {
                            // Check which option matches the correct answer text
                            $options = [
                                'A' => $q['option_a'] ?? '',
                                'B' => $q['option_b'] ?? '',
                                'C' => $q['option_c'] ?? '',
                                'D' => $q['option_d'] ?? '',
                                'E' => $q['option_e'] ?? ''
                            ];
                            
                            foreach ($options as $letter => $text) {
                                if ($text === $correct_answer) {
                                    $correct_letter = $letter;
                                    break;
                                }
                            }
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
            
            if ($is_correct) {
                $score++;
            }
            
            $question_results[$question_id] = [
                'user_answer' => $user_answer,
                'correct_answer' => $correct_answer,
                'is_correct' => $is_correct
            ];
        }
        
        // Calculate percentage
        $percentage = ($total_questions > 0) ? round(($score / $total_questions) * 100) : 0;
        
        // Save quiz result
        $resultData = [
            'user_id' => $_SESSION['user_id'],
            'quiz_id' => $quiz_id,
            'score' => $score,
            'max_score' => $total_questions,
            'percentage' => $percentage,
            'completion_date' => date('c'),
            'answers' => $answers,
            'question_results' => $question_results
        ];
        
        // Add quiz title and subject for better display in progress page
        if ($quiz) {
            $resultData['quiz_title'] = $quiz['title'] ?? 'Unknown Quiz';
            $resultData['subject'] = $quiz['subject'] ?? '';
            $resultData['level'] = $quiz['level'] ?? '';
        }
        
        // Generate a unique result ID
        $resultData['result_id'] = uniqid('result_', true);
        
        // Try to save to file storage first
        $savedToFile = saveQuizResult($resultData);
        
        // Also save to Supabase for backward compatibility
        $result = supabaseRequest('quiz_results', 'POST', $resultData, true);
        
        // Get quiz details
        $filter = "quiz_id=eq." . $quiz_id;
        $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
        
        if (!empty($quizzes)) {
            $quiz = $quizzes[0];
        }
        
        $quiz_completed = true;
    }
    
    // Get available subjects
    $jhs_subjects = [
        'mathematics' => 'Mathematics',
        'science' => 'Science',
        'english' => 'English',
        'social_studies' => 'Social Studies',
        'ict' => 'ICT',
        'ghanaian_language' => 'Ghanaian Language',
        'french' => 'French',
        'religious_studies' => 'Religious and Moral Education',
        'pre_vocational_skills' => 'Pre-Vocational Skills',
        'pre_technical_skills' => 'Pre-Technical Skills',
        'creative_arts' => 'Creative Arts',
        'physical_education' => 'Physical Education'
    ];
    
    $shs_subjects = [
        'mathematics' => 'Mathematics',
        'english' => 'English',
        'social_studies' => 'Social Studies',
        'integrated_science' => 'Integrated Science',
        'chemistry' => 'Chemistry',
        'physics' => 'Physics',
        'biology' => 'Biology',
        'elective_mathematics' => 'Elective Mathematics',
        'further_mathematics' => 'Further Mathematics',
        'financial_accounting' => 'Financial Accounting',
        'business_management' => 'Business Management',
        'cost_accounting' => 'Cost Accounting',
        'government' => 'Government',
        'economics' => 'Economics',
        'literature' => 'Literature in English',
        'history' => 'History',
        'geography' => 'Geography',
        'french_shs' => 'French (SHS)',
        'ghanaian_language_shs' => 'Ghanaian Language (SHS)',
        'technical_drawing' => 'Technical Drawing',
        'building_construction' => 'Building Construction',
        'woodwork' => 'Woodwork',
        'metalwork' => 'Metalwork',
        'auto_mechanics' => 'Auto Mechanics',
        'electrical_engineering' => 'Electrical Engineering',
        'visual_arts' => 'Visual Arts',
        'graphic_design' => 'Graphic Design',
        'picture_making' => 'Picture Making',
        'ceramics' => 'Ceramics',
        'sculpture' => 'Sculpture',
        'textiles' => 'Textiles',
        'food_nutrition' => 'Food and Nutrition',
        'clothing_textiles' => 'Clothing and Textiles',
        'management_living' => 'Management in Living',
        'agricultural_science' => 'Agricultural Science',
        'crop_husbandry' => 'Crop Husbandry and Horticulture',
        'animal_husbandry' => 'Animal Husbandry',
        'music' => 'Music',
        'physical_education' => 'Physical Education',
        'ict' => 'ICT'
    ];
    
    // Use the appropriate subject list based on level
    $subjects = ($level === 'jhs') ? $jhs_subjects : $shs_subjects;
    
} catch (Exception $e) {
    error_log('Quiz Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the quiz. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($quiz) ? htmlspecialchars($quiz['title']) : 'Quizzes'; ?> - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Embedded Logo CSS to fix 404 error -->
    <style>
    /* AcademicEase Logo Styling */
    .ae-logo-container {
        display: flex;
        align-items: center;
        font-family: 'Arial', sans-serif;
    }
    
    .ae-logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #4285F4, #34A853);
        border-radius: 8px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 10px;
        position: relative;
        overflow: hidden;
    }
    
    .ae-logo-icon::before {
        content: 'A';
        color: white;
        font-size: 24px;
        font-weight: bold;
        line-height: 1;
    }
    
    .ae-logo-icon::after {
        content: 'E';
        color: rgba(255, 255, 255, 0.8);
        font-size: 16px;
        font-weight: bold;
        position: absolute;
        bottom: 2px;
        right: 3px;
        line-height: 1;
    }
    
    .ae-logo-text {
        font-size: 18px;
        font-weight: bold;
    }
    
    .ae-academic {
        color: #4285F4;
    }
    
    .ae-ease {
        color: #34A853;
    }
    </style>
    <link rel="stylesheet" href="../assets/css/enhanced-ui.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .quiz-card {
            transition: transform 0.2s;
        }
        
        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .option-label {
            display: block;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .option-label:hover {
            background-color: #f8f9fa;
        }
        
        input[type="radio"]:checked + .option-label {
            background-color: #e9f5ff;
            border-color: #0d6efd;
        }
        
        .result-correct {
            background-color: rgba(25, 135, 84, 0.1);
            border-color: #198754;
        }
        
        .result-incorrect {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <div class="navbar-brand">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="quiz.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="progress.php">Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="subscribe-direct.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Quiz Content -->
    <div class="container py-4">
        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($quiz_completed)): ?>
        <!-- Quiz Results -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="text-center mb-4">
                    <h2 class="mb-3">Quiz Results</h2>
                    <h3 class="mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($subjects[$quiz['subject']]); ?> - <?php echo strtoupper($quiz['level']); ?></p>
                    
                    <div class="row justify-content-center mt-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center py-4">
                                    <div class="display-1 mb-3 <?php echo $percentage >= 70 ? 'text-success' : ($percentage >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                        <?php echo $percentage; ?>%
                                    </div>
                                    <p class="mb-0">You scored <?php echo $score; ?> out of <?php echo $total_questions; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mb-3">Question Review</h4>
                
                <?php foreach ($questions as $index => $question): ?>
                <div class="card mb-3 <?php echo $question_results[$question['question_id']]['is_correct'] ? 'result-correct' : 'result-incorrect'; ?>">
                    <div class="card-body">
                        <h5 class="card-title">
                            Question <?php echo $index + 1; ?>: 
                            <?php echo htmlspecialchars($question['question_text']); ?>
                        </h5>
                        
                        <div class="mt-3">
                            <p>
                                <strong>Your Answer:</strong> 
                                <?php echo htmlspecialchars($question_results[$question['question_id']]['user_answer']); ?>
                            </p>
                            
                            <?php if (!$question_results[$question['question_id']]['is_correct'] || isset($question['answer_type']) && $question['answer_type'] === 'theory' || isset($question['question_type']) && $question['question_type'] === 'theory'): ?>
                            <p>
                                <strong>Correct Answer:</strong> 
                                <?php 
                                if (isset($question['model_answer'])) {
                                    echo nl2br(htmlspecialchars($question['model_answer']));
                                } else {
                                    echo htmlspecialchars($question_results[$question['question_id']]['correct_answer']);
                                }
                                ?>
                            </p>
                            <?php endif; ?>
                            
                            <?php if (!empty($question['explanation'])): ?>
                            <div class="mt-2">
                                <strong>Explanation:</strong>
                                <p><?php echo nl2br(htmlspecialchars($question['explanation'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="quiz.php?subject=<?php echo $subject; ?>&level=<?php echo $level; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i> Back to Quizzes
                    </a>
                    <a href="ai-assistant.php" class="btn btn-primary">
                        <i class="bi bi-chat-dots me-2"></i> Get Help from AI Assistant
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif (isset($quiz) && isset($questions)): ?>
        <!-- Quiz Questions -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h2 class="mb-3"><?php echo htmlspecialchars($quiz['title']); ?></h2>
                <p class="text-muted"><?php echo htmlspecialchars($subjects[$quiz['subject']]); ?> - <?php echo strtoupper($quiz['level']); ?></p>
                
                <?php if (!empty($quiz['description'])): ?>
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i> <?php echo htmlspecialchars($quiz['description']); ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                    
                    <?php foreach ($questions as $index => $question): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Question <?php echo $index + 1; ?>:</h5>
                            <p class="card-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                            
                            <?php if (!empty($question['image_url'])): ?>
                            <div class="text-center mb-3">
                                <img src="<?php echo htmlspecialchars($question['image_url']); ?>" alt="Question Image" class="img-fluid rounded" style="max-height: 300px;">
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <?php
                                // Check if this is a multiple-choice question or a text input question
                                $isMultipleChoice = isset($question['option_a']) && !empty($question['option_a']);
                                $answerType = isset($question['answer_type']) ? $question['answer_type'] : ($isMultipleChoice ? 'multiple_choice' : 'text');
                                
                                if ($answerType === 'multiple_choice' || $isMultipleChoice): 
                                    // Multiple choice question
                                    $options = [
                                        'A' => $question['option_a'] ?? '',
                                        'B' => $question['option_b'] ?? '',
                                        'C' => $question['option_c'] ?? '',
                                        'D' => $question['option_d'] ?? ''
                                    ];
                                    
                                    if (!empty($question['option_e'])) {
                                        $options['E'] = $question['option_e'];
                                    }
                                ?>
                                    
                                    <?php foreach ($options as $key => $option): ?>
                                        <?php if (!empty($option)): ?>
                                        <div class="option">
                                            <input type="radio" class="btn-check" name="answers[<?php echo $question['question_id']; ?>]" id="option<?php echo $question['question_id'] . $key; ?>" value="<?php echo $key; ?>" required>
                                            <label class="option-label" for="option<?php echo $question['question_id'] . $key; ?>">
                                                <strong><?php echo $key; ?>.</strong> <?php echo htmlspecialchars($option); ?>
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    
                                <?php else: ?>
                                    <!-- Text input question -->
                                    <div class="mb-3">
                                        <label for="text_answer_<?php echo $question['question_id']; ?>" class="form-label">Your Answer:</label>
                                        <input type="text" class="form-control" id="text_answer_<?php echo $question['question_id']; ?>" name="answers[<?php echo $question['question_id']; ?>]" required placeholder="Type your answer here...">
                                        <div class="form-text">Type your answer in the text box above.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between">
                        <a href="quiz.php?subject=<?php echo $subject; ?>&level=<?php echo $level; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i> Cancel
                        </a>
                        <button type="submit" name="submit_quiz" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i> Submit Answers
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Quiz List -->
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Education Level</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="?level=jhs&subject=<?php echo $subject; ?>" class="list-group-item list-group-item-action <?php echo $level === 'jhs' ? 'active' : ''; ?>">
                                <i class="bi bi-book me-2"></i> Junior High School (JHS)
                            </a>
                            <a href="?level=shs&subject=<?php echo $subject; ?>" class="list-group-item list-group-item-action <?php echo $level === 'shs' ? 'active' : ''; ?>">
                                <i class="bi bi-book me-2"></i> Senior High School (SHS)
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Subjects</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($subjects as $subject_key => $subject_name): ?>
                            <a href="?level=<?php echo $level; ?>&subject=<?php echo $subject_key; ?>" class="list-group-item list-group-item-action <?php echo $subject === $subject_key ? 'active' : ''; ?>">
                                <i class="bi bi-journal-text me-2"></i> <?php echo htmlspecialchars($subject_name); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="mb-0"><?php echo htmlspecialchars($subjects[$subject]); ?> Quizzes - <?php echo strtoupper($level); ?></h2>
                        </div>
                        
                        <?php if (empty($quizzes)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            No quizzes found for this subject and level. Please check back later or try another subject.
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100 quiz-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo isset($quiz['title']) ? htmlspecialchars($quiz['title']) : 'Quiz'; ?></h5>
                                        <p class="card-text text-muted small">
                                            <i class="bi bi-tag me-1"></i> <?php echo isset($quiz['category']) ? htmlspecialchars($quiz['category']) : 'General'; ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <span class="badge bg-primary"><?php echo isset($quiz['question_count']) ? $quiz['question_count'] : 0; ?> Questions</span>
                                            <span class="badge bg-secondary"><?php echo isset($quiz['time_limit']) ? $quiz['time_limit'] : 0; ?> Minutes</span>
                                        </div>
                                        <p class="card-text"><?php echo isset($quiz['description']) ? htmlspecialchars(substr($quiz['description'], 0, 100)) . '...' : 'No description available.'; ?></p>
                                    </div>
                                    <div class="card-footer bg-white border-top-0">
                                        <a href="quiz.php?id=<?php echo isset($quiz['quiz_id']) ? $quiz['quiz_id'] : '1'; ?>" class="btn btn-primary w-100">
                                            <i class="bi bi-pencil-square me-1"></i> Start Quiz
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Enhanced UI JavaScript -->
    <script src="assets/js/enhanced-ui.js"></script>
    
    <!-- Settings Panel -->
    <script src="assets/js/settings-panel.js"></script>
    
    <!-- Add CSS for Settings Panel -->
    <style>
        #settings-panel {
            position: fixed;
            top: 50%;
            right: -300px;
            transform: translateY(-50%);
            width: 300px;
            background: white;
            border-radius: 8px 0 0 8px;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            padding: 20px;
            transition: right 0.3s ease;
            z-index: 1001;
        }
        
        #settings-panel.show {
            right: 0;
        }
        
        body.dark-theme {
            background-color: #343a40;
            color: #f8f9fa;
        }
        
        body.dark-theme .card {
            background-color: #495057;
            color: #f8f9fa;
        }
        
        body.dark-theme .list-group-item {
            background-color: #495057;
            color: #f8f9fa;
            border-color: #6c757d;
        }
        
        body.dark-theme .list-group-item.active {
            background-color: #0d6efd;
        }
    </style>
</body>
</html>
        body.dark-theme .list-group-item {
            background-color: #495057;
            color: #f8f9fa;
            border-color: #6c757d;
        }
        
        body.dark-theme .list-group-item.active {
            background-color: #0d6efd;
        }
    </style>
</body>
</html>
