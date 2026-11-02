<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session information
error_log('Quizzes - Session ID: ' . session_id());
error_log('Quizzes - SESSION: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Debug: Log the user ID we're looking for
    error_log('Quizzes - Looking for user_id: ' . $_SESSION['user_id']);
    
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the result of the user query
    error_log('Quizzes - User query result: ' . json_encode($users));
    
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
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    
    // Get filter parameters
    $level_filter = isset($_GET['level']) ? $_GET['level'] : 'all';
    $subject_filter = isset($_GET['subject']) ? $_GET['subject'] : 'all';
    
    // Build query based on filters
    $endpoint = 'quizzes';
    $query_params = [];
    
    // Add level filter if not 'all'
    if ($level_filter !== 'all') {
        $query_params[] = 'level=eq.' . urlencode($level_filter);
    }
    
    // Add subject filter if not 'all'
    if ($subject_filter !== 'all') {
        $query_params[] = 'subject=eq.' . urlencode($subject_filter);
    }
    
    // Add ordering
    $query_params[] = 'order=subject.asc,level.asc';
    
    // Combine query parameters
    if (!empty($query_params)) {
        $endpoint .= '?' . implode('&', $query_params);
    }
    
    // Get available quizzes using direct request
    $quizzes = supabaseRequest($endpoint, 'GET', null, true);
    
    // Get user's quiz history
    $endpoint = 'quiz_results?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=completion_date.desc';
    $quiz_history = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the structure of the first quiz
    if (!empty($quizzes)) {
        error_log('Quizzes - First quiz structure: ' . json_encode($quizzes[0]));
    }
    
    // Group quizzes by subject (use topic if subject is not available)
    $quizzes_by_subject = [];
    foreach ($quizzes as $quiz) {
        // Determine which field to use for grouping (subject or topic)
        $subject_key = isset($quiz['subject']) ? 'subject' : (isset($quiz['topic']) ? 'topic' : 'category');
        $subject = isset($quiz[$subject_key]) ? $quiz[$subject_key] : 'General';
        
        if (!isset($quizzes_by_subject[$subject])) {
            $quizzes_by_subject[$subject] = [];
        }
        $quizzes_by_subject[$subject][] = $quiz;
    }
    
} catch (Exception $e) {
    error_log('Quizzes Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the quizzes. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <!-- Mobile-specific CSS -->
    <link rel="stylesheet" href="/assets/css/mobile.css">
    
    <!-- Preconnect to external domains for faster loading -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../assets/img/logo.png" alt="AcademicEase Logo" height="40">
                AcademicEase
            </a>
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
                        <a class="nav-link" href="progress.php">Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Quizzes Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Available Quizzes</h5>
                        <div>
                            <a href="progress.php" class="btn btn-sm btn-light">
                                <i class="bi bi-graph-up me-1"></i> View Progress
                            </a>
                        </div>
                    </div>
                    <div class="card-body bg-light border-bottom">
                        <form action="quizzes.php" method="get" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="educationLevel" class="form-label">Education Level</label>
                                <select class="form-select" id="educationLevel" name="level">
                                    <option value="all" <?php echo (!isset($_GET['level']) || $_GET['level'] === 'all') ? 'selected' : ''; ?>>All Levels</option>
                                    <option value="jhs" <?php echo (isset($_GET['level']) && $_GET['level'] === 'jhs') ? 'selected' : ''; ?>>Junior High School (JHS)</option>
                                    <option value="shs" <?php echo (isset($_GET['level']) && $_GET['level'] === 'shs') ? 'selected' : ''; ?>>Senior High School (SHS)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="subjectFilter" class="form-label">Subject</label>
                                <select class="form-select" id="subjectFilter" name="subject">
                                    <option value="all" <?php echo (!isset($_GET['subject']) || $_GET['subject'] === 'all') ? 'selected' : ''; ?>>All Subjects</option>
                                    
                                    <!-- Core subjects -->
                                    <optgroup label="Core Subjects">
                                        <option value="mathematics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                                        <option value="english" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'english') ? 'selected' : ''; ?>>English</option>
                                        <option value="integrated_science" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'integrated_science') ? 'selected' : ''; ?>>Integrated Science</option>
                                        <option value="social_studies" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'social_studies') ? 'selected' : ''; ?>>Social Studies</option>
                                        <option value="ict" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'ict') ? 'selected' : ''; ?>>ICT</option>
                                    </optgroup>
                                    
                                    <!-- JHS specific subjects -->
                                    <optgroup label="JHS Subjects">
                                        <option value="science" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'science') ? 'selected' : ''; ?>>Science</option>
                                        <option value="religious_moral_education" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'religious_moral_education') ? 'selected' : ''; ?>>Religious and Moral Education</option>
                                        <option value="creative_arts" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'creative_arts') ? 'selected' : ''; ?>>Creative Arts</option>
                                        <option value="ghanaian_language" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'ghanaian_language') ? 'selected' : ''; ?>>Ghanaian Language</option>
                                        <option value="career_technology" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'career_technology') ? 'selected' : ''; ?>>Career Technology</option>
                                        <option value="french_jhs" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'french_jhs') ? 'selected' : ''; ?>>French (JHS)</option>
                                    </optgroup>
                                    
                                    <!-- SHS Science subjects -->
                                    <optgroup label="SHS Science">
                                        <option value="chemistry" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'chemistry') ? 'selected' : ''; ?>>Chemistry</option>
                                        <option value="physics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'physics') ? 'selected' : ''; ?>>Physics</option>
                                        <option value="biology" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'biology') ? 'selected' : ''; ?>>Biology</option>
                                        <option value="elective_mathematics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'elective_mathematics') ? 'selected' : ''; ?>>Elective Mathematics</option>
                                        <option value="further_mathematics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'further_mathematics') ? 'selected' : ''; ?>>Further Mathematics</option>
                                    </optgroup>
                                    
                                    <!-- SHS Business subjects -->
                                    <optgroup label="SHS Business">
                                        <option value="financial_accounting" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'financial_accounting') ? 'selected' : ''; ?>>Financial Accounting</option>
                                        <option value="business_management" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'business_management') ? 'selected' : ''; ?>>Business Management</option>
                                        <option value="cost_accounting" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'cost_accounting') ? 'selected' : ''; ?>>Cost Accounting</option>
                                        <option value="economics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'economics') ? 'selected' : ''; ?>>Economics</option>
                                    </optgroup>
                                    
                                    <!-- SHS Humanities subjects -->
                                    <optgroup label="SHS Humanities">
                                        <option value="government" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'government') ? 'selected' : ''; ?>>Government</option>
                                        <option value="literature" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'literature') ? 'selected' : ''; ?>>Literature in English</option>
                                        <option value="history" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'history') ? 'selected' : ''; ?>>History</option>
                                        <option value="geography" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'geography') ? 'selected' : ''; ?>>Geography</option>
                                        <option value="french_shs" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'french_shs') ? 'selected' : ''; ?>>French (SHS)</option>
                                    </optgroup>
                                    
                                    <!-- SHS Technical and Vocational subjects -->
                                    <optgroup label="SHS Technical & Vocational">
                                        <option value="technical_drawing" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'technical_drawing') ? 'selected' : ''; ?>>Technical Drawing</option>
                                        <option value="building_construction" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'building_construction') ? 'selected' : ''; ?>>Building Construction</option>
                                        <option value="woodwork" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'woodwork') ? 'selected' : ''; ?>>Woodwork</option>
                                        <option value="metalwork" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'metalwork') ? 'selected' : ''; ?>>Metalwork</option>
                                        <option value="auto_mechanics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'auto_mechanics') ? 'selected' : ''; ?>>Auto Mechanics</option>
                                        <option value="electrical_engineering" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'electrical_engineering') ? 'selected' : ''; ?>>Electrical Engineering</option>
                                    </optgroup>
                                    
                                    <!-- SHS Visual Arts subjects -->
                                    <optgroup label="SHS Visual Arts">
                                        <option value="visual_arts" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'visual_arts') ? 'selected' : ''; ?>>Visual Arts</option>
                                        <option value="graphic_design" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'graphic_design') ? 'selected' : ''; ?>>Graphic Design</option>
                                        <option value="picture_making" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'picture_making') ? 'selected' : ''; ?>>Picture Making</option>
                                        <option value="ceramics" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'ceramics') ? 'selected' : ''; ?>>Ceramics</option>
                                        <option value="sculpture" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'sculpture') ? 'selected' : ''; ?>>Sculpture</option>
                                        <option value="textiles" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'textiles') ? 'selected' : ''; ?>>Textiles</option>
                                    </optgroup>
                                    
                                    <!-- SHS Home Economics subjects -->
                                    <optgroup label="SHS Home Economics">
                                        <option value="food_nutrition" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'food_nutrition') ? 'selected' : ''; ?>>Food and Nutrition</option>
                                        <option value="clothing_textiles" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'clothing_textiles') ? 'selected' : ''; ?>>Clothing and Textiles</option>
                                        <option value="management_living" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'management_living') ? 'selected' : ''; ?>>Management in Living</option>
                                    </optgroup>
                                    
                                    <!-- SHS Agricultural Science -->
                                    <optgroup label="SHS Agriculture">
                                        <option value="agricultural_science" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'agricultural_science') ? 'selected' : ''; ?>>Agricultural Science</option>
                                        <option value="crop_husbandry" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'crop_husbandry') ? 'selected' : ''; ?>>Crop Husbandry</option>
                                        <option value="animal_husbandry" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'animal_husbandry') ? 'selected' : ''; ?>>Animal Husbandry</option>
                                    </optgroup>
                                    
                                    <!-- Additional subjects -->
                                    <optgroup label="Other Subjects">
                                        <option value="music" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'music') ? 'selected' : ''; ?>>Music</option>
                                        <option value="physical_education" <?php echo (isset($_GET['subject']) && $_GET['subject'] === 'physical_education') ? 'selected' : ''; ?>>Physical Education</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter me-1"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php else: ?>
                            <?php if (empty($quizzes_by_subject)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> No quizzes available at the moment. Please check back later.
                                </div>
                            <?php else: ?>
                                <ul class="nav nav-tabs mb-4" id="subjectTabs" role="tablist">
                                    <?php $first = true; ?>
                                    <?php foreach ($quizzes_by_subject as $subject => $subject_quizzes): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?php echo $first ? 'active' : ''; ?>" 
                                                    id="<?php echo strtolower(str_replace(' ', '-', $subject)); ?>-tab" 
                                                    data-bs-toggle="tab" 
                                                    data-bs-target="#<?php echo strtolower(str_replace(' ', '-', $subject)); ?>" 
                                                    type="button" 
                                                    role="tab" 
                                                    aria-controls="<?php echo strtolower(str_replace(' ', '-', $subject)); ?>" 
                                                    aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                                                <?php echo htmlspecialchars($subject); ?>
                                            </button>
                                        </li>
                                        <?php $first = false; ?>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="tab-content" id="subjectTabsContent">
                                    <?php $first = true; ?>
                                    <?php foreach ($quizzes_by_subject as $subject => $subject_quizzes): ?>
                                        <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" 
                                             id="<?php echo strtolower(str_replace(' ', '-', $subject)); ?>" 
                                             role="tabpanel" 
                                             aria-labelledby="<?php echo strtolower(str_replace(' ', '-', $subject)); ?>-tab">
                                            
                                            <div class="row">
                                                <?php foreach ($subject_quizzes as $quiz): ?>
                                                    <div class="col-md-4 mb-4">
                                                        <div class="card h-100">
                                                            <div class="card-header bg-light">
                                                                <h5 class="card-title mb-0"><?php echo isset($quiz['title']) ? htmlspecialchars($quiz['title']) : (isset($quiz['name']) ? htmlspecialchars($quiz['name']) : 'Quiz'); ?></h5>
                                                            </div>
                                                            <div class="card-body">
                                                                <p class="card-text"><?php echo isset($quiz['description']) ? htmlspecialchars($quiz['description']) : 'No description available'; ?></p>
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <span class="badge bg-primary"><?php echo isset($quiz['level']) ? htmlspecialchars($quiz['level']) : 'General'; ?></span>
                                                                    <span><i class="bi bi-clock me-1"></i> <?php echo isset($quiz['estimated_time']) ? $quiz['estimated_time'] : '10'; ?> min</span>
                                                                </div>
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <span><i class="bi bi-question-circle me-1"></i> <?php echo isset($quiz['question_count']) ? $quiz['question_count'] : '?'; ?> questions</span>
                                                                    <a href="quiz.php?id=<?php echo isset($quiz['quiz_id']) ? $quiz['quiz_id'] : (isset($quiz['id']) ? $quiz['id'] : '0'); ?>" class="btn btn-outline-primary">Start Quiz</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php $first = false; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($quiz_history)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Your Quiz History</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Quiz</th>
                                        <th>Score</th>
                                        <th>Time Taken</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($quiz_history, 0, 5) as $result): ?>
                                    <tr>
                                        <td><?php echo isset($result['completion_date']) ? date('M d, Y', strtotime($result['completion_date'])) : 'Unknown date'; ?></td>
                                        <td><?php echo isset($result['topic']) ? htmlspecialchars($result['topic']) : (isset($result['subject']) ? htmlspecialchars($result['subject']) : 'Unknown topic'); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <?php 
                                                    $score = isset($result['score_percentage']) ? $result['score_percentage'] : 0;
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
                                            <a href="quiz-review.php?id=<?php echo isset($result['result_id']) ? $result['result_id'] : '0'; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($quiz_history) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="progress.php" class="btn btn-outline-primary">View All Results</a>
                            </div>
                        <?php endif; ?>
                    </div>
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
                    <p class="mb-0">&copy; 2025 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted me-3">Terms of Service</a>
                    <a href="support.php" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Performance optimization script -->
    <script src="/assets/js/performance.js"></script>
</body>
</html>
