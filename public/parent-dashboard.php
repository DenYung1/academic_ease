<?php
// Include required files
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/parent_child_helper.php'; // Include the parent-child helper functions
require_once '../includes/session_helper.php'; // Include session helper for timeout checks

// Use the session helper to check login status and handle timeouts
check_user_login();

// Additionally check if user is a parent
if ($_SESSION['user_type'] !== 'parent') {
    header('Location: login.php?error=parent_only');
    exit;
}

// Initialize variables
$parent_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Parent';
$parent_id = $_SESSION['user_id'];
$error = '';
$success = '';
$linked_students = [];

// Get existing linked students
$linked_students = [];

// Check if we're coming back after linking a student
$force_refresh = isset($_SESSION['force_refresh']) && $_SESSION['force_refresh'];
if ($force_refresh) {
    // Clear the flag
    unset($_SESSION['force_refresh']);
    
    // Clear any existing linked students in the session
    unset($_SESSION['linked_students']);
    unset($_SESSION['linked_children']);
    unset($_SESSION['has_linked_children']);
}

// Get linked children using the helper function
$children_data = get_linked_children($parent_id);
error_log('Parent Dashboard - Retrieved linked children using helper: ' . json_encode($children_data));

// DEBUG: Display the raw data for linked children
echo "<!-- DEBUG: Linked children data: " . htmlspecialchars(json_encode($children_data)) . " -->";


// Format the linked students data
$linked_students = [];
foreach ($children_data as $child) {
    // Make sure we have valid data before adding to the array
    if (is_array($child) && isset($child['user_id'])) {
        $linked_students[] = [
            'user_id' => $child['user_id'],
            'full_name' => isset($child['full_name']) ? $child['full_name'] : 'Unknown',
            'school_level' => isset($child['school_level']) ? $child['school_level'] : '',
            'linked_at' => date('c')
        ];
    }
}

// Get progress data for each linked student
foreach ($linked_students as $key => $student) {
    // Get progress data for this student
    $progress_data = get_student_progress($student['user_id']);
    $linked_students[$key]['progress'] = $progress_data;
    error_log('Parent Dashboard - Retrieved progress for student ' . $student['user_id'] . ': ' . json_encode($progress_data));
}

// Store the linked students in the session for future use
if (!empty($linked_students)) {
    $_SESSION['linked_students'] = $linked_students;
    $_SESSION['has_linked_children'] = true;
    error_log('Parent Dashboard - Found ' . count($linked_students) . ' linked children');
} else {
    error_log('Parent Dashboard - No linked children found');
}

