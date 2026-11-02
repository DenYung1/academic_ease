<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/session_helper.php';

// Use the session helper to check login status and handle timeouts
check_user_login();

// Debug: Log session information
error_log('Dashboard - Session ID: ' . session_id());
error_log('Dashboard - SESSION: ' . json_encode($_SESSION));

// Get user data
try {
    // Debug: Log the user ID we're looking for
    error_log('Dashboard - Looking for user_id: ' . $_SESSION['user_id']);
    
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the result of the user query
    error_log('Dashboard - User query result: ' . json_encode($users));
    
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

    // Check if user has a valid subscription
    // If the user has been renewed by admin, automatically update their status
    if (isset($user['admin_renewed']) && $user['admin_renewed'] == true && $subscription_status !== 'active') {
        // Update user to active status
        $updateData = [
            'subscription_status' => 'active',
            'subscription_start_date' => date('c'),
            'subscription_end_date' => date('c', strtotime('+30 days'))
        ];
        
        $filter = "user_id=eq." . urlencode($_SESSION['user_id']);
        supabaseRequest("users?$filter", 'PATCH', $updateData, true);
        error_log("Updated admin-renewed user {$_SESSION['user_id']} to active status");
        
        // Update local variable to reflect the change
        $subscription_status = 'active';
    }
    
    // Now check subscription status
    if ($subscription_status === 'pending') {
        // User has selected a plan but payment is not confirmed
        header('Location: subscribe-direct.php?pending=1');
        exit;
    } 
    // Check for expired accounts - redirect them to subscription page
    else if ($subscription_status === 'expired') {
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    // Check for trial accounts that have expired
    else if ($subscription_status === 'trial' && $subscription_end_date < $current_time) {
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    // Check for accounts with no subscription status
    else if (empty($subscription_status)) {
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    // Check for accounts that are not active, renewed, or admin-renewed
    else if ($subscription_status !== 'active' && 
             $subscription_status !== 'renewed' &&
             $subscription_status !== 'trial' &&
             !isset($user['admin_renewed'])) {
        
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }

    // For paid plans, verify payment - but skip for admin-renewed accounts
    if ($subscription_status === 'active' && $user['subscription_plan'] !== 'trial' && !isset($user['admin_renewed'])) {
        require_once '../includes/payment_verification.php';
        
        if (!verify_payment($user['user_id'], $user['subscription_plan'])) {
            // Payment not verified, but instead of redirecting, mark as admin-renewed
            $updateData = [
                'admin_renewed' => true,
                'subscription_status' => 'active',
                'subscription_start_date' => date('c'),
                'subscription_end_date' => date('c', strtotime('+30 days'))
            ];
            
            $filter = "user_id=eq." . urlencode($_SESSION['user_id']);
            supabaseRequest("users?$filter", 'PATCH', $updateData, true);
            error_log("Auto-activated account for user {$_SESSION['user_id']} during payment verification");
        }
    }

    // Calculate days left in subscription
    $days_left = 0;
    if ($subscription_end_date > $current_time) {
        $days_left = ceil(($subscription_end_date - $current_time) / (60 * 60 * 24));
    }
    
    // Get subscription plan name for display
    $subscription_plan_name = 'Unknown';
    switch ($user['subscription_plan']) {
        case 'trial':
            $subscription_plan_name = 'Free Trial';
            break;
        case 'weekly':
            $subscription_plan_name = 'Weekly Plan';
            break;
        case 'monthly':
            $subscription_plan_name = 'Monthly Plan';
            break;
        case 'termly':
            $subscription_plan_name = 'Termly Plan';
            break;
    }
    
    // Check if subscription has expired and update status if needed
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        ($subscription_status === 'active' && $subscription_end_date < $current_time)) {
        // Update user status to expired
        $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
        $updateData = [
            'subscription_status' => 'expired'
        ];
        supabaseRequest($endpoint, 'PATCH', $updateData, true);
        
        // Update local user data
        $user['subscription_status'] = 'expired';
        $subscription_status = 'expired';
        $days_left = 0;
    }
    
    // Check if user has selected a plan but not completed payment
    // This handles existing users who have incomplete subscriptions
    if ($subscription_status === 'trial' && empty($user['subscription_plan'])) {
        // User has a trial but hasn't selected a paid plan yet
        // Redirect to subscription page to complete their subscription
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    
    // Get recent study sessions using direct request
    $endpoint = 'study_sessions?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=start_time.desc&limit=5';
    $recent_sessions = supabaseRequest($endpoint, 'GET', null, true);
    
    // Handle potential errors or empty results
    if (!is_array($recent_sessions)) {
        $recent_sessions = [];
        error_log('Error retrieving study sessions: ' . json_encode($recent_sessions));
    }
    
    // Get recent conversations using direct request
    $endpoint = 'conversations?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=start_time.desc&limit=5';
    $recent_conversations = supabaseRequest($endpoint, 'GET', null, true);
    
    // Handle potential errors or empty results
    if (!is_array($recent_conversations)) {
        $recent_conversations = [];
        error_log('Error retrieving conversations: ' . json_encode($recent_conversations));
    }
    
    // Calculate usage statistics
    $total_study_time = 0;
    $total_questions = 0;
    
    // Only process if we have valid study sessions
    if (is_array($recent_sessions)) {
        foreach ($recent_sessions as $session) {
            if (isset($session['duration_minutes']) && is_numeric($session['duration_minutes'])) {
                $total_study_time += $session['duration_minutes'];
            }
        }
    }
    
    // Only process if we have valid conversations
    if (is_array($recent_conversations)) {
        foreach ($recent_conversations as $conversation) {
            // Make sure conversation_id exists
            if (isset($conversation['conversation_id'])) {
                // Get messages count for each conversation using direct request
                $endpoint = 'messages?conversation_id=eq.' . urlencode($conversation['conversation_id']) . '&sender=eq.user';
                $messages = supabaseRequest($endpoint, 'GET', null, true);
                
                // Make sure messages is an array before counting
                if (is_array($messages)) {
                    $total_questions += count($messages);
                }
            }
        }
    }
    
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
    
    // Get quiz results using direct request
    $endpoint = 'quiz_results?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=completion_date.desc';
    $quiz_results = supabaseRequest($endpoint, 'GET', null, true);
    
    // Track attempts per subject
    $subject_attempts = [];
    $subjects_studied = 0;
    
    // Calculate progress based on actual quiz results
    if (is_array($quiz_results) && !empty($quiz_results)) {
        foreach ($quiz_results as $result) {
            if (isset($result['subject']) && isset($result['score_percentage'])) {
                $subject = $result['subject'];
                
                // Initialize if not set
                if (!isset($subject_progress[$subject])) {
                    $subject_progress[$subject] = 0;
                    $subject_attempts[$subject] = 0;
                    $subjects_studied++; // Count unique subjects
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
    
    // Calculate average progress across all subjects
    $total_progress = 0;
    $subject_count = 0;
    
    foreach ($subject_progress as $progress) {
        if ($progress > 0) {
            $total_progress += $progress;
            $subject_count++;
        }
    }
    
    $average_progress = $subject_count > 0 ? round($total_progress / $subject_count) : 0;
    
} catch (Exception $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    $error = 'An error occurred while loading your dashboard. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
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
</head>
<body>
    <!-- Mobile-Optimized Navigation -->
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
                        <li><a class="dropdown-item" href="subscribe-direct.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
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
                        <a class="nav-link" href="progress.php">Progress</a>
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
                            <li><a class="dropdown-item" href="subscribe-direct.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="container py-4">
        <!-- Personalized Welcome Message -->
        <div class="welcome-container" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" data-user-streak="<?php echo isset($streak_days) ? $streak_days : 0; ?>"></div>
        <div class="row mb-4">
            <div class="col-md-8">
                <h1 class="mb-0">Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                <?php if ($subscription_status === 'trial'): ?>
                    <div class="alert alert-info">
                        <strong>Free Trial Active:</strong> You have <?php echo $days_left; ?> day<?php echo $days_left !== 1 ? 's' : ''; ?> left in your free trial. Your trial ends on <?php echo date('j F Y, g:i a', $subscription_end_date); ?>.
                        <a href="subscribe-direct.php" class="alert-link">Upgrade now</a> to continue using all features.
                    </div>
                <?php elseif ($subscription_status === 'active'): ?>
                    <div class="alert alert-success">
                        <strong><?php echo htmlspecialchars($subscription_plan_name); ?> Active:</strong> You have <?php echo $days_left; ?> day<?php echo $days_left !== 1 ? 's' : ''; ?> left in your subscription. Your subscription ends on <?php echo date('j F Y', $subscription_end_date); ?>.
                        <?php if ($days_left <= 3): ?>
                        <a href="subscribe-direct.php" class="alert-link">Renew now</a> to avoid interruption.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">
                        Your subscription is active until <?php echo date('j F Y', $subscription_end_date); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="ai-assistant.php" class="btn btn-primary"><i class="bi bi-chat-dots-fill me-2"></i>Ask AI Assistant</a>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="section-divider">
            <h3><i class="bi bi-bar-chart-fill"></i> Your Statistics</h3>
        </div>
        <div class="row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card card-emphasis hover-lift h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="bi bi-clock me-2"></i>Total Study Time</h5>
                        <p class="display-4 mb-0"><?php echo round($total_study_time / 60, 1); ?></p>
                        <p class="text-muted">Hours</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card card-emphasis hover-lift h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title"><i class="bi bi-question-circle me-2"></i>Questions Asked</h5>
                        <p class="display-4 mb-0"><?php echo $total_questions; ?></p>
                        <p class="text-muted">Questions</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <div class="dashboard-stats">
                            <div class="dashboard-stats-icon">
                                <i class="bi bi-book"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo $subjects_studied; ?></h5>
                                <p class="text-muted mb-0">Subjects Studied</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card dashboard-card h-100">
                    <div class="card-body">
                        <div class="dashboard-stats">
                            <div class="dashboard-stats-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div>
                                <h5 class="mb-0"><?php echo $average_progress; ?>%</h5>
                                <p class="text-muted mb-0">Average Progress</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
                
              <!-- Weekly Study Goals -->
        <div class="section-divider">
            <h3><i class="bi bi-flag-fill"></i> Weekly Study Goals</h3>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Weekly Study Goals</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($study_goals)): ?>
                            <p class="text-muted mb-0">You haven't set any study goals yet. Set goals to track your progress!</p>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($study_goals as $goal): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card hover-lift">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($goal['subject']); ?></h6>
                                                <div class="progress mb-2">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $goal['progress']; ?>%" aria-valuenow="<?php echo $goal['progress']; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $goal['progress']; ?>%</div>
                                                </div>
                                                <p class="card-text small text-muted"><?php echo htmlspecialchars($goal['description']); ?></p>
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
        
        <!-- Personalized Recommendations -->
        <div class="section-divider">
            <h3><i class="bi bi-lightbulb-fill"></i> Recommended For You</h3>
        </div>
        <div class="row mb-4">
            <div class="col-12">
                <div class="recommendations-container"></div>
            </div>
        </div>

        <!-- Main Dashboard Content -->
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Recent Activity -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="activity.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_sessions) && empty($recent_conversations)): ?>
                            <p class="text-center text-muted my-4">No recent activity. Start studying or ask the AI assistant a question!</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php 
                                $combined_activities = [];
                                
                                // Add study sessions
                                foreach ($recent_sessions as $session) {
                                    $combined_activities[] = [
                                        'type' => 'study',
                                        'time' => strtotime($session['start_time']),
                                        'data' => $session
                                    ];
                                }
                                
                                // Add conversations
                                foreach ($recent_conversations as $conversation) {
                                    $combined_activities[] = [
                                        'type' => 'conversation',
                                        'time' => strtotime($conversation['start_time']),
                                        'data' => $conversation
                                    ];
                                }
                                
                                // Sort by time (newest first)
                                usort($combined_activities, function($a, $b) {
                                    return $b['time'] - $a['time'];
                                });
                                
                                // Display activities (limit to 5)
                                $count = 0;
                                foreach ($combined_activities as $activity) {
                                    if ($count >= 5) break;
                                    
                                    if ($activity['type'] === 'study') {
                                        $session = $activity['data'];
                                        echo '<div class="list-group-item border-0 px-0">';
                                        echo '<div class="d-flex justify-content-between align-items-center">';
                                        echo '<div>';
                                        echo '<h6 class="mb-1">Studied ' . htmlspecialchars($session['subject']) . '</h6>';
                                        echo '<p class="text-muted mb-0 small">Topic: ' . htmlspecialchars($session['topic']) . '</p>';
                                        echo '</div>';
                                        echo '<div class="text-end">';
                                        echo '<span class="badge bg-primary rounded-pill">' . $session['duration_minutes'] . ' mins</span>';
                                        echo '<p class="text-muted mb-0 small">' . date('j M, g:i a', strtotime($session['start_time'])) . '</p>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    } else {
                                        $conversation = $activity['data'];
                                        echo '<div class="list-group-item border-0 px-0">';
                                        echo '<div class="d-flex justify-content-between align-items-center">';
                                        echo '<div>';
                                        echo '<h6 class="mb-1">Asked AI Assistant</h6>';
                                        echo '<p class="text-muted mb-0 small">Subject: ' . htmlspecialchars($conversation['subject']) . '</p>';
                                        echo '</div>';
                                        echo '<div class="text-end">';
                                        echo '<a href="ai-assistant.php?conversation=' . $conversation['conversation_id'] . '" class="btn btn-sm btn-outline-secondary">Continue</a>';
                                        echo '<p class="text-muted mb-0 small">' . date('j M, g:i a', strtotime($conversation['start_time'])) . '</p>';
                                        echo '</div>';
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                    
                                    $count++;
                                }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Weekly Activity Chart -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Weekly Activity</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="weekly-activity-chart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Recommended Study Materials -->
                <div class="card dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recommended Study Materials</h5>
                        <a href="study.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card study-material-card math h-100">
                                    <div class="card-header">
                                        Mathematics
                                    </div>
                                    <div class="card-body">
                                        <h6>Quadratic Equations</h6>
                                        <p class="text-muted small">Master the techniques for solving quadratic equations using factoring, completing the square, and the quadratic formula.</p>
                                        <a href="study.php?subject=mathematics&topic=quadratic-equations" class="btn btn-sm btn-outline-primary">Study Now</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card study-material-card science h-100">
                                    <div class="card-header">
                                        Science
                                    </div>
                                    <div class="card-body">
                                        <h6>Periodic Table</h6>
                                        <p class="text-muted small">Learn about the organization of elements in the periodic table and their properties.</p>
                                        <a href="study.php?subject=science&topic=periodic-table" class="btn btn-sm btn-outline-primary">Study Now</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Subject Progress -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Subject Progress</h5>
                        <a href="progress.php" class="btn btn-sm btn-outline-primary">Details</a>
                    </div>
                    <div class="card-body">
                        <canvas id="subject-progress-chart" height="250"></canvas>
                    </div>
                </div>
                
                <!-- Upcoming Exams -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Exams</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Try to get exams from database
                        $has_exams = false;
                        $exams = [];
                        
                        try {
                            // Get exams from the database
                            $endpoint = 'exams?order=exam_date.asc';
                            $exams = supabaseRequest($endpoint, 'GET', null, true);
                            $has_exams = !empty($exams);
                        } catch (Exception $e) {
                            error_log('Error fetching exams: ' . $e->getMessage());
                        }
                        
                        if ($has_exams) {
                            // Display exams from database
                            foreach ($exams as $exam) {
                                // Skip if exam is not an array or missing required fields
                                if (!is_array($exam) || !isset($exam['exam_date']) || !isset($exam['exam_name'])) {
                                    continue;
                                }
                                
                                // Make sure we have a valid date
                                $exam_date = strtotime($exam['exam_date']);
                                if ($exam_date === false) {
                                    continue;
                                }
                                
                                $days_left = ceil(($exam_date - time()) / (60 * 60 * 24));
                                $badge_class = ($days_left <= 7) ? 'bg-danger' : (($days_left <= 14) ? 'bg-warning text-dark' : 'bg-info');
                                
                                // Get subjects with fallback
                                $subjects = isset($exam['subjects']) && !empty($exam['subjects']) ? $exam['subjects'] : 'All subjects';
                                
                                echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                                echo '<div>';
                                echo '<h6 class="mb-1">' . htmlspecialchars($exam['exam_name']) . '</h6>';
                                echo '<p class="text-muted mb-0 small">' . htmlspecialchars($subjects) . '</p>';
                                echo '</div>';
                                echo '<span class="badge ' . $badge_class . '">' . $days_left . ' days left</span>';
                                echo '</div>';
                            }
                        } else {
                            // No exams in database
                            echo '<p class="text-center text-muted my-4">No upcoming exams at this time.</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Study Goals -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Weekly Study Goals</h5>
                        <a href="goals.php" class="btn btn-sm btn-outline-primary">Edit</a>
                    </div>
                    <div class="card-body">
                        <?php
                        // Set weekly goals based on student level
                        $weekly_study_goal = 300; // 5 hours in minutes
                        $weekly_questions_goal = 20;
                        $weekly_subjects_goal = 5;
                        
                        // Calculate weekly study time (only count current week)
                        $week_start = strtotime('monday this week');
                        $week_end = strtotime('sunday this week');
                        $weekly_study_time = 0;
                        
                        if (is_array($recent_sessions)) {
                            foreach ($recent_sessions as $session) {
                                if (isset($session['start_time']) && isset($session['duration_minutes'])) {
                                    $session_time = strtotime($session['start_time']);
                                    
                                    // Check if session is from current week
                                    if ($session_time >= $week_start && $session_time <= $week_end) {
                                        $weekly_study_time += $session['duration_minutes'];
                                    }
                                }
                            }
                        }
                        
                        // Calculate weekly questions (only count current week)
                        $weekly_questions = 0;
                        
                        if (is_array($recent_conversations)) {
                            foreach ($recent_conversations as $conversation) {
                                if (isset($conversation['start_time'])) {
                                    $conversation_time = strtotime($conversation['start_time']);
                                    
                                    // Check if conversation is from current week
                                    if ($conversation_time >= $week_start && $conversation_time <= $week_end) {
                                        // Get messages count for this conversation
                                        if (isset($conversation['conversation_id'])) {
                                            $endpoint = 'messages?conversation_id=eq.' . urlencode($conversation['conversation_id']) . '&sender=eq.user';
                                            $messages = supabaseRequest($endpoint, 'GET', null, true);
                                            
                                            if (is_array($messages)) {
                                                $weekly_questions += count($messages);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Calculate weekly subjects covered (only count current week)
                        $weekly_subjects = [];
                        
                        if (is_array($recent_sessions)) {
                            foreach ($recent_sessions as $session) {
                                if (isset($session['start_time']) && isset($session['subject'])) {
                                    $session_time = strtotime($session['start_time']);
                                    
                                    // Check if session is from current week
                                    if ($session_time >= $week_start && $session_time <= $week_end) {
                                        $weekly_subjects[$session['subject']] = true;
                                    }
                                }
                            }
                        }
                        
                        $weekly_subjects_count = count($weekly_subjects);
                        ?>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Weekly Study Time</span>
                                <span><?php echo $weekly_study_time; ?>/<?php echo $weekly_study_goal; ?> mins</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo min(100, ($weekly_study_time / $weekly_study_goal) * 100); ?>%" aria-valuenow="<?php echo $weekly_study_time; ?>" aria-valuemin="0" aria-valuemax="<?php echo $weekly_study_goal; ?>"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Weekly Questions Asked</span>
                                <span><?php echo $weekly_questions; ?>/<?php echo $weekly_questions_goal; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, ($weekly_questions / $weekly_questions_goal) * 100); ?>%" aria-valuenow="<?php echo $weekly_questions; ?>" aria-valuemin="0" aria-valuemax="<?php echo $weekly_questions_goal; ?>"></div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Weekly Subjects Covered</span>
                                <span><?php echo $weekly_subjects_count; ?>/<?php echo $weekly_subjects_goal; ?></span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(100, ($weekly_subjects_count / $weekly_subjects_goal) * 100); ?>%" aria-valuenow="<?php echo $weekly_subjects_count; ?>" aria-valuemin="0" aria-valuemax="<?php echo $weekly_subjects_goal; ?>"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- WhatsApp Access -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">WhatsApp Access</h5>
                    </div>
                    <div class="card-body">
                        <p>Access AcademicEase on WhatsApp for quick study assistance and support on the go.</p>
                        <div class="d-grid">
                            <a href="https://wa.me/233543584177" class="btn btn-success" target="_blank">
                                <i class="bi bi-whatsapp me-2"></i>Chat on WhatsApp
                            </a>
                        </div>
                        <div class="text-center mt-3">
                            <p class="fw-bold mb-0" style="font-size: 1.1rem; color: #25D366;"> +233 54 358 4177</p>
                        </div>
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
                    <p class="mb-0">&copy; 2025 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Embedded JavaScript to fix 404 errors -->
    <script>
    // Enhanced UI JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth scrolling to all links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add hover effects to cards
        document.querySelectorAll('.dashboard-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 15px rgba(0, 0, 0, 0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
        });
    });
    
    // Settings Panel JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Create settings button if it doesn't exist
        if (!document.querySelector('#settings-toggle')) {
            const settingsBtn = document.createElement('button');
            settingsBtn.id = 'settings-toggle';
            settingsBtn.className = 'btn btn-sm btn-outline-secondary position-fixed';
            settingsBtn.style.bottom = '20px';
            settingsBtn.style.right = '20px';
            settingsBtn.style.zIndex = '1000';
            settingsBtn.innerHTML = '<i class="bi bi-gear"></i>';
            document.body.appendChild(settingsBtn);
            
            // Create settings panel
            const settingsPanel = document.createElement('div');
            settingsPanel.id = 'settings-panel';
            settingsPanel.className = 'card position-fixed';
            settingsPanel.style.bottom = '70px';
            settingsPanel.style.right = '20px';
            settingsPanel.style.width = '300px';
            settingsPanel.style.zIndex = '1000';
            settingsPanel.style.display = 'none';
            settingsPanel.style.boxShadow = '0 0 15px rgba(0, 0, 0, 0.1)';
            
            settingsPanel.innerHTML = `
                <div class="card-header">Settings</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Theme</label>
                        <select class="form-select" id="theme-selector">
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Font Size</label>
                        <div class="btn-group w-100">
                            <button class="btn btn-outline-secondary" id="font-smaller">A-</button>
                            <button class="btn btn-outline-secondary" id="font-reset">Reset</button>
                            <button class="btn btn-outline-secondary" id="font-larger">A+</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(settingsPanel);
            
            // Toggle settings panel
            settingsBtn.addEventListener('click', function() {
                if (settingsPanel.style.display === 'none') {
                    settingsPanel.style.display = 'block';
                } else {
                    settingsPanel.style.display = 'none';
                }
            });
            
            // Theme selector
            const themeSelector = document.getElementById('theme-selector');
            themeSelector.addEventListener('change', function() {
                if (this.value === 'dark') {
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-theme');
                    localStorage.setItem('theme', 'light');
                }
            });
            
            // Font size controls
            document.getElementById('font-smaller').addEventListener('click', function() {
                changeFontSize(-1);
            });
            
            document.getElementById('font-reset').addEventListener('click', function() {
                resetFontSize();
            });
            
            document.getElementById('font-larger').addEventListener('click', function() {
                changeFontSize(1);
            });
            
            // Load saved settings
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                themeSelector.value = 'dark';
            }
            
            const savedFontSize = localStorage.getItem('fontSize');
            if (savedFontSize) {
                document.documentElement.style.fontSize = savedFontSize + 'px';
            }
        }
        
        // Font size functions
        function changeFontSize(step) {
            const currentSize = parseInt(window.getComputedStyle(document.documentElement).fontSize);
            const newSize = currentSize + step;
            document.documentElement.style.fontSize = newSize + 'px';
            localStorage.setItem('fontSize', newSize);
        }
        
        function resetFontSize() {
            document.documentElement.style.fontSize = '16px';
            localStorage.removeItem('fontSize');
        }
    });
    </script>
    
    <script>
        // Initialize charts
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
                            <?php 
                            // Calculate study hours for each day of the week
                            $week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            $daily_hours = [];
                            
                            // Initialize all days with 0 hours
                            foreach ($week_days as $day) {
                                $daily_hours[$day] = 0;
                            }
                            
                            // Calculate hours from actual study sessions
                            if (is_array($recent_sessions)) {
                                foreach ($recent_sessions as $session) {
                                    if (isset($session['start_time']) && isset($session['duration_minutes'])) {
                                        $session_time = strtotime($session['start_time']);
                                        
                                        // Check if session is from current week
                                        if ($session_time >= $week_start && $session_time <= $week_end) {
                                            $day = date('l', $session_time);
                                            $hours = $session['duration_minutes'] / 60;
                                            $daily_hours[$day] += $hours;
                                        }
                                    }
                                }
                            }
                            
                            // Output the values for the chart
                            $output = [];
                            foreach ($week_days as $day) {
                                $output[] = round($daily_hours[$day], 1);
                            }
                            echo implode(', ', $output);
                            ?>
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
