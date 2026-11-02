<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Direct query using the service role key
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
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    
    // Get selected level
    $level = isset($_GET['level']) ? $_GET['level'] : (isset($user['school_level']) ? $user['school_level'] : 'jhs');
    
    // Get selected subject (if any)
    $subject = isset($_GET['subject']) ? $_GET['subject'] : '';
    
    // Define exam types based on level
    $exam_type = $level === 'jhs' ? 'BECE' : 'WASSCE';
    
    // Get past questions from database
    $filter = "level=eq.$level";
    if (!empty($subject)) {
        $filter .= "&subject=eq.$subject";
    }
    // Exclude study materials by filtering out exam_type=study_material
    $filter .= "&exam_type=neq.study_material";
    $order = "order=year.desc,title.asc";
    $past_questions = supabaseRequest("past_questions?$filter&$order", 'GET', null, true);
    
    // Group past questions by subject
    $grouped_questions = [];
    if (!empty($past_questions)) {
        foreach ($past_questions as $question) {
            $subject_key = $question['subject'] ?? 'general';
            if (!isset($grouped_questions[$subject_key])) {
                $grouped_questions[$subject_key] = [];
            }
            $grouped_questions[$subject_key][] = $question;
        }
    }
    
    // Define subjects based on level
    $subjects = [
        'jhs' => [
            // Core subjects
            'mathematics' => 'Mathematics',
            'english' => 'English Language',
            'science' => 'Integrated Science',
            'social_studies' => 'Social Studies',
            'ict' => 'ICT',
            'religious_moral_education' => 'Religious and Moral Education',
            'creative_arts' => 'Creative Arts',
            'ghanaian_language' => 'Ghanaian Language',
            'career_technology' => 'Career Technology',
            'french_jhs' => 'French'
        ],
        'shs' => [
            // Core subjects
            'mathematics' => 'Core Mathematics',
            'english' => 'English Language',
            'integrated_science' => 'Integrated Science',
            'social_studies' => 'Social Studies',
            
            // Science subjects
            'elective_mathematics' => 'Elective Mathematics',
            'physics' => 'Physics',
            'chemistry' => 'Chemistry',
            'biology' => 'Biology',
            'further_mathematics' => 'Further Mathematics',
            
            // Business subjects
            'financial_accounting' => 'Financial Accounting',
            'business_management' => 'Business Management',
            'cost_accounting' => 'Cost Accounting',
            'economics' => 'Economics',
            
            // Humanities subjects
            'government' => 'Government',
            'literature' => 'Literature in English',
            'history' => 'History',
            'geography' => 'Geography',
            'french_shs' => 'French',
            
            // Technical and Vocational subjects
            'technical_drawing' => 'Technical Drawing',
            'building_construction' => 'Building Construction',
            'woodwork' => 'Woodwork',
            'metalwork' => 'Metalwork',
            'auto_mechanics' => 'Auto Mechanics',
            'electrical_engineering' => 'Electrical Engineering',
            
            // Visual Arts subjects
            'visual_art' => 'Visual Art',
            'graphic_design' => 'Graphic Design',
            'picture_making' => 'Picture Making',
            'ceramics' => 'Ceramics',
            'sculpture' => 'Sculpture',
            'textiles' => 'Textiles',
            
            // Home Economics subjects
            'food_nutrition' => 'Food and Nutrition',
            'clothing_textiles' => 'Clothing and Textiles',
            'management_living' => 'Management in Living',
            
            // Agriculture subjects
            'general_agriculture' => 'General Agriculture',
            'crop_husbandry' => 'Crop Husbandry and Horticulture',
            'animal_husbandry' => 'Animal Husbandry',
            
            // Additional subjects
            'music' => 'Music',
            'physical_education' => 'Physical Education'
        ]
    ];
    
    // Define default resources if no past questions are found
    $default_resources = [
        'jhs' => [
            [
                'title' => 'BECE Study Guide',
                'description' => 'Comprehensive study guide for all BECE subjects',
                'subjects' => ['All Subjects'],
                'icon' => 'book'
            ],
            [
                'title' => 'BECE Exam Timetable',
                'description' => 'Official examination schedule and guidelines',
                'subjects' => ['Examination Information'],
                'icon' => 'calendar-check'
            ]
        ],
        'shs' => [
            [
                'title' => 'WASSCE Study Guide',
                'description' => 'Comprehensive study guide for all WASSCE subjects',
                'subjects' => ['All Subjects'],
                'icon' => 'book'
            ],
            [
                'title' => 'WASSCE Exam Timetable',
                'description' => 'Official examination schedule and guidelines',
                'subjects' => ['Examination Information'],
                'icon' => 'calendar-check'
            ]
        ]
    ];
    
    // Define exam tips
    $exam_tips = [
        [
            'title' => 'Create a Study Schedule',
            'description' => 'Develop a realistic study timetable that allocates sufficient time for each subject based on your strengths and weaknesses.',
            'icon' => 'calendar-week'
        ],
        [
            'title' => 'Practice Past Questions',
            'description' => 'Regularly practice with past examination questions to familiarize yourself with the format and types of questions asked.',
            'icon' => 'pencil-square'
        ],
        [
            'title' => 'Form Study Groups',
            'description' => 'Join or create study groups to discuss difficult concepts and share knowledge with peers.',
            'icon' => 'people'
        ],
        [
            'title' => 'Take Care of Your Health',
            'description' => 'Ensure adequate sleep, proper nutrition, and regular exercise to maintain optimal brain function during your preparation.',
            'icon' => 'heart-pulse'
        ],
        [
            'title' => 'Use Active Learning Techniques',
            'description' => 'Employ techniques like summarizing, teaching others, and creating mind maps to enhance understanding and retention.',
            'icon' => 'lightbulb'
        ],
        [
            'title' => 'Manage Exam Anxiety',
            'description' => 'Practice relaxation techniques such as deep breathing and positive visualization to manage exam stress.',
            'icon' => 'emoji-smile'
        ]
    ];
    
} catch (Exception $e) {
    error_log('Exam Prep Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the exam preparation resources. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Preparation - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .resource-card, .tip-card {
            transition: transform 0.2s;
            height: 100%;
        }
        
        .resource-card:hover, .tip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .subject-badge {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .tip-icon {
            font-size: 2rem;
            color: #0d6efd;
            margin-bottom: 15px;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 40px;
            margin-bottom: 30px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #dee2e6;
        }
        
        .timeline-item:after {
            content: '';
            position: absolute;
            left: -8px;
            top: 0;
            height: 18px;
            width: 18px;
            border-radius: 50%;
            background-color: #0d6efd;
        }
        
        .timeline-date {
            font-weight: 600;
            color: #0d6efd;
            margin-bottom: 5px;
        }
    </style>
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
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?>
                        </button>
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

    <!-- Exam Preparation Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="study.php">Study</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Exam Preparation</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="mb-0">Exam Preparation</h2>
                <p class="text-muted">Resources and guides for <?php echo $exam_type; ?> preparation</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group">
                    <a href="?level=jhs" class="btn btn-outline-primary <?php echo $level === 'jhs' ? 'active' : ''; ?>">BECE (JHS)</a>
                    <a href="?level=shs" class="btn btn-outline-primary <?php echo $level === 'shs' ? 'active' : ''; ?>">WASSCE (SHS)</a>
                </div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <!-- Subject Filter -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="?level=<?php echo $level; ?>" class="btn <?php echo empty($subject) ? 'btn-primary' : 'btn-outline-primary'; ?>">All Subjects</a>
                                <?php foreach ($subjects[$level] as $subject_key => $subject_name): ?>
                                    <a href="?level=<?php echo $level; ?>&subject=<?php echo $subject_key; ?>" class="btn <?php echo $subject === $subject_key ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        <?php echo htmlspecialchars($subject_name); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Past Questions -->
            <div class="row mb-5">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo $exam_type; ?> Past Questions</h5>
                            <span class="badge bg-light text-dark"><?php echo count($past_questions); ?> Resources</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($past_questions)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    No past questions available for the selected criteria. Please check back later or select a different subject.
                                </div>
                                
                                <!-- Show default resources if no past questions are found -->
                                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mt-3">
                                    <?php foreach ($default_resources[$level] as $resource): ?>
                                        <div class="col">
                                            <div class="card resource-card h-100">
                                                <div class="card-body">
                                                    <div class="text-center mb-3">
                                                        <i class="bi bi-<?php echo $resource['icon']; ?> text-primary" style="font-size: 2.5rem;"></i>
                                                    </div>
                                                    <h5 class="card-title text-center"><?php echo htmlspecialchars($resource['title']); ?></h5>
                                                    <p class="card-text text-muted text-center"><?php echo htmlspecialchars($resource['description']); ?></p>
                                                    <div class="text-center mb-3">
                                                        <?php foreach ($resource['subjects'] as $subject): ?>
                                                            <span class="badge bg-light text-dark subject-badge"><?php echo htmlspecialchars($subject); ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-white border-top-0 text-center">
                                                    <button class="btn btn-outline-primary">
                                                        <i class="bi bi-download me-1"></i> Download
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                    <?php foreach ($past_questions as $question): ?>
                                        <div class="col">
                                            <div class="card resource-card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="badge bg-primary"><?php echo isset($subjects[$level][$question['subject']]) ? htmlspecialchars($subjects[$level][$question['subject']]) : ucfirst($question['subject']); ?></span>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($question['year'] ?? ''); ?></span>
                                                    </div>
                                                    <h5 class="card-title"><?php echo htmlspecialchars($question['title']); ?></h5>
                                                    <p class="card-text text-muted"><?php echo htmlspecialchars($question['description'] ?? ''); ?></p>
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <span class="badge bg-<?php echo isset($question['difficulty']) ? ($question['difficulty'] === 'easy' ? 'success' : ($question['difficulty'] === 'hard' ? 'danger' : 'primary')) : 'primary'; ?>"><?php echo isset($question['difficulty']) ? htmlspecialchars(ucfirst($question['difficulty'])) : 'Medium'; ?></span>
                                                        <small class="text-muted"><?php echo isset($question['question_type']) ? htmlspecialchars(ucfirst($question['question_type'])) : 'Multiple Choice'; ?></small>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-white border-top-0">
                                                    <a href="view-file.php?file=<?php echo basename($question['file_path']); ?>" class="btn btn-outline-primary w-100">
                                                        <i class="fas fa-file-pdf me-1"></i> View Question Paper
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
            
            <!-- Exam Timeline -->
            <div class="row mb-5">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><?php echo $exam_type; ?> Timeline</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php if ($level === 'jhs'): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">January - February</div>
                                        <h5>First Term Review</h5>
                                        <p>Complete review of first term subjects and topics. Focus on Mathematics, English, and Science fundamentals.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">March - April</div>
                                        <h5>Second Term Review</h5>
                                        <p>Review second term subjects with emphasis on Social Studies and ICT. Begin practicing past questions.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">May</div>
                                        <h5>Intensive Revision</h5>
                                        <p>Comprehensive revision of all subjects. Focus on weak areas identified from practice tests.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">June</div>
                                        <h5>Final Preparation</h5>
                                        <p>Final revision and mock examinations. Review examination techniques and time management strategies.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">July</div>
                                        <h5>BECE Examination</h5>
                                        <p>The actual examination period. Remember to stay calm, read questions carefully, and manage your time effectively.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline-item">
                                        <div class="timeline-date">January - February</div>
                                        <h5>Core Subjects Review</h5>
                                        <p>Focus on core subjects: Mathematics, English, Integrated Science, and Social Studies. Review fundamental concepts.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">March - April</div>
                                        <h5>Elective Subjects Review</h5>
                                        <p>Concentrate on elective subjects. Begin practicing past questions for both core and elective subjects.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">May</div>
                                        <h5>Intensive Revision</h5>
                                        <p>Comprehensive revision of all subjects. Focus on weak areas identified from practice tests.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">June</div>
                                        <h5>Final Preparation</h5>
                                        <p>Final revision and mock examinations. Review examination techniques and time management strategies.</p>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-date">July - August</div>
                                        <h5>WASSCE Examination</h5>
                                        <p>The actual examination period. Remember to stay calm, read questions carefully, and manage your time effectively.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Exam Tips -->
            <div class="row mb-5">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Exam Success Tips</h5>
                        </div>
                        <div class="card-body">
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php foreach ($exam_tips as $tip): ?>
                                    <div class="col">
                                        <div class="card tip-card h-100">
                                            <div class="card-body text-center">
                                                <i class="bi bi-<?php echo $tip['icon']; ?> tip-icon"></i>
                                                <h5 class="card-title"><?php echo htmlspecialchars($tip['title']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($tip['description']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Assistant Promotion -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card bg-light border-0">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h4>Need personalized exam help?</h4>
                                    <p class="mb-0">Our AI Assistant can help you with specific exam questions, explain difficult concepts, and provide personalized study tips.</p>
                                </div>
                                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                    <a href="ai-assistant.php" class="btn btn-primary">
                                        <i class="bi bi-robot me-2"></i> Ask AI Assistant
                                    </a>
                                </div>
                            </div>
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
</body>
</html>
