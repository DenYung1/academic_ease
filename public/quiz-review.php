<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/quiz_bridge.php'; // Include the quiz bridge for file-based quizzes

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get quiz ID and result ID from URL
$quiz_id = isset($_GET['quiz_id']) ? $_GET['quiz_id'] : null;
$result_id = isset($_GET['result_id']) ? $_GET['result_id'] : null;

// If no quiz ID provided, redirect to quizzes page
if (!$quiz_id) {
    header('Location: quiz.php');
    exit;
}

// Get user data and quiz result
try {
    // Get user data
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
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
    
    // Get the quiz data
    $quiz = null;
    $questions = [];
    $quiz_result = null;
    
    // Try to get the quiz from file storage first
    $quiz = getQuizById($quiz_id);
    
    // If not found in file storage, try Supabase
    if ($quiz === null) {
        $filter = "quiz_id=eq." . $quiz_id;
        $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
        
        if (!empty($quizzes)) {
            $quiz = $quizzes[0];
        }
    }
    
    // Get quiz questions
    if ($quiz !== null) {
        if (isset($quiz['questions'])) {
            // Questions are already included in the file-based quiz
            $questions = $quiz['questions'];
        } else {
            // Get questions from Supabase for legacy quizzes
            $filter = "quiz_id=eq." . $quiz_id;
            $order = "order=question_order.asc";
            $questions = supabaseRequest("quiz_questions?$filter&$order", 'GET', null, true) ?: [];
        }
    }
    
    // Get the quiz result
    if ($result_id && $result_id !== '0') {
        // Try to get from file storage first
        $file_results = getQuizResultsForUser($_SESSION['user_id']); // Using the function from quiz_storage.php
        foreach ($file_results as $result) {
            if (isset($result['result_id']) && $result['result_id'] === $result_id) {
                $quiz_result = $result;
                break;
            }
        }
        
        // If not found, try Supabase
        if ($quiz_result === null) {
            $filter = "result_id=eq." . $result_id;
            $results = supabaseRequest("quiz_results?$filter", 'GET', null, true);
            
            if (!empty($results)) {
                $quiz_result = $results[0];
            }
        }
    }
    
    // If we don't have a specific result, try to get the latest result for this quiz
    if ($quiz_result === null) {
        // Use the new getLatestQuizResult function
        $quiz_result = getLatestQuizResult($_SESSION['user_id'], $quiz_id);
        
        // If not found in file storage, try Supabase
        if ($quiz_result === null) {
            $filter = "user_id=eq." . $_SESSION['user_id'] . "&quiz_id=eq." . $quiz_id . "&order=completion_date.desc&limit=1";
            $results = supabaseRequest("quiz_results?$filter", 'GET', null, true);
            
            if (!empty($results)) {
                $quiz_result = $results[0];
            }
        }
    }
    
    // Debug the quiz result
    error_log('Final quiz_result: ' . json_encode($quiz_result));
    
    // Calculate score percentage
    $score_percentage = 0;
    if ($quiz_result) {
        if (isset($quiz_result['percentage'])) {
            $score_percentage = $quiz_result['percentage'];
        } elseif (isset($quiz_result['score_percentage'])) {
            $score_percentage = $quiz_result['score_percentage'];
        } elseif (isset($quiz_result['score']) && isset($quiz_result['max_score']) && $quiz_result['max_score'] > 0) {
            $score_percentage = round(($quiz_result['score'] / $quiz_result['max_score']) * 100);
        }
    }
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe.php');
        exit;
    }
    
} catch (Exception $e) {
    $error = "An error occurred: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Review - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/enhanced-ui.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .result-correct {
            background-color: rgba(25, 135, 84, 0.1);
            border-color: #198754;
        }
        
        .result-incorrect {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: #dc3545;
        }
        
        .option-label {
            display: block;
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        
        .option-correct {
            background-color: rgba(25, 135, 84, 0.1);
            border-color: #198754;
        }
        
        .option-incorrect {
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
                        <a class="nav-link" href="quiz.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="progress.php">Progress</a>
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
    
    <!-- Main Content -->
    <div class="container py-4">
        <?php if (!$quiz): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Quiz not found.
            </div>
            <div class="text-center mt-4">
                <a href="quiz.php" class="btn btn-primary">Back to Quizzes</a>
            </div>
        <?php else: ?>
            <!-- Quiz Results -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h2 class="mb-3">Quiz Results</h2>
                        <h3 class="mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h3>
                        <?php 
                        $subject_key = $quiz['subject'] ?? '';
                        $level_key = $quiz['level'] ?? '';
                        
                        // Get subject and level display names
                        $subjects = [
                            'mathematics' => 'Mathematics',
                            'english' => 'English Language',
                            'science' => 'Integrated Science',
                            'social_studies' => 'Social Studies',
                            'core_mathematics' => 'Core Mathematics',
                            'english_language' => 'English Language',
                            'integrated_science' => 'Integrated Science',
                            'biology' => 'Biology',
                            'chemistry' => 'Chemistry',
                            'physics' => 'Physics'
                        ];
                        
                        $levels = [
                            'jhs' => 'Junior High School',
                            'shs' => 'Senior High School'
                        ];
                        
                        $subject_display = isset($subjects[$subject_key]) ? $subjects[$subject_key] : $subject_key;
                        $level_display = isset($levels[$level_key]) ? $levels[$level_key] : strtoupper($level_key);
                        ?>
                        <p class="text-muted"><?php echo htmlspecialchars($subject_display); ?> - <?php echo $level_display; ?></p>
                        
                        <div class="row justify-content-center mt-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center py-4">
                                        <div class="display-1 mb-3 <?php echo $score_percentage >= 70 ? 'text-success' : ($score_percentage >= 50 ? 'text-warning' : 'text-danger'); ?>">
                                            <?php echo $score_percentage; ?>%
                                        </div>
                                        <p class="mb-0">
                                            You scored 
                                            <?php 
                                            if (isset($quiz_result['score']) && isset($quiz_result['max_score'])) {
                                                echo $quiz_result['score'] . ' out of ' . $quiz_result['max_score'];
                                            } else {
                                                echo round($score_percentage * count($questions) / 100) . ' out of ' . count($questions);
                                            }
                                            ?>
                                        </p>
                                        <p class="text-muted mt-2">
                                            Completed on <?php echo isset($quiz_result['completion_date']) ? date('M d, Y', strtotime($quiz_result['completion_date'])) : date('M d, Y'); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h4 class="mb-3">Question Review</h4>
                    
                    <?php foreach ($questions as $index => $question): ?>
                        <?php
                        // Debug: Log question and result data
                        error_log('Question: ' . json_encode($question));
                        error_log('Quiz Result: ' . json_encode($quiz_result));
                        
                        // Get user's answer and correct answer for this question
                        $question_id = $question['question_id'];
                        $user_answer = '';
                        $is_correct = false;
                        
                        // Get the correct answer
                        $correct_answer = $question['correct_answer'];
                        
                        // Check if we have detailed results for this question
                        if (isset($quiz_result['question_results']) && isset($quiz_result['question_results'][$question_id])) {
                            $result_data = $quiz_result['question_results'][$question_id];
                            $user_answer = $result_data['user_answer'];
                            $is_correct = $result_data['is_correct'];
                            error_log('Found in question_results: ' . $user_answer . ', is_correct: ' . ($is_correct ? 'true' : 'false'));
                        } elseif (isset($quiz_result['answers']) && isset($quiz_result['answers'][$question_id])) {
                            // For older result format
                            $user_answer = $quiz_result['answers'][$question_id];
                            $is_correct = ($user_answer === $correct_answer);
                            error_log('Found in answers: ' . $user_answer . ', is_correct: ' . ($is_correct ? 'true' : 'false'));
                        } elseif (isset($quiz_result['user_answers']) && isset($quiz_result['user_answers'][$question_id])) {
                            // Another possible format
                            $user_answer = $quiz_result['user_answers'][$question_id];
                            $is_correct = ($user_answer === $correct_answer);
                            error_log('Found in user_answers: ' . $user_answer . ', is_correct: ' . ($is_correct ? 'true' : 'false'));
                        } else {
                            // Try to get the answer from the result_id parameter
                            // This is a special case for when we're viewing a quiz that was just completed
                            $filter = "quiz_id=eq." . $quiz_id . "&user_id=eq." . $_SESSION['user_id'] . "&order=completion_date.desc&limit=1";
                            $latest_results = supabaseRequest("quiz_results?$filter", 'GET', null, true);
                            
                            if (!empty($latest_results)) {
                                $latest_result = $latest_results[0];
                                if (isset($latest_result['answers']) && isset($latest_result['answers'][$question_id])) {
                                    $user_answer = $latest_result['answers'][$question_id];
                                    $is_correct = ($user_answer === $correct_answer);
                                    error_log('Found in latest_result answers: ' . $user_answer);
                                }
                            }
                            
                            // If we still can't find the answer, log the issue
                            if (empty($user_answer)) {
                                error_log('Could not find answer for question ID: ' . $question_id);
                                error_log('Available keys in quiz_result: ' . implode(', ', array_keys($quiz_result)));
                            }
                        }
                        
                        // Debug the question type
                        error_log('Question type check: ' . json_encode($question));
                        
                        // Determine if this is a text input question
                        // Method 1: Check for answer_type field
                        $isTextInput = isset($question['answer_type']) && $question['answer_type'] === 'text';
                        
                        // Method 2: Check if the correct answer is not a single letter (A, B, C, D, E)
                        if (!$isTextInput && isset($correct_answer)) {
                            // If correct_answer is not a single letter (A-E), it's likely a text input question
                            if (!preg_match('/^[A-E]$/', $correct_answer)) {
                                $isTextInput = true;
                                error_log('Detected as text input based on correct_answer: ' . $correct_answer);
                            }
                        }
                        
                        // Method 3: Check if options are not set
                        if (!$isTextInput) {
                            $hasOptions = isset($question['option_a']) && !empty($question['option_a']);
                            if (!$hasOptions) {
                                $isTextInput = true;
                                error_log('Detected as text input based on missing options');
                            }
                        }
                        ?>
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">
                                    Question <?php echo $index + 1; ?>: 
                                    <?php echo htmlspecialchars($question['question_text']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                
                                <!-- All questions (both text input and multiple choice) -->
                                <div class="mt-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="card border-secondary mb-3">
                                                <div class="card-header bg-light">Your Answer</div>
                                                <div class="card-body">
                                                    <?php if ($isTextInput): ?>
                                                        <!-- Text input display -->
                                                        <p class="card-text <?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                                                            <?php if (empty($user_answer)): ?>
                                                                <em>No answer provided</em>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($user_answer); ?>
                                                                <?php if ($is_correct): ?>
                                                                    <i class="bi bi-check-circle-fill text-success ms-2"></i>
                                                                <?php else: ?>
                                                                    <i class="bi bi-x-circle-fill text-danger ms-2"></i>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    <?php else: ?>
                                                        <!-- Multiple choice display -->
                                                        <?php
                                                        $options = [
                                                            'A' => $question['option_a'] ?? '',
                                                            'B' => $question['option_b'] ?? '',
                                                            'C' => $question['option_c'] ?? '',
                                                            'D' => $question['option_d'] ?? ''
                                                        ];
                                                        
                                                        if (isset($question['option_e']) && !empty($question['option_e'])) {
                                                            $options['E'] = $question['option_e'];
                                                        }
                                                        
                                                        if (empty($user_answer)): ?>
                                                            <p class="card-text text-muted"><em>No answer provided</em></p>
                                                        <?php else: ?>
                                                            <p class="card-text <?php echo $is_correct ? 'text-success' : 'text-danger'; ?>">
                                                                <?php if (isset($options[$user_answer])): ?>
                                                                    <strong><?php echo $user_answer; ?>.</strong> 
                                                                    <?php echo htmlspecialchars($options[$user_answer]); ?>
                                                                <?php else: ?>
                                                                    <?php echo htmlspecialchars($user_answer); ?>
                                                                <?php endif; ?>
                                                                <?php if ($is_correct): ?>
                                                                    <i class="bi bi-check-circle-fill text-success ms-2"></i>
                                                                <?php else: ?>
                                                                    <i class="bi bi-x-circle-fill text-danger ms-2"></i>
                                                                <?php endif; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card border-success mb-3">
                                                <div class="card-header bg-light">Correct Answer</div>
                                                <div class="card-body">
                                                    <?php if ($isTextInput): ?>
                                                        <!-- Text input correct answer -->
                                                        <p class="card-text text-success">
                                                            <?php echo htmlspecialchars($correct_answer); ?>
                                                            <i class="bi bi-check-circle-fill text-success ms-2"></i>
                                                        </p>
                                                    <?php else: ?>
                                                        <!-- Multiple choice correct answer -->
                                                        <p class="card-text text-success">
                                                            <?php if (isset($options[$correct_answer])): ?>
                                                                <strong><?php echo $correct_answer; ?>.</strong> 
                                                                <?php echo htmlspecialchars($options[$correct_answer]); ?>
                                                            <?php else: ?>
                                                                <?php echo htmlspecialchars($correct_answer); ?>
                                                            <?php endif; ?>
                                                            <i class="bi bi-check-circle-fill text-success ms-2"></i>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($question['explanation'])): ?>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <h6 class="mb-2"><i class="bi bi-lightbulb-fill text-warning me-2"></i>Explanation:</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($question['explanation']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="progress.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i> Back to Progress
                        </a>
                        <a href="quiz.php?subject=<?php echo $subject_key; ?>&level=<?php echo $level_key; ?>" class="btn btn-primary">
                            <i class="bi bi-list-check me-2"></i> Take Another Quiz
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 Academic Ease. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
