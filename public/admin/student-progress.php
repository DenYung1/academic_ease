<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_session.php';

// Verify admin is logged in with secure session
check_admin_login();

// Get admin data
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Initialize variables
$students = [];
$selected_student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$student_data = null;
$quiz_results = [];
$study_sessions = [];
$subject_progress = [];
$success_message = '';
$error_message = '';

// Get all students with improved error handling and logging
try {
    error_log('Admin Student Progress: Fetching all students');
    $students = supabaseRequest("users?user_type=eq.student&order=full_name.asc", 'GET', null, true);
    error_log('Admin Student Progress: Successfully retrieved ' . count($students) . ' students');
    
    // Add additional metadata for each student
    foreach ($students as &$student) {
        // Track when this student data was last viewed by an admin
        $tracking_data = [
            'last_viewed_by_admin' => date('c'),
            'admin_id' => $admin_id
        ];
        
        // If student already has admin_tracking metadata, merge it
        if (isset($student['metadata']) && !empty($student['metadata'])) {
            $metadata = json_decode($student['metadata'], true) ?: [];
            if (isset($metadata['admin_tracking'])) {
                $metadata['admin_tracking'][] = $tracking_data;
                // Keep only the last 10 admin views
                if (count($metadata['admin_tracking']) > 10) {
                    $metadata['admin_tracking'] = array_slice($metadata['admin_tracking'], -10);
                }
            } else {
                $metadata['admin_tracking'] = [$tracking_data];
            }
        } else {
            $metadata = ['admin_tracking' => [$tracking_data]];
        }
        
        // Update student metadata in the database
        try {
            $update_data = ['metadata' => json_encode($metadata)];
            supabaseRequest("users?user_id=eq." . urlencode($student['user_id']), 'PATCH', $update_data, true);
            error_log('Admin Student Progress: Updated tracking metadata for student ' . $student['user_id']);
        } catch (Exception $e) {
            error_log('Admin Student Progress: Failed to update tracking metadata: ' . $e->getMessage());
        }
    }
    unset($student); // Break the reference
} catch (Exception $e) {
    error_log('Admin Student Progress Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading students.';
}

// Get specific student data if selected with enhanced validation and logging
if ($selected_student_id) {
    error_log("Admin Student Progress: Retrieving detailed data for student ID: $selected_student_id");
    
    // Validate student ID format before making the request
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $selected_student_id)) {
        error_log("Admin Student Progress: Invalid student ID format: $selected_student_id");
        $error_message = 'Invalid student ID format.';
    } else {
        try {
            // Get student details with proper URL encoding
            $endpoint = "users?user_id=eq." . urlencode($selected_student_id);
            error_log("Admin Student Progress: Fetching student data from endpoint: $endpoint");
            $student_data_result = supabaseRequest($endpoint, 'GET', null, true);
            
            if (!empty($student_data_result)) {
                $student_data = $student_data_result[0];
                error_log("Admin Student Progress: Successfully retrieved data for student: {$student_data['full_name']}");
                
                // Record this view in activity logs
                try {
                    $log_data = [
                        'admin_id' => $admin_id,
                        'admin_name' => $admin_name,
                        'action' => 'view_student_progress',
                        'target_id' => $selected_student_id,
                        'target_name' => $student_data['full_name'],
                        'timestamp' => date('c'),
                        'ip_address' => $_SERVER['REMOTE_ADDR']
                    ];
                    
                    // Log to admin_activity table if it exists
                    supabaseRequest("admin_activity", 'POST', $log_data, true);
                    error_log("Admin Student Progress: Logged admin view activity");
                } catch (Exception $e) {
                    error_log("Admin Student Progress: Failed to log admin activity: " . $e->getMessage());
                }
                
                // Get quiz results for the student with error handling
                try {
                    $quiz_endpoint = "quiz_results?user_id=eq." . urlencode($selected_student_id) . "&order=completion_date.desc";
                    $quiz_results = supabaseRequest($quiz_endpoint, 'GET', null, true);
                    error_log("Admin Student Progress: Retrieved " . count($quiz_results) . " quiz results");
                } catch (Exception $e) {
                    error_log("Admin Student Progress: Error fetching quiz results: " . $e->getMessage());
                    $quiz_results = [];
                }
                
                // Get study sessions for the student with error handling
                try {
                    $sessions_endpoint = "study_sessions?user_id=eq." . urlencode($selected_student_id) . "&order=start_time.desc";
                    $study_sessions = supabaseRequest($sessions_endpoint, 'GET', null, true);
                    error_log("Admin Student Progress: Retrieved " . count($study_sessions) . " study sessions");
                } catch (Exception $e) {
                    error_log("Admin Student Progress: Error fetching study sessions: " . $e->getMessage());
                    $study_sessions = [];
                }
                
                // Get subject progress data from real student data
                // Use the same function we use for the parent dashboard to get consistent data
                require_once '../../includes/parent_child_helper.php';
                
                // Get comprehensive student progress data
                $student_progress_data = get_student_progress($selected_student_id);
                
                // Extract subject performance data
                $subject_performance = isset($student_progress_data['subject_performance']) ? 
                                      $student_progress_data['subject_performance'] : [];
                
                // Format the data for the admin dashboard
                $subject_progress = [];
                
                // Define the subjects we want to track
                $tracked_subjects = ['Mathematics', 'Science', 'English', 'Social Studies', 'ICT'];
                
                foreach ($tracked_subjects as $subject) {
                    // Get the progress percentage from real data or default to 0
                    $progress_percentage = isset($subject_performance[$subject]) ? 
                                          $subject_performance[$subject] : 0;
                    
                    // Count quizzes taken for this subject
                    $quizzes_taken = count(array_filter($quiz_results, function($result) use ($subject) {
                        $quiz_subject = isset($result['quiz']['subject']) ? strtolower($result['quiz']['subject']) : '';
                        return stripos($quiz_subject, strtolower($subject)) !== false;
                    }));
                    
                    // Calculate average score
                    $avg_score = calculateAverageScore($quiz_results, strtolower($subject));
                    
                    // Store the data
                    $subject_progress[$subject] = [
                        'progress' => $progress_percentage,
                        'quizzes_taken' => $quizzes_taken,
                        'avg_score' => $avg_score
                    ];
                }
                
                // Add usage statistics from the student progress data
                $usage_stats = isset($student_progress_data['usage_stats']) ? 
                              $student_progress_data['usage_stats'] : [
                                  'hours_spent' => 0,
                                  'questions_answered' => 0,
                                  'study_sessions' => 0,
                                  'ai_assistant_usage' => 0,
                                  'last_active' => ''
                              ];
                
                // Add improvement areas from the student progress data
                $improvement_areas = isset($student_progress_data['improvement_areas']) ? 
                                   $student_progress_data['improvement_areas'] : [];
            }
        } catch (Exception $e) {
            error_log('Admin Student Progress Error: ' . $e->getMessage());
            $error_message = 'An error occurred while loading student data.';
        }
    }
}