// Debug log the final list of linked students
error_log('Parent Dashboard - Final linked students: ' . json_encode($linked_students));

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    if ($action === 'unlink_student') {
        // Handle unlinking a student
        $student_id = isset($_POST['student_id']) ? $_POST['student_id'] : '';
        
        if (empty($student_id)) {
            $error = 'Invalid student ID.';
        } else {
            // Find the student in the linked students array
            $student_found = false;
            $student_name = '';
            
            // First, get the student details from the database
            $student_data = supabaseRequest('users?user_id=eq.' . $student_id, 'GET', null, true);
            if (!empty($student_data) && isset($student_data[0])) {
                $student_name = $student_data[0]['full_name'];
                $student_found = true;
            }
            
            // Make sure parent_child_helper.php is included
            require_once '../includes/parent_child_helper.php';
            
            // Use the helper function to unlink the child from the parent
            $unlink_result = unlink_child_from_parent($parent_id, $student_id);
            error_log('Unlink result using helper: ' . ($unlink_result ? 'success' : 'failed'));
            
            // Force a database refresh regardless of the result
            // Clear any existing session data to force a refresh from the database
            unset($_SESSION['linked_students']);
            unset($_SESSION['linked_children']);
            unset($_SESSION['has_linked_children']);
            $_SESSION['force_refresh'] = true;
            
            if (!$unlink_result) {
                // Even if the unlink failed in the function, the direct approach might have worked
                // Let's check if the student is still linked
                $check_result = get_linked_children($parent_id);
                $still_linked = false;
                
                foreach ($check_result as $child) {
                    if (isset($child['user_id']) && $child['user_id'] === $student_id) {
                        $still_linked = true;
                        break;
                    }
                }
                
                if ($still_linked) {
                    $error = 'Failed to unlink student. Please try again.';
                } else {
                    // The student was successfully unlinked despite the function returning false
                    $_SESSION['success_message'] = $student_found ? "Student '$student_name' unlinked successfully!" : 'Student unlinked successfully!';
                    header('Location: parent-dashboard.php');
                    exit;
                }
            } else {
                // Store success message in session
                $_SESSION['success_message'] = $student_found ? "Student '$student_name' unlinked successfully!" : 'Student unlinked successfully!';
                
                // Refresh page to show updated list
                header('Location: parent-dashboard.php');
                exit;
            }
        }
    } else if ($action === 'direct_link') {
        // Direct linking for debugging purposes
        $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
        
        if (empty($student_id)) {
            $error = 'Please enter a student ID.';
        } else {
            // Check if student exists
            $student_data = supabaseRequest("users?user_id=eq." . urlencode($student_id), 'GET', null, true);
            
            if (empty($student_data)) {
                $error = 'No student found with ID: ' . $student_id;
            } else {
                // Force direct linking
                $parent_update = [
                    'school_name' => 'CHILD:' . $student_id
                ];
                
                $parent_update_result = supabaseRequest("users?user_id=eq." . urlencode($parent_id), 'PATCH', $parent_update, true);
                
                $child_update = [
                    'school_name' => 'PARENT:' . $parent_id
                ];
                
                $child_update_result = supabaseRequest("users?user_id=eq." . urlencode($student_id), 'PATCH', $child_update, true);
                
                if (!empty($parent_update_result) || !empty($child_update_result)) {
                    $_SESSION['success_message'] = 'Student directly linked successfully!';
                    
                    // Clear session data
                    unset($_SESSION['linked_students']);
                    unset($_SESSION['linked_children']);
                    unset($_SESSION['has_linked_children']);
                    
                    // Refresh page
                    header('Location: parent-dashboard.php?debug=true');
                    exit;
                } else {
                    $error = 'Failed to directly link student. Please try again.';
                }
            }
        }
    } else if ($action === 'link_existing') {
        // Link existing student account
        $student_email = trim($_POST['student_email']);
        
        if (empty($student_email)) {
            $error = 'Please enter the student\'s email address.';
        } else {
            // Find the student by email
            $filter = "email=eq." . urlencode($student_email) . "&user_type=eq.student";
            $student_data = supabaseRequest("users?$filter", 'GET', null, true);
            
            error_log("Linking: Looking for student with email: $student_email");
            error_log("Linking: Student data: " . json_encode($student_data));
            
            if (empty($student_data) || !isset($student_data[0]['user_id'])) {
                $error = 'No student account found with email: ' . $student_email . '. Please check the email address.';
            } else {
                $student = $student_data[0];
                $student_id = $student['user_id'];
                $student_name = isset($student['full_name']) ? $student['full_name'] : 'Unknown';
                
                // Use the improved link_child_to_parent function from parent_child_helper.php
                require_once '../includes/parent_child_helper.php';
                
                // Attempt to link the student to the parent
                $success = link_child_to_parent($parent_id, $student_id);
                error_log("Linking: Result from link_child_to_parent: " . ($success ? "SUCCESS" : "FAILURE"));
                
                if (!$success) {
                    $error = 'Failed to link student. Please try again.';
                } else {
                    // Store success message in session
                    $_SESSION['success_message'] = "Student '$student_name' linked successfully!";
                    
                    // Set force refresh flag to ensure we reload from database
                    $_SESSION['force_refresh'] = true;
                    
                    // Clear any existing session data to force a refresh from the database
                    unset($_SESSION['linked_students']);
                    unset($_SESSION['linked_children']);
                    unset($_SESSION['has_linked_children']);
                    
                    // Refresh page to show updated list
                    header('Location: parent-dashboard.php');
                    exit;
                }
            }
        }
    }
}

// Check for success message in session and clear it after use
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    // Clear the message so it doesn't show on refresh
    unset($_SESSION['success_message']);
    // Save the session to ensure the message is cleared immediately
    session_write_close();
    session_start();
}

