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
    
    // Get user's level
    $level = isset($user['school_level']) ? $user['school_level'] : 'jhs';
    
    // Define subjects based on level
    $subjects = [
        'jhs' => [
            // JHS Core subjects
            'mathematics' => 'Mathematics',
            'english' => 'English',
            'integrated_science' => 'Integrated Science',
            'social_studies' => 'Social Studies',
            'ict' => 'ICT',
            'religious_moral_education' => 'Religious and Moral Education',
            'creative_arts' => 'Creative Arts',
            'ghanaian_language' => 'Ghanaian Language',
            'career_technology' => 'Career Technology',
            'french_jhs' => 'French (JHS)',
            'physical_education' => 'Physical Education'
        ],
        'shs' => [
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
        ]
    ];
    
    // Process delete plan request
    if (isset($_POST['action']) && $_POST['action'] === 'delete_plan' && isset($_POST['plan_id'])) {
        $plan_id = $_POST['plan_id'];
        
        // Verify the plan belongs to the current user before deleting
        $verify_endpoint = 'study_plans?plan_id=eq.' . urlencode($plan_id) . '&user_id=eq.' . urlencode($_SESSION['user_id']);
        $verify_plan = supabaseRequest($verify_endpoint, 'GET', null, true);
        
        if (!empty($verify_plan)) {
            // Delete the plan
            $delete_endpoint = 'study_plans?plan_id=eq.' . urlencode($plan_id);
            $result = supabaseRequest($delete_endpoint, 'DELETE', null, true);
            
            // Also delete any schedule items associated with this plan
            $delete_schedule_endpoint = 'study_schedules?plan_id=eq.' . urlencode($plan_id);
            supabaseRequest($delete_schedule_endpoint, 'DELETE', null, true);
            
            $success_message = 'Study plan deleted successfully.';
        } else {
            $error_message = 'Unable to delete the study plan. Please try again.';
        }
    }
    
    // Get user's study plans
    $endpoint = 'study_plans?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=created_at.desc';
    $study_plans = supabaseRequest($endpoint, 'GET', null, true);
    
    // If study_plans is not an array, initialize it as an empty array
    if (!is_array($study_plans)) {
        $study_plans = [];
    }
    
    // Process form submission to create a new study plan
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_plan'])) {
        $plan_name = isset($_POST['plan_name']) ? trim($_POST['plan_name']) : '';
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
        $goal = isset($_POST['goal']) ? trim($_POST['goal']) : '';
        $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
        
        $errors = [];
        
        // Validate inputs
        if (empty($plan_name)) {
            $errors[] = 'Plan name is required.';
        }
        
        if (empty($start_date)) {
            $errors[] = 'Start date is required.';
        }
        
        if (empty($end_date)) {
            $errors[] = 'End date is required.';
        } else if (strtotime($end_date) <= strtotime($start_date)) {
            $errors[] = 'End date must be after start date.';
        }
        
        if (empty($selected_subjects)) {
            $errors[] = 'Please select at least one subject.';
        }
        
        // If no errors, create the study plan
        if (empty($errors)) {
            $plan_data = [
                'user_id' => $_SESSION['user_id'],
                'plan_name' => $plan_name,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'goal' => $goal,
                'subjects' => json_encode($selected_subjects),
                'created_at' => date('c')
            ];
            
            $result = supabaseRequest('study_plans', 'POST', $plan_data, true);
            
            if ($result && !isset($result['error'])) {
                $success_message = 'Study plan created successfully!';
                
                // Refresh the study plans list
                $endpoint = 'study_plans?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=created_at.desc';
                $study_plans = supabaseRequest($endpoint, 'GET', null, true);
            } else {
                $errors[] = 'Failed to create study plan. Please try again.';
            }
        }
    }
    
} catch (Exception $e) {
    error_log('Study Planner Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the study planner. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Planner - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .plan-card {
            transition: transform 0.2s;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .subject-badge {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        
        .progress-thin {
            height: 8px;
        }
        
        .date-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 0.8rem;
        }
    </style>
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
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="notes.php">Notes</a>
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

    <!-- Study Planner Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="study.php">Study</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Study Planner</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="mb-0">Study Planner</h2>
                <p class="text-muted">Create and manage your personalized study plans</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                    <i class="bi bi-plus-circle me-1"></i> Create New Plan
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Study Plans -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Your Study Plans</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($study_plans)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-calendar3 text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">No Study Plans Yet</h4>
                                <p class="text-muted">Create your first study plan to get started!</p>
                                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createPlanModal">
                                    <i class="bi bi-plus-circle me-1"></i> Create New Plan
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php foreach ($study_plans as $plan): ?>
                                    <?php
                                    // Calculate progress
                                    $start_timestamp = strtotime($plan['start_date']);
                                    $end_timestamp = strtotime($plan['end_date']);
                                    $current_timestamp = time();
                                    
                                    $total_duration = $end_timestamp - $start_timestamp;
                                    $elapsed_duration = $current_timestamp - $start_timestamp;
                                    
                                    $progress_percentage = 0;
                                    if ($total_duration > 0) {
                                        $progress_percentage = min(100, max(0, ($elapsed_duration / $total_duration) * 100));
                                    }
                                    
                                    // Determine status
                                    $status = 'In Progress';
                                    $status_class = 'primary';
                                    
                                    if ($current_timestamp < $start_timestamp) {
                                        $status = 'Not Started';
                                        $status_class = 'secondary';
                                        $progress_percentage = 0;
                                    } else if ($current_timestamp > $end_timestamp) {
                                        $status = 'Completed';
                                        $status_class = 'success';
                                        $progress_percentage = 100;
                                    }
                                    
                                    // Parse subjects
                                    $plan_subjects = [];
                                    if (isset($plan['subjects'])) {
                                        $plan_subjects = is_array($plan['subjects']) ? $plan['subjects'] : json_decode($plan['subjects'], true);
                                    }
                                    if (!is_array($plan_subjects)) {
                                        $plan_subjects = [];
                                    }
                                    ?>
                                    <div class="col">
                                        <div class="card plan-card h-100">
                                            <div class="card-body">
                                                <span class="badge bg-<?php echo $status_class; ?> date-badge">
                                                    <?php echo date('M j', $start_timestamp); ?> - <?php echo date('M j, Y', $end_timestamp); ?>
                                                </span>
                                                <h5 class="card-title"><?php echo htmlspecialchars($plan['plan_name']); ?></h5>
                                                <div class="mb-3">
                                                    <span class="badge bg-<?php echo $status_class; ?>"><?php echo $status; ?></span>
                                                </div>
                                                <?php if (!empty($plan['goal'])): ?>
                                                    <p class="card-text"><strong>Goal:</strong> <?php echo htmlspecialchars($plan['goal']); ?></p>
                                                <?php endif; ?>
                                                <div class="mb-3">
                                                    <div class="progress progress-thin">
                                                        <div class="progress-bar bg-<?php echo $status_class; ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $progress_percentage; ?>%;" 
                                                             aria-valuenow="<?php echo $progress_percentage; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo round($progress_percentage); ?>% Complete</small>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Subjects:</strong><br>
                                                    <?php foreach ($plan_subjects as $subject_key): ?>
                                                        <?php 
                                                        $subject_name = isset($subjects[$level][$subject_key]) ? $subjects[$level][$subject_key] : $subject_key;
                                                        ?>
                                                        <span class="badge bg-light text-dark subject-badge"><?php echo htmlspecialchars($subject_name); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="card-footer bg-white border-top-0">
                                                <div class="d-flex gap-2">
                                                    <a href="schedule.php?plan_id=<?php echo $plan['plan_id']; ?>" class="btn btn-outline-primary flex-grow-1">
                                                        <i class="bi bi-calendar-check me-1"></i> View Schedule
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePlanModal" data-plan-id="<?php echo $plan['plan_id']; ?>" data-plan-name="<?php echo htmlspecialchars($plan['plan_name']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
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
        
        <!-- Study Tips -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Effective Study Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Create a Dedicated Study Space</h5>
                                <p>Find a quiet, comfortable, and well-lit area that you can designate as your study space. Having a consistent location helps train your brain to focus when you're in that environment.</p>
                                
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Use the Pomodoro Technique</h5>
                                <p>Study in focused 25-minute intervals (called "Pomodoros") with 5-minute breaks in between. After completing four Pomodoros, take a longer 15-30 minute break.</p>
                                
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Practice Active Recall</h5>
                                <p>Instead of passively re-reading material, actively test yourself on what you've learned. This strengthens memory and helps identify areas that need more attention.</p>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Use Spaced Repetition</h5>
                                <p>Review material at increasing intervals over time (1 day, 3 days, 1 week, etc.) to improve long-term retention and combat forgetting.</p>
                                
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Teach What You Learn</h5>
                                <p>Explaining concepts to others (or even to yourself) helps solidify your understanding and identifies gaps in your knowledge.</p>
                                
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Take Care of Your Health</h5>
                                <p>Ensure adequate sleep, proper nutrition, and regular exercise. Physical well-being significantly impacts cognitive function and learning ability.</p>
                            </div>
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
                                <h4>Need help with your studies?</h4>
                                <p class="mb-0">Our AI Assistant can help explain difficult concepts, provide practice questions, and offer personalized study tips.</p>
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
    </div>
    
    <!-- Create Plan Modal -->
    <div class="modal fade" id="createPlanModal" tabindex="-1" aria-labelledby="createPlanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createPlanModalLabel">Create New Study Plan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="plan_name" class="form-label">Plan Name*</label>
                            <input type="text" class="form-control" id="plan_name" name="plan_name" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">Start Date*</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">End Date*</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="goal" class="form-label">Study Goal</label>
                            <textarea class="form-control" id="goal" name="goal" rows="2" placeholder="What do you want to achieve with this study plan?"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Subjects*</label>
                            
                            <!-- Nav tabs for JHS/SHS -->
                            <ul class="nav nav-tabs mb-3" id="subjectTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo ($level == 'jhs') ? 'active' : ''; ?>" id="jhs-tab" data-bs-toggle="tab" data-bs-target="#jhs-subjects" type="button" role="tab" aria-controls="jhs-subjects" aria-selected="<?php echo ($level == 'jhs') ? 'true' : 'false'; ?>">JHS Subjects</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo ($level == 'shs') ? 'active' : ''; ?>" id="shs-tab" data-bs-toggle="tab" data-bs-target="#shs-subjects" type="button" role="tab" aria-controls="shs-subjects" aria-selected="<?php echo ($level == 'shs') ? 'true' : 'false'; ?>">SHS Subjects</button>
                                </li>
                            </ul>
                            
                            <!-- Tab content -->
                            <div class="tab-content" id="subjectTabContent">
                                <!-- JHS Subjects Tab -->
                                <div class="tab-pane fade <?php echo ($level == 'jhs') ? 'show active' : ''; ?>" id="jhs-subjects" role="tabpanel" aria-labelledby="jhs-tab">
                                    <h6 class="mb-2">JHS Core Subjects</h6>
                                    <div class="row mb-3">
                                        <?php foreach ($subjects['jhs'] as $subject_key => $subject_name): ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- SHS Subjects Tab -->
                                <div class="tab-pane fade <?php echo ($level == 'shs') ? 'show active' : ''; ?>" id="shs-subjects" role="tabpanel" aria-labelledby="shs-tab">
                                    <!-- Core Subjects -->
                                    <h6 class="mb-2">Core Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $core_subjects = array_slice($subjects['shs'], 0, 4, true);
                                        foreach ($core_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Science Subjects -->
                                    <h6 class="mb-2">Science Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $science_subjects = array_slice($subjects['shs'], 4, 5, true);
                                        foreach ($science_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Business Subjects -->
                                    <h6 class="mb-2">Business Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $business_subjects = array_slice($subjects['shs'], 9, 3, true);
                                        foreach ($business_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Humanities Subjects -->
                                    <h6 class="mb-2">Humanities Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $humanities_subjects = array_slice($subjects['shs'], 12, 6, true);
                                        foreach ($humanities_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Technical Subjects -->
                                    <h6 class="mb-2">Technical & Vocational Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $technical_subjects = array_slice($subjects['shs'], 18, 6, true);
                                        foreach ($technical_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Visual Arts Subjects -->
                                    <h6 class="mb-2">Visual Arts Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $arts_subjects = array_slice($subjects['shs'], 24, 6, true);
                                        foreach ($arts_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Home Economics Subjects -->
                                    <h6 class="mb-2">Home Economics Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $home_ec_subjects = array_slice($subjects['shs'], 30, 3, true);
                                        foreach ($home_ec_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Agricultural Science Subjects -->
                                    <h6 class="mb-2">Agricultural Science Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $agric_subjects = array_slice($subjects['shs'], 33, 3, true);
                                        foreach ($agric_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Additional Subjects -->
                                    <h6 class="mb-2">Additional Subjects</h6>
                                    <div class="row mb-3">
                                        <?php 
                                        $additional_subjects = array_slice($subjects['shs'], 36, 2, true);
                                        foreach ($additional_subjects as $subject_key => $subject_name): 
                                        ?>
                                            <div class="col-md-4 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="subjects[]" value="<?php echo $subject_key; ?>" id="subject_<?php echo $subject_key; ?>">
                                                    <label class="form-check-label" for="subject_<?php echo $subject_key; ?>">
                                                        <?php echo htmlspecialchars($subject_name); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="create_plan" class="btn btn-primary">Create Plan</button>
                        </div>
                    </form>
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
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted me-3">Terms of Service</a>
                    <a href="support.php" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Delete Plan Modal -->
    <div class="modal fade" id="deletePlanModal" tabindex="-1" aria-labelledby="deletePlanModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePlanModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the study plan "<span id="deletePlanName"></span>"?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone and will also delete any schedules associated with this plan.</p>
                </div>
                <div class="modal-footer">
                    <form action="study-planner.php" method="post">
                        <input type="hidden" name="action" value="delete_plan">
                        <input type="hidden" name="plan_id" id="deletePlanId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Plan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set default dates for the form
        document.addEventListener('DOMContentLoaded', function() {
            // Handle delete plan modal
            const deletePlanModal = document.getElementById('deletePlanModal');
            if (deletePlanModal) {
                deletePlanModal.addEventListener('show.bs.modal', function(event) {
                    // Button that triggered the modal
                    const button = event.relatedTarget;
                    
                    // Extract info from data attributes
                    const planId = button.getAttribute('data-plan-id');
                    const planName = button.getAttribute('data-plan-name');
                    
                    // Update the modal's content
                    document.getElementById('deletePlanId').value = planId;
                    document.getElementById('deletePlanName').textContent = planName;
                });
            }
            
            const today = new Date();
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            // Format date as YYYY-MM-DD for input[type="date"]
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Set start date to today
            startDateInput.value = formatDate(today);
            
            // Set end date to 30 days from today
            const endDate = new Date();
            endDate.setDate(today.getDate() + 30);
            endDateInput.value = formatDate(endDate);
        });
    </script>
</body>
</html>