// Calculate average score for a subject
function calculateAverageScore($quiz_results, $subject) {
    $scores = array_filter($quiz_results, function($result) use ($subject) {
        return isset($result['quiz']['subject']) && $result['quiz']['subject'] === $subject;
    });
    
    if (empty($scores)) {
        return 0;
    }
    
    $total = array_sum(array_column($scores, 'percentage'));
    return round($total / count($scores));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reset student progress
    if (isset($_POST['action']) && $_POST['action'] === 'reset_progress') {
        $student_id = $_POST['student_id'];
        
        try {
            // Delete quiz results for the student
            supabaseRequest("quiz_results?user_id=eq.$student_id", 'DELETE', null, true);
            
            // Delete study sessions for the student
            supabaseRequest("study_sessions?user_id=eq.$student_id", 'DELETE', null, true);
            
            // Reset any other progress-related data
            supabaseRequest("conversations?user_id=eq.$student_id", 'DELETE', null, true);
            
            $success_message = 'Student progress has been reset successfully.';
            
            // Refresh data
            $quiz_results = [];
            $study_sessions = [];
            
            // Reset subject progress
            foreach ($subject_progress as $subject => $data) {
                $subject_progress[$subject]['progress'] = 0;
                $subject_progress[$subject]['quizzes_taken'] = 0;
                $subject_progress[$subject]['avg_score'] = 0;
            }
        } catch (Exception $e) {
            error_log('Admin Reset Progress Error: ' . $e->getMessage());
            $error_message = 'An error occurred while resetting student progress.';
        }
    }
    
    // Manage student subscription
    if (isset($_POST['action']) && $_POST['action'] === 'manage_subscription') {
        $student_id = $_POST['student_id'];
        $subscription_status = $_POST['subscription_status'];
        $subscription_plan = $_POST['subscription_plan'];
        $subscription_end_date = $_POST['subscription_end_date'];
        
        try {
            // Format the date properly for database storage
            $formatted_date = date('Y-m-d H:i:s', strtotime($subscription_end_date));
            
            // Update user subscription data
            $updateData = [
                'subscription_status' => $subscription_status,
                'subscription_plan' => $subscription_plan,
                'subscription_end_date' => $formatted_date
            ];
            
            // Add error logging to help diagnose issues
            error_log('Admin Subscription Update Data: ' . json_encode($updateData));
            
            supabaseRequest("users?user_id=eq.$student_id", 'PATCH', $updateData, true);
            
            $success_message = 'Student subscription has been updated successfully.';
            
            // Refresh student data
            $student_data_result = supabaseRequest("users?user_id=eq.$student_id", 'GET', null, true);
            if (!empty($student_data_result)) {
                $student_data = $student_data_result[0];
            }
        } catch (Exception $e) {
            error_log('Admin Manage Subscription Error: ' . $e->getMessage());
            $error_message = 'An error occurred while updating student subscription.';
        }
    }
    
    // Send message to student
    if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
        $student_id = $_POST['student_id'];
        $message = trim($_POST['message']);
        
        if (empty($message)) {
            $error_message = 'Please enter a message to send.';
        } else {
            try {
                $messageData = [
                    'sender_id' => $admin_id,
                    'receiver_id' => $student_id,
                    'message' => $message,
                    'is_read' => false,
                    'sent_at' => date('c')
                ];
                
                supabaseRequest('messages', 'POST', $messageData, true);
                
                $success_message = 'Message sent successfully.';
            } catch (Exception $e) {
                error_log('Admin Send Message Error: ' . $e->getMessage());
                $error_message = 'An error occurred while sending the message.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress - Academic Ease Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .sidebar {
            background-color: #343a40;
            color: white;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu {
            padding: 15px 0;
        }
        
        .sidebar-menu a {
            color: rgba(255, 255, 255, 0.8);
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .progress-card {
            transition: transform 0.2s;
        }
        
        .progress-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0">Academic Ease</h4>
            <p class="mb-0 text-muted">Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="index.php">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="users.php">
                <i class="bi bi-people me-2"></i> Users
            </a>
            <a href="formula-sheets.php">
                <i class="bi bi-journal-text me-2"></i> Formula Sheets
            </a>
            <a href="past-questions.php">
                <i class="bi bi-file-earmark-text me-2"></i> Past Questions
            </a>
            <a href="quizzes.php">
                <i class="bi bi-question-circle me-2"></i> Quizzes
            </a>
            <a href="generate-quizzes.php">
                <i class="bi bi-lightning me-2"></i> Generate Quizzes
            </a>
            <a href="study-materials.php">
                <i class="bi bi-book me-2"></i> Study Materials
            </a>
            <a href="student-progress.php" class="active">
                <i class="bi bi-graph-up me-2"></i> Student Progress
            </a>
            <a href="student-view.php">
                <i class="bi bi-eye me-2"></i> Student View
            </a>
            <a href="bulk-upload.php">
                <i class="bi bi-cloud-upload me-2"></i> Bulk Upload
            </a>
            <a href="settings.php">
                <i class="bi bi-gear me-2"></i> Settings
            </a>
            <a href="logout.php" class="mt-4 text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a></div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Student Progress Monitoring</h2>
            </div>
            
            <!-- Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Student Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form action="student-progress.php" method="get">
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Choose a student...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['user_id']; ?>" <?php echo $selected_student_id == $student['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i> View Progress
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($student_data): ?>
                <!-- Student Information -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_data['full_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($student_data['email']); ?></p>
                                <p><strong>User Type:</strong> <?php echo ucfirst($student_data['user_type']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Subscription Plan:</strong> <?php echo ucfirst($student_data['subscription_plan'] ?? 'None'); ?></p>
                                <p><strong>Subscription Status:</strong> 
                                    <span class="badge <?php echo ($student_data['subscription_status'] === 'active') ? 'bg-success' : (($student_data['subscription_status'] === 'trial') ? 'bg-info' : 'bg-danger'); ?>">
                                        <?php echo ucfirst($student_data['subscription_status'] ?? 'None'); ?>
                                    </span>
                                </p>
                                <p><strong>Subscription End Date:</strong> 
                                    <?php echo isset($student_data['subscription_end_date']) ? date('M d, Y', strtotime($student_data['subscription_end_date'])) : 'N/A'; ?>
                                </p>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#manageSubscriptionModal">
                                <i class="bi bi-credit-card me-1"></i> Manage Subscription
                            </button>
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
                                <i class="bi bi-envelope me-1"></i> Send Message
                            </button>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#resetProgressModal">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Progress
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Subject Progress -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Subject Progress</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <canvas id="subjectProgressChart" height="250"></canvas>
                            </div>
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Quizzes</th>
                                                <th>Avg. Score</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subject_progress as $subject => $data): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($subject); ?></td>
                                                    <td><?php echo $data['quizzes_taken']; ?></td>
                                                    <td><?php echo $data['avg_score']; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quiz Results -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Quiz Results</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($quiz_results)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No quiz results found for this student.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="quizResultsTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Quiz</th>
                                            <th>Subject</th>
                                            <th>Score</th>
                                            <th>Percentage</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quiz_results as $result): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($result['quiz']['title'] ?? 'Unknown Quiz'); ?></td>
                                                <td><?php echo ucfirst($result['quiz']['subject'] ?? 'Unknown'); ?></td>
                                                <td><?php echo $result['score']; ?> / <?php echo $result['max_score']; ?></td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar <?php echo $result['percentage'] >= 70 ? 'bg-success' : ($result['percentage'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>" 
                                                             role="progressbar" 
                                                             style="width: <?php echo $result['percentage']; ?>%;" 
                                                             aria-valuenow="<?php echo $result['percentage']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="100">
                                                            <?php echo $result['percentage']; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo date('M d, Y H:i', strtotime($result['completion_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Usage Statistics -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Usage Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8">
                                <canvas id="usageStatsChart" height="250"></canvas>
                            </div>
                            <div class="col-lg-4">
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Hours spent
                                        <span class="badge bg-primary rounded-pill"><?php echo $usage_stats['hours_spent']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Questions answered
                                        <span class="badge bg-primary rounded-pill"><?php echo $usage_stats['questions_answered']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Study sessions
                                        <span class="badge bg-primary rounded-pill"><?php echo $usage_stats['study_sessions']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        AI Assistant usage
                                        <span class="badge bg-success rounded-pill"><?php echo $usage_stats['ai_assistant_usage']; ?></span>
                                    </li>
                                    <?php if (!empty($usage_stats['last_active'])): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Last active: 
                                        <?php 
                                        try {
                                            $last_active_date = new DateTime($usage_stats['last_active']);
                                            echo $last_active_date->format('M j, Y g:i A');
                                        } catch (Exception $e) {
                                            echo $usage_stats['last_active'];
                                        }
                                        ?>
                                        </small>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Improvement Areas -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Improvement Areas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($improvement_areas)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                No improvement areas identified. The student is performing well in all subjects.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($improvement_areas as $area): 
                                    $subject = isset($area['subject']) ? $area['subject'] : 'General';
                                    $average = isset($area['average']) ? $area['average'] : 0;
                                    $recommendation = isset($area['recommendation']) ? $area['recommendation'] : 'Needs more practice';
                                    $severity = isset($area['severity']) ? $area['severity'] : 'medium';
                                    
                                    // Set appropriate icon and color based on severity
                                    $icon_class = 'bi-exclamation-triangle text-warning';
                                    $bg_class = 'bg-warning bg-opacity-10';
                                    
                                    if ($severity === 'high') {
                                        $icon_class = 'bi-exclamation-circle-fill text-danger';
                                        $bg_class = 'bg-danger bg-opacity-10';
                                    } else if ($severity === 'low') {
                                        $icon_class = 'bi-info-circle text-info';
                                        $bg_class = 'bg-info bg-opacity-10';
                                    }
                                ?>
                                <div class="list-group-item <?php echo $bg_class; ?> border-0 mb-2 rounded">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <i class="bi <?php echo $icon_class; ?> me-2"></i>
                                            <?php echo htmlspecialchars($subject); ?>
                                        </h6>
                                        <small>Score: <?php echo $average; ?>%</small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($recommendation); ?></p>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar <?php echo ($severity === 'high') ? 'bg-danger' : (($severity === 'low') ? 'bg-info' : 'bg-warning'); ?>" 
                                             role="progressbar" style="width: <?php echo $average; ?>%" 
                                             aria-valuenow="<?php echo $average; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Study Sessions -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Study Sessions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($study_sessions)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No study sessions found for this student.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="studySessionsTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Topic</th>
                                            <th>Duration</th>
                                            <th>Start Time</th>
                                            <th>End Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($study_sessions as $session): ?>
                                            <tr>
                                                <td><?php echo ucfirst($session['subject'] ?? 'General'); ?></td>
                                                <td><?php echo htmlspecialchars($session['topic'] ?? 'General Study'); ?></td>
                                                <td><?php echo $session['duration_minutes']; ?> minutes</td>
                                                <td><?php echo date('M d, Y H:i', strtotime($session['start_time'])); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($session['end_time'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Manage Subscription Modal -->
                <div class="modal fade" id="manageSubscriptionModal" tabindex="-1" aria-labelledby="manageSubscriptionModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="manageSubscriptionModalLabel">Manage Subscription for <?php echo htmlspecialchars($student_data['full_name']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="student-progress.php?student_id=<?php echo $selected_student_id; ?>" method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="manage_subscription">
                                    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="subscription_status" class="form-label">Subscription Status</label>
                                        <select class="form-select" id="subscription_status" name="subscription_status" required>
                                            <option value="active" <?php echo ($student_data['subscription_status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="trial" <?php echo ($student_data['subscription_status'] === 'trial') ? 'selected' : ''; ?>>Trial</option>
                                            <option value="expired" <?php echo ($student_data['subscription_status'] === 'expired') ? 'selected' : ''; ?>>Expired</option>
                                            <option value="pending" <?php echo ($student_data['subscription_status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="subscription_plan" class="form-label">Subscription Plan</label>
                                        <select class="form-select" id="subscription_plan" name="subscription_plan" required>
                                            <option value="trial" <?php echo ($student_data['subscription_plan'] === 'trial') ? 'selected' : ''; ?>>Free Trial</option>
                                            <option value="weekly" <?php echo ($student_data['subscription_plan'] === 'weekly') ? 'selected' : ''; ?>>Weekly Plan</option>
                                            <option value="monthly" <?php echo ($student_data['subscription_plan'] === 'monthly') ? 'selected' : ''; ?>>Monthly Plan</option>
                                            <option value="termly" <?php echo ($student_data['subscription_plan'] === 'termly') ? 'selected' : ''; ?>>Termly Plan</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="subscription_end_date" class="form-label">Subscription End Date</label>
                                        <input type="date" class="form-control" id="subscription_end_date" name="subscription_end_date" value="<?php echo isset($student_data['subscription_end_date']) ? date('Y-m-d', strtotime($student_data['subscription_end_date'])) : date('Y-m-d', strtotime('+30 days')); ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success">Update Subscription</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Send Message Modal -->
                <div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="sendMessageModalLabel">Send Message to <?php echo htmlspecialchars($student_data['full_name']); ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="student-progress.php?student_id=<?php echo $selected_student_id; ?>" method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                        <div class="form-text">This message will be sent to the student's inbox.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Send Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Reset Progress Modal -->
                <div class="modal fade" id="resetProgressModal" tabindex="-1" aria-labelledby="resetProgressModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="resetProgressModalLabel">Reset Progress</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to reset all progress for <strong><?php echo htmlspecialchars($student_data['full_name']); ?></strong>?</p>
                                <p class="text-danger mb-0"><strong>Warning:</strong> This will delete all quiz results and study sessions for this student. This action cannot be undone.</p>
                            </div>
                            <div class="modal-footer">
                                <form action="student-progress.php?student_id=<?php echo $selected_student_id; ?>" method="post">
                                    <input type="hidden" name="action" value="reset_progress">
                                    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Reset Progress</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!$student_data): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Select a student to view their progress.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#quizResultsTable').DataTable({
                order: [[4, 'desc']], // Sort by date by default
                pageLength: 10,
                language: {
                    search: "Search results:",
                    lengthMenu: "Show _MENU_ results per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ results"
                }
            });
            
            $('#studySessionsTable').DataTable({
                order: [[3, 'desc']], // Sort by start time by default
                pageLength: 10,
                language: {
                    search: "Search sessions:",
                    lengthMenu: "Show _MENU_ sessions per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ sessions"
                }
            });
            
            // Initialize Usage Statistics Chart if data exists
            if (document.getElementById('usageStatsChart')) {
                const usageStatsCtx = document.getElementById('usageStatsChart').getContext('2d');
                const usageStatsChart = new Chart(usageStatsCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Hours Spent', 'Questions Answered', 'Study Sessions', 'AI Assistant Usage'],
                        datasets: [{
                            label: 'Usage Statistics',
                            data: [
                                <?php echo $usage_stats['hours_spent']; ?>,
                                <?php echo $usage_stats['questions_answered']; ?>,
                                <?php echo $usage_stats['study_sessions']; ?>,
                                <?php echo $usage_stats['ai_assistant_usage']; ?>
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 159, 64, 0.7)',
                                'rgba(75, 192, 192, 0.7)',
                                'rgba(153, 102, 255, 0.7)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 159, 64, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Student Usage Statistics'
                            },
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += context.raw;
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
            
            // Initialize Subject Progress Chart if data exists and we have subject progress data
            if (document.getElementById('subjectProgressChart') && Object.keys(<?php echo json_encode($subject_progress); ?>).length > 0) {
                const subjectProgressCtx = document.getElementById('subjectProgressChart').getContext('2d');
                const subjectProgressChart = new Chart(subjectProgressCtx, {
                    type: 'radar',
                    data: {
                        labels: [
                            <?php 
                            foreach ($subject_progress as $subject => $data) {
                                echo "'" . addslashes($subject) . "', ";
                            }
                            ?>
                        ],
                        datasets: [{
                            label: 'Progress (%)',
                            data: [
                                <?php 
                                foreach ($subject_progress as $subject => $data) {
                                    echo $data['progress'] . ", ";
                                }
                                ?>
                            ],
                            backgroundColor: 'rgba(26, 115, 232, 0.2)',
                            borderColor: 'rgba(26, 115, 232, 1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(26, 115, 232, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(26, 115, 232, 1)'
                        },
                        {
                            label: 'Average Score (%)',
                            data: [
                                <?php 
                                foreach ($subject_progress as $subject => $data) {
                                    echo $data['avg_score'] . ", ";
                                }
                                ?>
                            ],
                            backgroundColor: 'rgba(76, 175, 80, 0.2)',
                            borderColor: 'rgba(76, 175, 80, 1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(76, 175, 80, 1)',
                            pointBorderColor: '#fff',
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(76, 175, 80, 1)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Subject Performance Overview'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.dataset.label + ': ' + context.raw + '%';
                                    }
                                }
                            }
                        },
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
            }
        });
    </script>
</body>
</html>