// Helper function to generate UUID
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - AcademicEase</title>
    
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
                <?php include_once '../assets/img/logo.svg'; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="parent-dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">Sign Out</a>
                </div>
            </div>
        </div>
    </nav>
            
    <!-- Main Content -->
    <div class="container mt-4">
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Debug Information (Only visible to admins) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === 'true'): ?>
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Debug Information</h5>
            </div>
            <div class="card-body">
                <h6>Parent ID: <?php echo htmlspecialchars($parent_id); ?></h6>
                
                <h6 class="mt-3">Session Data:</h6>
                <pre><?php print_r($_SESSION); ?></pre>
                
                <h6 class="mt-3">Linked Students:</h6>
                <pre><?php print_r($linked_students); ?></pre>
                
                <h6 class="mt-3">Test Direct Linking:</h6>
                <form method="post" action="">
                    <input type="hidden" name="action" value="direct_link">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto">
                            <input type="text" name="student_id" class="form-control" placeholder="Student ID">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-warning">Direct Link</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <script>
            // Auto-dismiss success message after 3 seconds
            setTimeout(function() {
                var successAlert = document.getElementById('successAlert');
                if (successAlert) {
                    var bsAlert = new bootstrap.Alert(successAlert);
                    bsAlert.close();
                }
            }, 3000);
        </script>
        <?php  ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Parent Dashboard</h1>
        </div>
        
        <?php if (isset($error) && $error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Children's Accounts Section -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Children's Accounts</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($linked_students)): ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($linked_students as $student): ?>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary"><?php echo htmlspecialchars($student['full_name']); ?></button>
                        <form method="post" action="parent-dashboard.php" class="d-inline" onsubmit="return confirm('Are you sure you want to unlink this student?');">
                            <input type="hidden" name="action" value="unlink_student">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['user_id']); ?>">
                            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x"></i></button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#linkStudentModal"><i class="bi bi-plus"></i> Add</button>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <p class="mb-0">You don't have any student accounts linked to your parent account yet.</p>
                    <p class="mb-0">Please use the button below to link your children's accounts.</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#linkStudentModal"><i class="bi bi-plus"></i> Add Child</button>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Weekly Progress Summary -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="card-title mb-0">Weekly Progress Summary</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($linked_students)): ?>
                <?php foreach ($linked_students as $index => $student): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                        <small class="text-muted"><?php echo isset($student['school_level']) ? htmlspecialchars($student['school_level']) : 'N/A'; ?></small>
                    </div>
                    <div class="card-body">
                        <h6 class="mt-2 mb-3">Subject Performance:</h6>
                        <?php 
                        // Get the progress data for this student
                        $progress = isset($student['progress']) ? $student['progress'] : [];
                        $subject_performance = isset($progress['subject_performance']) ? $progress['subject_performance'] : [
                            'Mathematics' => 0,
                            'Science' => 0,
                            'English' => 0,
                            'Social Studies' => 0
                        ];
                        
                        // Define colors for each subject
                        $subject_colors = [
                            'Mathematics' => 'success',
                            'Science' => 'info',
                            'English' => 'primary',
                            'Social Studies' => 'warning'
                        ];
                        
                        // Display progress bars for each subject
                        foreach ($subject_performance as $subject => $percentage) :
                            // Round the percentage to a whole number
                            $percentage = round($percentage);
                            // Default to 0 if no data, but don't use random values
                            if ($percentage <= 0) $percentage = 0;
                            // Get the appropriate color
                            $color = isset($subject_colors[$subject]) ? $subject_colors[$subject] : 'secondary';
                        ?>
                        <!-- <?php echo $subject; ?> Progress Bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?php echo $subject; ?>:</span>
                                <span><?php echo $percentage; ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%" 
                                     aria-valuenow="<?php echo $percentage; ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Activity Summary -->
                        <div class="mt-4">
                            <h6 class="mb-2">Activity Summary:</h6>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Notes: <?php echo isset($progress['notes_count']) ? $progress['notes_count'] : 0; ?></span>
                                <span>Study Plans: <?php echo isset($progress['study_plans_count']) ? $progress['study_plans_count'] : 0; ?></span>
                                <span>Quizzes: <?php echo isset($progress['quiz_results']) ? count($progress['quiz_results']) : 0; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="alert alert-info">
                    <p class="mb-0">No progress data available. Please link a student account to view progress.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics Row -->
        <div class="row mb-4">
            <!-- Usage Statistics -->
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Usage Statistics</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($linked_students)): ?>
                        <?php foreach ($linked_students as $student): ?>
                        <div class="mb-3">
                            <h6 class="border-bottom pb-2"><?php echo htmlspecialchars($student['full_name']); ?>'s Usage</h6>
                            <ul class="list-group list-group-flush">
                                <?php
                                // Get usage stats for this student
                                $usage_stats = isset($student['progress']['usage_stats']) ? $student['progress']['usage_stats'] : [
                                    'hours_spent' => 0,
                                    'questions_answered' => 0,
                                    'study_sessions' => 0,
                                    'ai_assistant_usage' => 0,
                                    'last_active' => ''
                                ];
                                
                                // Format last active time if available
                                $last_active_display = '';
                                if (!empty($usage_stats['last_active'])) {
                                    try {
                                        $last_active_date = new DateTime($usage_stats['last_active']);
                                        $last_active_display = $last_active_date->format('M j, Y g:i A');
                                    } catch (Exception $e) {
                                        // If date parsing fails, use the raw string
                                        $last_active_display = $usage_stats['last_active'];
                                    }
                                }
                                ?>
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
                                <?php if (!empty($last_active_display)): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <small class="text-muted">Last active: <?php echo $last_active_display; ?></small>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="text-muted">Link student accounts to view usage statistics.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Areas for Improvement -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">Areas for Improvement</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($linked_students)): ?>
                        <?php foreach ($linked_students as $student): ?>
                        <div class="mb-3">
                            <h6 class="border-bottom pb-2"><?php echo htmlspecialchars($student['full_name']); ?>'s Improvement Areas</h6>
                            <ul class="list-group list-group-flush">
                                <?php 
                                // Get improvement areas for this student
                                $improvement_areas = isset($student['progress']['improvement_areas']) ? $student['progress']['improvement_areas'] : [];
                                
                                // If we have specific improvement areas, display them
                                if (!empty($improvement_areas)) {
                                    foreach ($improvement_areas as $area) {
                                        $subject = isset($area['subject']) ? $area['subject'] : 'General';
                                        $recommendation = isset($area['recommendation']) ? $area['recommendation'] : 'Needs more practice';
                                ?>
                                <li class="list-group-item">
                                    <?php 
                                    // Display different icons based on severity
                                    $severity = isset($area['severity']) ? $area['severity'] : 'medium';
                                    $icon_class = 'bi-exclamation-triangle text-warning';
                                    
                                    if ($severity === 'high') {
                                        $icon_class = 'bi-exclamation-circle-fill text-danger';
                                    } else if ($severity === 'low') {
                                        $icon_class = 'bi-info-circle text-info';
                                    }
                                    ?>
                                    <i class="bi <?php echo $icon_class; ?> me-2"></i> <?php echo htmlspecialchars($recommendation); ?>
                                </li>
                                <?php 
                                    }
                                } else {
                                    // Show a message if no improvement areas are found
                                ?>
                                <li class="list-group-item">
                                    <i class="bi bi-check-circle text-success me-2"></i> No specific improvement areas identified yet
                                </li>
                                <li class="list-group-item">
                                    <i class="bi bi-info-circle text-info me-2"></i> More data will be available as your child uses the platform
                                </li>
                                <?php } ?>
                                
                                <!-- Always show the AI assistant recommendation -->
                                <li class="list-group-item">
                                    <i class="bi bi-star-fill text-primary me-2"></i> Encourage using the AI assistant for homework help
                                </li>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <p class="text-muted">Link student accounts to view areas for improvement.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Link Student Modal -->
    <div class="modal fade" id="linkStudentModal" tabindex="-1" aria-labelledby="linkStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkStudentModalLabel">Link Student Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">If your child already has an Academic Ease account, you can link it here.</p>
                    
                    <form id="linkExistingForm" method="post" action="parent-dashboard.php">
                        <input type="hidden" name="action" value="link_existing">
                        
                        <div class="mb-3">
                            <label for="student_email" class="form-label">Student's Email Address</label>
                            <input type="email" class="form-control" id="student_email" name="student_email" required>
                            <div class="form-text">Enter the email address your child used to register their Academic Ease account.</div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Link Account</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- No automatic modal trigger -->
</body>
</html>
