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
error_log('Study - Session ID: ' . session_id());
error_log('Study - SESSION: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Debug: Log the user ID we're looking for
    error_log('Study - Looking for user_id: ' . $_SESSION['user_id']);
    
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the result of the user query
    error_log('Study - User query result: ' . json_encode($users));
    
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
    
    // Get selected subject and level
    $subject = isset($_GET['subject']) ? $_GET['subject'] : 'mathematics';
    $level = isset($_GET['level']) ? $_GET['level'] : 'jhs';
    
    // Get study materials from past_questions table for the selected subject and level
    // Filter by exam_type=study_material to only get study materials
    $endpoint = 'past_questions?subject=eq.' . urlencode($subject) . '&level=eq.' . urlencode($level) . '&exam_type=eq.study_material&order=title.asc';
    $study_materials = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the study materials query and results
    error_log('Study Materials Query: ' . $endpoint);
    error_log('Study Materials Results: ' . json_encode($study_materials));
    
    // If no results, try a broader query to see if any study materials exist at all
    if (empty($study_materials)) {
        $debug_endpoint = 'past_questions?exam_type=eq.study_material';
        $debug_materials = supabaseRequest($debug_endpoint, 'GET', null, true);
        error_log('Debug All Study Materials: ' . json_encode($debug_materials));
    }
    
    // Define subjects based on level
    $jhs_subjects = [
        // JHS Core subjects
        'mathematics' => 'Mathematics',
        'english' => 'English',
        'science' => 'Integrated Science', // Changed from integrated_science to science to match admin panel
        'social_studies' => 'Social Studies',
        'ict_jhs' => 'ICT', // Changed from ict to ict_jhs to match admin panel
        'religious_moral_education' => 'Religious and Moral Education',
        'creative_arts' => 'Creative Arts',
        'ghanaian_language' => 'Ghanaian Language',
        'career_technology' => 'Career Technology',
        'french_jhs' => 'French (JHS)'
    ];
    
    $shs_subjects = [
        // SHS Core subjects
        'mathematics' => 'Mathematics',
        'english' => 'English',
        'social_studies' => 'Social Studies',
        'ict' => 'ICT',
        
        // SHS Science subjects
        'chemistry' => 'Chemistry',
        'physics' => 'Physics',
        'biology' => 'Biology',
        'elective_mathematics' => 'Elective Mathematics',
        'further_mathematics' => 'Further Mathematics',
        
        // SHS Business subjects
        'financial_accounting' => 'Financial Accounting',
        'business_management' => 'Business Management',
        'cost_accounting' => 'Cost Accounting',
        
        // SHS Humanities subjects
        'government' => 'Government',
        'economics' => 'Economics',
        'literature' => 'Literature in English',
        'history' => 'History',
        'geography' => 'Geography',
        'french_shs' => 'French (SHS)',
        
        // SHS Technical and Vocational subjects
        'technical_drawing' => 'Technical Drawing',
        'building_construction' => 'Building Construction',
        'woodwork' => 'Woodwork',
        'metalwork' => 'Metalwork',
        'auto_mechanics' => 'Auto Mechanics',
        'electrical_engineering' => 'Electrical Engineering',
        
        // SHS Visual Arts subjects
        'visual_arts' => 'Visual Arts',
        'graphic_design' => 'Graphic Design',
        'picture_making' => 'Picture Making',
        'ceramics' => 'Ceramics',
        'sculpture' => 'Sculpture',
        'textiles' => 'Textiles',
        
        // SHS Home Economics subjects
        'food_nutrition' => 'Food and Nutrition',
        'clothing_textiles' => 'Clothing and Textiles',
        'management_living' => 'Management in Living',
        
        // SHS Agricultural Science and related
        'agricultural_science' => 'Agricultural Science',
        'crop_husbandry' => 'Crop Husbandry and Horticulture',
        'animal_husbandry' => 'Animal Husbandry',
        
        // Additional subjects
        'music' => 'Music',
        'physical_education' => 'Physical Education'
    ];
    
    // Define subjects based on level
    $jhs_subjects = [
        'mathematics' => 'Mathematics',
        'english' => 'English',
        'integrated_science' => 'Integrated Science',
        'social_studies' => 'Social Studies',
        'ict' => 'ICT',
        'religious_moral_education' => 'Religious and Moral Education',
        'creative_arts' => 'Creative Arts',
        'ghanaian_language' => 'Ghanaian Language',
        'career_technology' => 'Career Technology',
        'french_jhs' => 'French (JHS)'
    ];
    
    $shs_subjects = [
        'mathematics' => 'Mathematics',
        'english' => 'English',
        'social_studies' => 'Social Studies',
        'ict_shs' => 'ICT', // Changed from ict to ict_shs to match admin panel
        'integrated_science' => 'Integrated Science', // Added to match admin panel
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
        'physical_education' => 'Physical Education'
    ];
    
    // Use the appropriate subject list based on level
    $subjects = ($level === 'jhs') ? $jhs_subjects : $shs_subjects;
    
    // Get past questions only (fresh approach)
    $exam_resources = [];
    
    // Map the subject to the correct database field name
    $subject_map = [
        'integrated_science' => 'science', // Map integrated_science to science for JHS
        'ict' => $level === 'jhs' ? 'ict_jhs' : 'ict_shs' // Map ICT to the correct level-specific field
    ];
    
    // Use the mapped subject if it exists
    $db_subject = isset($subject_map[$subject]) ? $subject_map[$subject] : $subject;
    
    // Fetch past questions for the selected level AND subject
    // Exclude study materials by filtering out exam_type=study_material
    $past_endpoint = 'past_questions?level=eq.' . urlencode($level) . 
                    '&subject=eq.' . urlencode($db_subject) . 
                    '&exam_type=neq.study_material&order=year.desc';
    
    // Debug: Log the past questions query
    error_log('Past Questions Query: ' . $past_endpoint);
    
    $past_questions = supabaseRequest($past_endpoint, 'GET', null, true);
    
    // Debug: Log the past questions results
    error_log('Past Questions Results: ' . json_encode($past_questions));
    
    // Format the results for display
    if (!empty($past_questions)) {
        foreach ($past_questions as $question) {
            $exam_resources[] = [
                'id' => isset($question['question_id']) ? $question['question_id'] : '',
                'subject' => isset($question['subject']) ? $question['subject'] : 'General',
                'year' => isset($question['year']) ? $question['year'] : 'N/A',
                'description' => isset($question['title']) ? $question['title'] : 'No description available',
                'paper_type' => isset($question['exam_type']) ? $question['exam_type'] : 'Exam',
                'url' => isset($question['file_path']) ? $question['file_path'] : '#'
            ];
        }
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
    <title>Study Materials - AcademicEase</title>
    
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
                        <a class="nav-link active" href="study.php">Study</a>
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
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="study.php?level=jhs&subject=<?php echo $subject; ?>" class="list-group-item list-group-item-action <?php echo $level === 'jhs' ? 'active' : ''; ?>">
                                <i class="bi bi-book me-2"></i> Junior High School (JHS)
                            </a>
                            <a href="study.php?level=shs&subject=<?php echo $subject; ?>" class="list-group-item list-group-item-action <?php echo $level === 'shs' ? 'active' : ''; ?>">
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
                            <?php foreach ($subjects as $subjectKey => $subjectName): ?>
                            <a href="study.php?level=<?php echo $level; ?>&subject=<?php echo $subjectKey; ?>" class="list-group-item list-group-item-action <?php echo $subject === $subjectKey ? 'active' : ''; ?>">
                                <i class="bi bi-journal me-2"></i> <?php echo $subjectName; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo $subjects[$subject]; ?> Study Materials (<?php echo $level === 'jhs' ? 'JHS' : 'SHS'; ?>)</h5>
                        <div>
                            <span class="badge bg-light text-primary"><?php echo count($study_materials); ?> Materials</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($study_materials)): ?>
                            <!-- Categorize materials by type -->
                            <?php 
                            $file_materials = [];
                            $text_materials = [];
                            
                            foreach ($study_materials as $material) {
                                if (!empty($material['file_path'])) {
                                    $file_materials[] = $material;
                                } else {
                                    $text_materials[] = $material;
                                }
                            }
                            ?>
                            
                            <!-- Material Type Tabs -->
                            <ul class="nav nav-tabs mb-3" id="materialTypeTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="all-materials-tab" data-bs-toggle="tab" data-bs-target="#all-materials" type="button" role="tab" aria-controls="all-materials" aria-selected="true">
                                        All Materials <span class="badge bg-primary ms-1"><?php echo count($study_materials); ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="file-materials-tab" data-bs-toggle="tab" data-bs-target="#file-materials" type="button" role="tab" aria-controls="file-materials" aria-selected="false">
                                        PDF Materials <span class="badge bg-primary ms-1"><?php echo count($file_materials); ?></span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="text-materials-tab" data-bs-toggle="tab" data-bs-target="#text-materials" type="button" role="tab" aria-controls="text-materials" aria-selected="false">
                                        Text Materials <span class="badge bg-primary ms-1"><?php echo count($text_materials); ?></span>
                                    </button>
                                </li>
                            </ul>
                            
                            <!-- Tab Content -->
                            <div class="tab-content" id="materialTypeTabContent">
                                <!-- All Materials Tab -->
                                <div class="tab-pane fade show active" id="all-materials" role="tabpanel" aria-labelledby="all-materials-tab">
                                    <div class="row">
                                        <?php foreach ($study_materials as $material): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                    <h5 class="card-title mb-0"><?php echo isset($material['title']) ? htmlspecialchars($material['title']) : 'Untitled Material'; ?></h5>
                                                    <?php if (!empty($material['file_path'])): ?>
                                                        <span class="badge bg-info">PDF</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Text</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text"><?php echo isset($material['description']) ? htmlspecialchars($material['description']) : 'No description available'; ?></p>
                                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                                        <div>
                                                            <span class="badge bg-primary"><?php echo isset($material['year']) ? htmlspecialchars($material['year']) : date('Y'); ?></span>
                                                            <span class="badge bg-secondary ms-1">Study Material</span>
                                                        </div>
                                                        <?php
                                                        // Determine the action based on whether there's a file or not
                                                        $actionLabel = 'View';
                                                        $actionUrl = '#';
                                                        $targetBlank = false;
                                                        $iconClass = 'book';
                                                        
                                                        if (!empty($material['file_path'])) {
                                                            // This is a file-based material
                                                            $actionUrl = 'download.php?file=' . urlencode($material['file_path']);
                                                            $actionLabel = 'Download';
                                                            $targetBlank = true;
                                                            $iconClass = 'file-earmark-arrow-down';
                                                        } else {
                                                            // This is a text-based material
                                                            $actionUrl = 'view-material.php?id=' . $material['question_id'];
                                                            $actionLabel = 'Read';
                                                            $iconClass = 'book';
                                                        }
                                                        ?>
                                                        <a href="<?php echo htmlspecialchars($actionUrl); ?>" class="btn btn-sm btn-primary" <?php echo $targetBlank ? 'target="_blank"' : ''; ?>>
                                                            <i class="bi bi-<?php echo $iconClass; ?> me-1"></i> <?php echo $actionLabel; ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- File Materials Tab -->
                                <div class="tab-pane fade" id="file-materials" role="tabpanel" aria-labelledby="file-materials-tab">
                                    <div class="row">
                                        <?php if (!empty($file_materials)): ?>
                                            <?php foreach ($file_materials as $material): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100 shadow-sm">
                                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                        <h5 class="card-title mb-0"><?php echo isset($material['title']) ? htmlspecialchars($material['title']) : 'Untitled Material'; ?></h5>
                                                        <span class="badge bg-info">PDF</span>
                                                    </div>
                                                    <div class="card-body">
                                                        <p class="card-text"><?php echo isset($material['description']) ? htmlspecialchars($material['description']) : 'No description available'; ?></p>
                                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                                            <div>
                                                                <span class="badge bg-primary"><?php echo isset($material['year']) ? htmlspecialchars($material['year']) : date('Y'); ?></span>
                                                            </div>
                                                            <a href="download.php?file=<?php echo urlencode($material['file_path']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Download
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle me-2"></i> No PDF materials available for this subject.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Text Materials Tab -->
                                <div class="tab-pane fade" id="text-materials" role="tabpanel" aria-labelledby="text-materials-tab">
                                    <div class="row">
                                        <?php if (!empty($text_materials)): ?>
                                            <?php foreach ($text_materials as $material): ?>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100 shadow-sm">
                                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                        <h5 class="card-title mb-0"><?php echo isset($material['title']) ? htmlspecialchars($material['title']) : 'Untitled Material'; ?></h5>
                                                        <span class="badge bg-success">Text</span>
                                                    </div>
                                                    <div class="card-body">
                                                        <p class="card-text"><?php echo isset($material['description']) ? htmlspecialchars($material['description']) : 'No description available'; ?></p>
                                                        <div class="d-flex justify-content-between align-items-center mt-3">
                                                            <div>
                                                                <span class="badge bg-primary"><?php echo isset($material['year']) ? htmlspecialchars($material['year']) : date('Y'); ?></span>
                                                            </div>
                                                            <a href="view-material.php?id=<?php echo $material['question_id']; ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-book me-1"></i> Read
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle me-2"></i> No text materials available for this subject.
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> No study materials found for this subject and level. Please check back later or select a different subject.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Past Exam Papers -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Past Exam Papers (<?php echo $level === 'jhs' ? 'BECE' : 'WASSCE'; ?>)</h5>
                        <span class="badge bg-light text-primary"><?php echo count($exam_resources); ?> Papers</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($exam_resources)): ?>
                            <div class="row">
                                <?php foreach ($exam_resources as $resource): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-0 shadow-sm hover-shadow">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0 text-primary">
                                                <i class="bi bi-file-earmark-pdf me-2"></i>
                                                <?php 
                                                $subject = isset($resource['subject']) ? htmlspecialchars($resource['subject']) : 'General';
                                                // Make English appear in all caps
                                                if (strtolower($subject) === 'english') {
                                                    $subject = 'ENGLISH';
                                                }
                                                echo $subject;
                                                ?>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-primary"><?php echo isset($resource['year']) ? htmlspecialchars($resource['year']) : 'N/A'; ?></span>
                                                <span class="badge bg-secondary"><?php echo isset($resource['paper_type']) ? htmlspecialchars($resource['paper_type']) : 'Exam'; ?></span>
                                            </div>
                                            <div class="description-box p-2 mb-3 bg-light rounded">
                                                <p class="card-text mb-0"><strong>Description:</strong></p>
                                                <p class="card-text"><?php echo isset($resource['description']) ? htmlspecialchars($resource['description']) : 'No description available'; ?></p>
                                            </div>
                                            <div class="text-center">
                                                <a href="view-file.php?file=<?php 
                                                    // Extract just the filename from the complete path
                                                    $filename = '';
                                                    if (isset($resource['url']) && !empty($resource['url'])) {
                                                        $filename = basename($resource['url']);
                                                    }
                                                    echo urlencode($filename);
                                                ?>" class="btn btn-primary w-100" target="_blank">
                                                    <i class="bi bi-eye me-2"></i> View Question Paper
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> No exam resources found. Please check back later.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Featured Resources -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Featured Resources</h5>
                    </div>
                    <div class="card-body">
                        <!-- Educational Level Tabs -->
                        <ul class="nav nav-tabs mb-4" id="resourceTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-resources" type="button" role="tab" aria-controls="all-resources" aria-selected="true">All Resources</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="jhs-tab" data-bs-toggle="tab" data-bs-target="#jhs-resources" type="button" role="tab" aria-controls="jhs-resources" aria-selected="false">JHS</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="shs-tab" data-bs-toggle="tab" data-bs-target="#shs-resources" type="button" role="tab" aria-controls="shs-resources" aria-selected="false">SHS</button>
                            </li>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content" id="resourceTabContent">
                            <!-- All Resources Tab -->
                            <div class="tab-pane fade show active" id="all-resources" role="tabpanel" aria-labelledby="all-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-calculator me-2 text-primary"></i>
                                                    Formula Sheets
                                                </h5>
                                                <p class="card-text">Quick reference guides for mathematical and scientific formulas for all GES subjects.</p>
                                                <a href="formula-sheets.php" class="btn btn-sm btn-outline-primary">View All</a>
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
                                                <p class="card-text">Comprehensive guides for BECE and WASSCE preparation covering all GES subjects.</p>
                                                <a href="exam-prep.php" class="btn btn-sm btn-outline-primary">Learn More</a>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            
                            <!-- JHS Resources Tab -->
                            <div class="tab-pane fade" id="jhs-resources" role="tabpanel" aria-labelledby="jhs-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-calculator me-2 text-primary"></i>
                                                    JHS Formula Sheets
                                                </h5>
                                                <p class="card-text">Essential formulas for Mathematics, Integrated Science, and other JHS subjects.</p>
                                                <a href="formula-sheets.php?level=jhs" class="btn btn-sm btn-outline-primary">View JHS Formulas</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                                    BECE Preparation
                                                </h5>
                                                <p class="card-text">Comprehensive study guides and past questions for all BECE subjects.</p>
                                                <a href="exam-prep.php?exam=bece" class="btn btn-sm btn-outline-primary">BECE Resources</a>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            
                            <!-- SHS Resources Tab -->
                            <div class="tab-pane fade" id="shs-resources" role="tabpanel" aria-labelledby="shs-tab">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-calculator me-2 text-primary"></i>
                                                    SHS Formula Sheets
                                                </h5>
                                                <p class="card-text">Comprehensive formulas for Mathematics, Physics, Chemistry, and other SHS subjects.</p>
                                                <a href="formula-sheets.php?level=shs" class="btn btn-sm btn-outline-primary">View SHS Formulas</a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title">
                                                    <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                                    WASSCE Preparation
                                                </h5>
                                                <p class="card-text">Study guides, past questions, and exam tips for all WASSCE subjects.</p>
                                                <a href="exam-prep.php?exam=wassce" class="btn btn-sm btn-outline-primary">WASSCE Resources</a>
                                            </div>
                                        </div>
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
    
    <!-- Performance optimization script -->
    <script src="/assets/js/performance.js"></script>
</body>
</html>
