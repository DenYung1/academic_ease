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

// Initialize variables
$error = '';
$user = null;
$schedule_items = [];
$current_plan = null;
$success_message = '';

// Get user data
try {
    $filter = "user_id=eq." . $_SESSION['user_id'];
    $users = supabaseRequest("users?$filter", 'GET', null, true);
    
    if (empty($users)) {
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
        header('Location: subscribe.php?expired=1');
        exit;
    }
} catch (Exception $e) {
    error_log('User Data Error: ' . $e->getMessage());
    $error = 'Failed to retrieve user data. Please try again.';
}

// Process schedule creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    $schedule_items = isset($_POST['schedule_items']) ? $_POST['schedule_items'] : [];
    $plan_id = isset($_POST['plan_id']) ? $_POST['plan_id'] : null;
    
    // Validate and clean schedule items
    $valid_items = [];
    foreach ($schedule_items as $item) {
        if (!empty($item['subject']) && !empty($item['day']) && 
            !empty($item['start_time']) && !empty($item['end_time'])) {
            $valid_items[] = $item;
        }
    }
    
    try {
        // Delete existing schedule items
        $delete_filter = "user_id=eq." . $_SESSION['user_id'];
        if ($plan_id) {
            $delete_filter .= "&plan_id=eq." . urlencode($plan_id);
        }
        supabaseDelete('study_schedules', $delete_filter);
        
        // Insert new schedule items
        $inserted = 0;
        foreach ($valid_items as $item) {
            $schedule_id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            
            $schedule_data = [
                'schedule_id' => $schedule_id,
                'user_id' => $_SESSION['user_id'],
                'subject' => $item['subject'],
                'day_of_week' => $item['day'],
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'notes' => isset($item['notes']) ? $item['notes'] : '',
                'created_at' => date('c')
            ];
            
            if ($plan_id) {
                $schedule_data['plan_id'] = $plan_id;
            }
            
            // Pass as single item, not in array
            $result = supabaseRequest('study_schedules', 'POST', $schedule_data, true);
            
            if (!isset($result['error'])) {
                $inserted++;
            } else {
                error_log('Schedule Insert Error: ' . json_encode($result['error']));
            }
        }
        
        if ($inserted > 0) {
            $success_message = "Schedule updated successfully. Added $inserted items.";
            
            // Refresh schedule items list
            $refresh_filter = "user_id=eq." . $_SESSION['user_id'];
            if ($plan_id) {
                $refresh_filter .= "&plan_id=eq." . urlencode($plan_id);
            }
            $schedule_items = supabaseRequest(
                "study_schedules?$refresh_filter&order=day_of_week.asc,start_time.asc",
                'GET',
                null,
                true
            );
        } else {
            $success_message = "Schedule cleared successfully.";
        }
    } catch (Exception $e) {
        error_log('Schedule Update Error: ' . $e->getMessage());
        $error = 'Failed to update schedule. Please try again.';
    }
}

// Get current schedule
try {
    // Check if viewing a specific plan
    $plan_id = isset($_GET['plan_id']) ? $_GET['plan_id'] : null;
    
    if ($plan_id) {
        // Get plan details
        $plan_filter = "plan_id=eq." . urlencode($plan_id) . "&user_id=eq." . $_SESSION['user_id'];
        $plan_result = supabaseRequest("study_plans?$plan_filter", 'GET', null, true);
        
        if (!empty($plan_result)) {
            $current_plan = $plan_result[0];
            
            // Get schedule items for this plan
            $schedule_filter = "plan_id=eq.$plan_id&user_id=eq." . $_SESSION['user_id'];
            $schedule_items = supabaseRequest(
                "study_schedules?$schedule_filter&order=day_of_week.asc,start_time.asc", 
                'GET', 
                null, 
                true
            );
        } else {
            header('Location: study-planner.php?error=invalid_plan');
            exit;
        }
    } else {
        // Get all schedule items for user
        $schedule_filter = "user_id=eq." . $_SESSION['user_id'];
        $schedule_items = supabaseRequest(
            "study_schedules?$schedule_filter&order=day_of_week.asc,start_time.asc", 
            'GET', 
            null, 
            true
        );
    }
} catch (Exception $e) {
    error_log('Schedule Retrieval Error: ' . $e->getMessage());
    $error = 'Failed to retrieve schedule. Please try again.';
    $schedule_items = [];
}

// Get user's academic level
$school_level = isset($user['school_level']) ? strtolower($user['school_level']) : '';

// Define all subjects in categories
$all_subjects = [
    'core' => [
        'mathematics' => 'Mathematics',
        'english' => 'English',
        'integrated_science' => 'Integrated Science',
        'social_studies' => 'Social Studies',
        'ict' => 'ICT'
    ],
    'jhs_specific' => [
        'religious_moral_education' => 'Religious and Moral Education',
        'ghanaian_language' => 'Ghanaian Language',
        'french_jhs' => 'French (JHS)',
        'creative_arts' => 'Creative Arts',
        'career_technology' => 'Career Technology',
        'physical_education' => 'Physical Education',
        'bdt' => 'Basic Design and Technology'
    ],
    'science' => [
        'chemistry' => 'Chemistry',
        'physics' => 'Physics',
        'biology' => 'Biology',
        'elective_mathematics' => 'Elective Mathematics',
        'further_mathematics' => 'Further Mathematics'
    ],
    'business' => [
        'financial_accounting' => 'Financial Accounting',
        'business_management' => 'Business Management',
        'cost_accounting' => 'Cost Accounting',
        'economics' => 'Economics'
    ],
    'humanities' => [
        'government' => 'Government',
        'literature' => 'Literature in English',
        'history' => 'History',
        'geography' => 'Geography',
        'french_shs' => 'French (SHS)',
        'religious_studies' => 'Religious Studies'
    ],
    'technical' => [
        'technical_drawing' => 'Technical Drawing',
        'building_construction' => 'Building Construction',
        'woodwork' => 'Woodwork',
        'metalwork' => 'Metalwork',
        'auto_mechanics' => 'Auto Mechanics',
        'electrical_engineering' => 'Electrical Engineering'
    ],
    'visual_arts' => [
        'visual_arts' => 'Visual Arts',
        'graphic_design' => 'Graphic Design',
        'picture_making' => 'Picture Making',
        'ceramics' => 'Ceramics',
        'sculpture' => 'Sculpture',
        'textiles' => 'Textiles'
    ],
    'home_economics' => [
        'food_nutrition' => 'Food and Nutrition',
        'clothing_textiles' => 'Clothing and Textiles',
        'management_living' => 'Management in Living'
    ],
    'agriculture' => [
        'agricultural_science' => 'Agricultural Science',
        'crop_husbandry' => 'Crop Husbandry and Horticulture',
        'animal_husbandry' => 'Animal Husbandry'
    ],
    'activities' => [
        'break' => 'Break/Rest',
        'revision' => 'Revision',
        'study_group' => 'Study Group'
    ]
];

// Define days of week
$days = [
    'monday' => 'Monday',
    'tuesday' => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday' => 'Thursday',
    'friday' => 'Friday',
    'saturday' => 'Saturday',
    'sunday' => 'Sunday'
];

// Render the navigation menu first
?>
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
                    <a class="nav-link active" href="schedule.php">Schedule</a>
                </li>
            </ul>
            
            <!-- Desktop: User Account Dropdown (Inside Collapse) -->
            <div class="d-none d-lg-flex align-items-center">
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

<?php
// Continue with the rest of the existing code
require_once 'views/schedule_view.php';
