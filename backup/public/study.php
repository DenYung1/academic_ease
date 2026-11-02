<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    $users = supabaseSelect('users', [
        'filter' => [
            'user_id.eq' => $_SESSION['user_id']
        ]
    ]);
    
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
        header('Location: subscribe.php?expired=1');
        exit;
    }
    
    // Get selected subject and level
    $subject = isset($_GET['subject']) ? $_GET['subject'] : 'mathematics';
    $level = isset($_GET['level']) ? $_GET['level'] : 'jhs';
    
    // Get study materials for the selected subject and level
    $study_materials = supabaseSelect('study_materials', [
        'filter' => [
            'subject.eq' => $subject,
            'level.eq' => $level
        ],
        'order' => 'title.asc'
    ]);
    
    // Get available subjects
    $subjects = [
        'mathematics' => 'Mathematics',
        'science' => 'Science',
        'english' => 'English',
        'social_studies' => 'Social Studies',
        'ict' => 'ICT',
        'integrated_science' => 'Integrated Science',
        'chemistry' => 'Chemistry',
        'physics' => 'Physics',
        'biology' => 'Biology',
        'government' => 'Government',
        'economics' => 'Economics',
        'literature' => 'Literature',
        'history' => 'History',
        'geography' => 'Geography'
    ];
    
    // Filter subjects based on level
    if ($level === 'jhs') {
        // Remove SHS-only subjects
        unset($subjects['chemistry']);
        unset($subjects['physics']);
        unset($subjects['biology']);
        unset($subjects['government']);
        unset($subjects['economics']);
        unset($subjects['literature']);
    }
    
    // Get exam resources
    $exam_resources = [];
    
    if ($level === 'jhs') {
        $exam_resources = supabaseSelect('exam_resources', [
            'filter' => [
                'exam_type.eq' => 'bece'
            ],
            'order' => 'year.desc'
        ]);
    } else {
        $exam_resources = supabaseSelect('exam_resources', [
            'filter' => [
                'exam_type.eq' => 'wassce'
            ],
            'order' => 'year.desc'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Study Materials Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the study materials. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../assets/img/logo.png" alt="Academic Ease Logo" height="40">
                Academic Ease
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
                        <a class="nav-link active" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="progress.php">Progress</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['name']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="subscribe.php"><i class="bi bi-star me-2"></i>Subscription</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Study Materials Content -->
    <div class="container py-4">
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
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2 class="mb-0"><?php echo htmlspecialchars($subjects[$subject]); ?> - <?php echo strtoupper($level); ?></h2>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary active" data-bs-toggle="tab" data-bs-target="#materials-tab">
                                    <i class="bi bi-journal-text me-1"></i> Materials
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="tab" data-bs-target="#exams-tab">
                                    <i class="bi bi-file-earmark-text me-1"></i> Past Exams
                                </button>
                            </div>
                        </div>
                        
                        <div class="tab-content">
                            <!-- Study Materials Tab -->
                            <div class="tab-pane fade show active" id="materials-tab">
                                <div class="row">
                                    <?php if (empty($study_materials)): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle-fill me-2"></i>
                                            No study materials found for this subject and level. Please check back later or try another subject.
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($study_materials as $material): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($material['title']); ?></h5>
                                                    <p class="card-text text-muted small">
                                                        <i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($material['category']); ?>
                                                    </p>
                                                    <p class="card-text"><?php echo htmlspecialchars(substr($material['description'], 0, 100)) . '...'; ?></p>
                                                </div>
                                                <div class="card-footer bg-white border-top-0">
                                                    <a href="study-material.php?id=<?php echo $material['material_id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-book me-1"></i> Study Now
                                                    </a>
                                                    <?php if ($material['downloadable']): ?>
                                                    <a href="<?php echo htmlspecialchars($material['file_url']); ?>" class="btn btn-outline-secondary btn-sm ms-1" download>
                                                        <i class="bi bi-download me-1"></i> Download
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Past Exams Tab -->
                            <div class="tab-pane fade" id="exams-tab">
                                <div class="row">
                                    <?php if (empty($exam_resources)): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle-fill me-2"></i>
                                            No exam resources found for this level. Please check back later.
                                        </div>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($exam_resources as $exam): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo strtoupper($exam['exam_type']); ?> <?php echo htmlspecialchars($exam['year']); ?></h5>
                                                    <p class="card-text text-muted small">
                                                        <i class="bi bi-tag me-1"></i> <?php echo htmlspecialchars($exam['subject']); ?>
                                                    </p>
                                                    <p class="card-text"><?php echo htmlspecialchars($exam['description']); ?></p>
                                                </div>
                                                <div class="card-footer bg-white border-top-0">
                                                    <a href="exam-resource.php?id=<?php echo $exam['resource_id']; ?>" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-file-earmark-text me-1"></i> View Questions
                                                    </a>
                                                    <a href="<?php echo htmlspecialchars($exam['file_url']); ?>" class="btn btn-outline-secondary btn-sm ms-1" download>
                                                        <i class="bi bi-download me-1"></i> Download
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Featured Resources -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Featured Resources</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-calculator me-2 text-primary"></i>
                                            Formula Sheets
                                        </h5>
                                        <p class="card-text">Quick reference guides for mathematical and scientific formulas.</p>
                                        <a href="formula-sheets.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-journal-check me-2 text-primary"></i>
                                            Practice Quizzes
                                        </h5>
                                        <p class="card-text">Test your knowledge with subject-specific practice quizzes.</p>
                                        <a href="quizzes.php" class="btn btn-sm btn-outline-primary">Start Quiz</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                            Exam Preparation
                                        </h5>
                                        <p class="card-text">Comprehensive guides for BECE and WASSCE preparation.</p>
                                        <a href="exam-prep.php" class="btn btn-sm btn-outline-primary">Learn More</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="bi bi-clock-history me-2 text-primary"></i>
                                            Study Planner
                                        </h5>
                                        <p class="card-text">Create a personalized study schedule for effective learning.</p>
                                        <a href="study-planner.php" class="btn btn-sm btn-outline-primary">Create Plan</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
