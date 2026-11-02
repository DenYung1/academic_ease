<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/quiz_bridge.php'; // Include the quiz bridge for file-based quizzes

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session information
error_log('Progress Fixed - Session ID: ' . session_id());
error_log('Progress Fixed - SESSION: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Debug: Log the user ID we're looking for
    error_log('Progress - Looking for user_id: ' . $_SESSION['user_id']);
    
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the result of the user query
    error_log('Progress - User query result: ' . json_encode($users));
    
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
    
    // Get study sessions using direct request
    $endpoint = 'study_sessions?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=start_time.desc';
    $study_sessions = supabaseRequest($endpoint, 'GET', null, true);
    
    // Get quiz results from both sources
    // 1. Get results from file storage
    $file_quiz_results = getQuizResultsForUser($_SESSION['user_id']); // Using the function from quiz_storage.php
    
    // 2. Get results from Supabase
    $endpoint = 'quiz_results?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=completion_date.desc';
    $db_quiz_results = supabaseRequest($endpoint, 'GET', null, true) ?: [];
    
    // 3. Format file results to match database structure
    $formatted_file_results = [];
    foreach ($file_quiz_results as $result) {
        // Get the quiz to get subject and level
        $quiz = getQuizById($result['quiz_id']);
        if ($quiz) {
            $formatted_file_results[] = [
                'user_id' => $result['user_id'],
                'quiz_id' => $result['quiz_id'],
                'score' => $result['score'],
                'max_score' => $result['max_score'],
                'score_percentage' => $result['percentage'],
                'completion_date' => $result['completion_date'],
                'subject' => $quiz['subject'],
                'level' => $quiz['level']
            ];
        }
    }
    
    // 4. Merge results
    $quiz_results = array_merge($formatted_file_results, $db_quiz_results);
    
    // 5. Sort by completion date (newest first)
    usort($quiz_results, function($a, $b) {
        return strtotime($b['completion_date']) - strtotime($a['completion_date']);
    });
    
    // Get subject progress data from actual quiz results
    $subject_progress = [];
    
    // Initialize with zero progress for all subjects
    $all_subjects = [
        'Mathematics' => 0,
        'Science' => 0,
        'English' => 0,
        'Social Studies' => 0,
        'ICT' => 0,
        'Ghanaian Language' => 0,
        'Religious Studies' => 0,
        'Creative Arts' => 0
    ];
    
    // Track attempts per subject
    $subject_attempts = [];
    
    // Calculate progress based on actual quiz results
    if (is_array($quiz_results) && !empty($quiz_results)) {
        foreach ($quiz_results as $result) {
            if (isset($result['subject']) && isset($result['score_percentage'])) {
                $subject = $result['subject'];
                
                // Initialize if not set
                if (!isset($subject_progress[$subject])) {
                    $subject_progress[$subject] = 0;
                    $subject_attempts[$subject] = 0;
                }
                
                // Add to total score
                $subject_progress[$subject] += $result['score_percentage'];
                $subject_attempts[$subject]++;
            }
        }
        
        // Calculate averages
        foreach ($subject_progress as $subject => $total) {
            if ($subject_attempts[$subject] > 0) {
                $subject_progress[$subject] = round($total / $subject_attempts[$subject]);
            }
        }
    }
    
    // Merge with default subjects
    $subject_progress = array_merge($all_subjects, $subject_progress);
    
    // Calculate weekly activity data based on actual study sessions
    $weekly_activity = [
        'Monday' => 0,
        'Tuesday' => 0,
        'Wednesday' => 0,
        'Thursday' => 0,
        'Friday' => 0,
        'Saturday' => 0,
        'Sunday' => 0
    ];
    
    // Get current week's study sessions
    $week_start = strtotime('monday this week');
    $week_end = strtotime('sunday this week');
    
    if (is_array($study_sessions)) {
        foreach ($study_sessions as $session) {
            if (isset($session['start_time']) && isset($session['duration_minutes'])) {
                $session_time = strtotime($session['start_time']);
                
                // Check if session is from current week
                if ($session_time >= $week_start && $session_time <= $week_end) {
                    $day = date('l', $session_time);
                    $hours = $session['duration_minutes'] / 60;
                    $weekly_activity[$day] += $hours;
                }
            }
        }
    }
    
    // Round to 1 decimal place
    foreach ($weekly_activity as $day => $hours) {
        $weekly_activity[$day] = round($hours, 1);
    }
    
    // Calculate total study time
    $total_study_time = 0;
    foreach ($study_sessions as $session) {
        $total_study_time += $session['duration_minutes'];
    }
    
    // Calculate average quiz score
    $total_score = 0;
    $quiz_count = 0;
    
    // Make sure quiz_results is an array
    if (is_array($quiz_results)) {
        foreach ($quiz_results as $result) {
            // Make sure each result is an array and has the expected key
            if (is_array($result) && isset($result['score_percentage']) && is_numeric($result['score_percentage'])) {
                $total_score += $result['score_percentage'];
                $quiz_count++;
            }
        }
    }
    
    $average_quiz_score = $quiz_count > 0 ? round($total_score / $quiz_count) : 0;
    
} catch (Exception $e) {
    error_log('Progress Error: ' . $e->getMessage());
    $error = 'An error occurred while loading your progress data. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracking - AcademicEase</title>
    
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
    
    /* Mobile Navigation Fixes */
    @media (max-width: 991.98px) {
        /* Fix dropdown menu positioning on mobile */
        .dropdown-menu {
            position: absolute !important;
            will-change: transform !important;
        }
        
        /* Ensure dropdown items are properly spaced and visible */
        .dropdown-item {
            padding: 0.5rem 1rem;
            clear: both;
            white-space: nowrap;
        }
        
        /* Fix for user dropdown on mobile */
        #mobileUserDropdown + .dropdown-menu {
            min-width: 200px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        /* Ensure navbar-collapse takes full width on mobile */
        .navbar-collapse {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        /* Add some spacing to the collapsed navbar */
        .navbar-nav {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
    }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            <!-- Mobile: User Account Button (Outside Collapse) -->
            <div class="d-flex d-lg-none ms-auto me-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="mobileUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileUserDropdown">
                        <li class="dropdown-item-text">
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Hamburger Menu Button -->
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
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="progress.php">Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                
                <!-- Desktop: User Account Dropdown (Inside Collapse) -->
                <div class="d-none d-lg-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Progress Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Your Learning Progress</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-clock-history text-primary fs-1 mb-2"></i>
                                        <h5 class="card-title">Total Study Time</h5>
                                        <h2 class="mb-0"><?php echo round($total_study_time / 60, 1); ?> hrs</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-journal-check text-primary fs-1 mb-2"></i>
                                        <h5 class="card-title">Quizzes Completed</h5>
                                        <h2 class="mb-0"><?php echo count($quiz_results); ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-trophy text-primary fs-1 mb-2"></i>
                                        <h5 class="card-title">Average Score</h5>
                                        <h2 class="mb-0"><?php echo $average_quiz_score; ?>%</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body text-center">
                                        <i class="bi bi-lightning-charge text-primary fs-1 mb-2"></i>
                                        <h5 class="card-title">Current Streak</h5>
                                        <?php
                                        // Calculate streak based on actual study sessions
                                        $streak = 0;
                                        $current_date = time();
                                        $yesterday = strtotime('-1 day', $current_date);
                                        $yesterday_date = date('Y-m-d', $yesterday);
                                        
                                        // Check if student has studied today or yesterday
                                        $has_recent_activity = false;
                                        
                                        if (is_array($study_sessions) && !empty($study_sessions)) {
                                            // Sort sessions by date (newest first)
                                            usort($study_sessions, function($a, $b) {
                                                return strtotime($b['start_time']) - strtotime($a['start_time']);
                                            });
                                            
                                            // Get the most recent session date
                                            $last_session_date = date('Y-m-d', strtotime($study_sessions[0]['start_time']));
                                            $today_date = date('Y-m-d');
                                            
                                            // Check if studied today or yesterday
                                            if ($last_session_date === $today_date || $last_session_date === $yesterday_date) {
                                                $has_recent_activity = true;
                                            }
                                            
                                            // Calculate streak
                                            if ($has_recent_activity) {
                                                $streak = 1; // Start with 1 for today/yesterday
                                                $date_to_check = strtotime('-2 days'); // Start checking from 2 days ago
                                                
                                                // Check consecutive days (up to 30 days back)
                                                for ($i = 2; $i <= 30; $i++) {
                                                    $check_date = date('Y-m-d', $date_to_check);
                                                    $found_session = false;
                                                    
                                                    // Look for sessions on this date
                                                    foreach ($study_sessions as $session) {
                                                        $session_date = date('Y-m-d', strtotime($session['start_time']));
                                                        if ($session_date === $check_date) {
                                                            $found_session = true;
                                                            break;
                                                        }
                                                    }
                                                    
                                                    if ($found_session) {
                                                        $streak++;
                                                        $date_to_check = strtotime('-1 day', $date_to_check);
                                                    } else {
                                                        break; // Streak ends
                                                    }
                                                }
                                            }
                                        }
                                        ?>
                                        <h2 class="mb-0"><?php echo $streak; ?> day<?php echo $streak !== 1 ? 's' : ''; ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Subject Progress</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="subject-progress-chart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Weekly Activity</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weekly-activity-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Recent Quiz Results</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($quiz_results)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Topic</th>
                                            <th>Score</th>
                                            <th>Time Taken</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($quiz_results, 0, 5) as $result): ?>
                                        <tr>
                                            <td><?php echo isset($result['completion_date']) ? date('M d, Y', strtotime($result['completion_date'])) : 'Unknown date'; ?></td>
                                            <?php
                                            // Get quiz title and subject from file storage or database
                                            $quiz_title = 'Unknown Quiz';
                                            $quiz_subject = isset($result['subject']) ? htmlspecialchars($result['subject']) : 'General';
                                            
                                            if (isset($result['quiz_id'])) {
                                                // Try to get quiz from file storage
                                                $quiz = getQuizById($result['quiz_id']);
                                                
                                                if ($quiz) {
                                                    $quiz_title = htmlspecialchars($quiz['title']);
                                                    $quiz_subject = isset($quiz['subject']) ? htmlspecialchars($quiz['subject']) : $quiz_subject;
                                                } else {
                                                    // Try to get from database
                                                    $filter = "quiz_id=eq." . $result['quiz_id'];
                                                    $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
                                                    
                                                    if (!empty($quizzes)) {
                                                        $quiz_title = htmlspecialchars($quizzes[0]['title']);
                                                        $quiz_subject = isset($quizzes[0]['subject']) ? htmlspecialchars($quizzes[0]['subject']) : $quiz_subject;
                                                    }
                                                }
                                            }
                                            ?>
                                            <td><?php echo $quiz_subject; ?></td>
                                            <td><?php echo $quiz_title; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                        <?php 
                                                        // Get score from the appropriate field based on source
                                                        $score = 0;
                                                        if (isset($result['percentage'])) {
                                                            $score = $result['percentage'];
                                                        } elseif (isset($result['score_percentage'])) {
                                                            $score = $result['score_percentage'];
                                                        } elseif (isset($result['score']) && isset($result['max_score']) && $result['max_score'] > 0) {
                                                            $score = round(($result['score'] / $result['max_score']) * 100);
                                                        }
                                                        
                                                        $colorClass = $score >= 70 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
                                                        ?>
                                                        <div class="progress-bar bg-<?php echo $colorClass; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $score; ?>%;" 
                                                             aria-valuenow="<?php echo $score; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100"></div>
                                                    </div>
                                                    <span><?php echo $score; ?>%</span>
                                                </div>
                                            </td>
                                            <td><?php echo isset($result['time_taken']) ? $result['time_taken'] : '0'; ?> min</td>
                                            <td>
                                                <a href="quiz-review.php?quiz_id=<?php echo isset($result['quiz_id']) ? $result['quiz_id'] : '0'; ?>&result_id=<?php echo isset($result['result_id']) ? $result['result_id'] : '0'; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Review
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($quiz_results) > 5): ?>
                                <div class="text-center mt-3">
                                    <a href="all-quizzes.php" class="btn btn-outline-primary">View All Quiz Results</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> You haven't taken any quizzes yet. Take a quiz to track your progress!
                            </div>
                            <div class="text-center">
                                <a href="quiz.php" class="btn btn-primary">Start a Quiz</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Study Sessions History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($study_sessions)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Subject</th>
                                            <th>Topic</th>
                                            <th>Duration</th>
                                            <th>Resources Used</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($study_sessions, 0, 5) as $session): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($session['start_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($session['subject']); ?></td>
                                            <td><?php echo htmlspecialchars($session['topic']); ?></td>
                                            <td><?php echo $session['duration_minutes']; ?> min</td>
                                            <td><?php echo $session['resources_count']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($study_sessions) > 5): ?>
                                <div class="text-center mt-3">
                                    <a href="all-sessions.php" class="btn btn-outline-primary">View All Study Sessions</a>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> You haven't recorded any study sessions yet. Start studying to track your progress!
                            </div>
                            <div class="text-center">
                                <a href="study.php" class="btn btn-primary">Start Studying</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Subject Progress Chart
            const subjectProgressCtx = document.getElementById('subject-progress-chart').getContext('2d');
            const subjectProgressChart = new Chart(subjectProgressCtx, {
                type: 'radar',
                data: {
                    labels: ['Mathematics', 'Science', 'English', 'Social Studies', 'ICT'],
                    datasets: [{
                        label: 'Your Progress',
                        data: [
                            <?php echo $subject_progress['Mathematics']; ?>,
                            <?php echo $subject_progress['Science']; ?>,
                            <?php echo $subject_progress['English']; ?>,
                            <?php echo $subject_progress['Social Studies']; ?>,
                            <?php echo $subject_progress['ICT']; ?>
                        ],
                        backgroundColor: 'rgba(26, 115, 232, 0.2)',
                        borderColor: 'rgba(26, 115, 232, 1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(26, 115, 232, 1)',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: 'rgba(26, 115, 232, 1)'
                    }]
                },
                options: {
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 20
                            }
                        }
                    }
                }
            });
            
            // Weekly Activity Chart
            const weeklyActivityCtx = document.getElementById('weekly-activity-chart').getContext('2d');
            const weeklyActivityChart = new Chart(weeklyActivityCtx, {
                type: 'bar',
                data: {
                    labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                    datasets: [{
                        label: 'Study Hours',
                        data: [
                            <?php echo $weekly_activity['Monday']; ?>,
                            <?php echo $weekly_activity['Tuesday']; ?>,
                            <?php echo $weekly_activity['Wednesday']; ?>,
                            <?php echo $weekly_activity['Thursday']; ?>,
                            <?php echo $weekly_activity['Friday']; ?>,
                            <?php echo $weekly_activity['Saturday']; ?>,
                            <?php echo $weekly_activity['Sunday']; ?>
                        ],
                        backgroundColor: 'rgba(52, 168, 83, 0.7)'
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
