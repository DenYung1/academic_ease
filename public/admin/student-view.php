<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_session.php';
require_once '../../includes/parent_child_helper.php';

// Verify admin is logged in with secure session
check_admin_login();

// Get admin data
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Initialize variables
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : null;
$student = null;
$error_message = '';
$success_message = '';
$recent_activity = [];

// Get student data if ID is provided
if ($student_id) {
    try {
        $student = supabaseRequest("users?user_id=eq.$student_id", 'GET', null, true);
        if (!empty($student)) {
            $student = $student[0];
            
            // Get parent information for this student
            $parent = get_student_parent($student_id);
            $student['linked_parent'] = $parent;
            
            // Get recent activity for this student
            // 1. Recent logins
            if (!empty($student['last_login'])) {
                $recent_activity[] = [
                    'type' => 'login',
                    'date' => $student['last_login'],
                    'details' => 'User logged in'
                ];
            }
            
            // 2. Recent quiz attempts
            $quiz_results = supabaseRequest("quiz_results?user_id=eq.$student_id&order=completion_date.desc&limit=5", 'GET', null, true);
            if (!empty($quiz_results)) {
                foreach ($quiz_results as $result) {
                    $recent_activity[] = [
                        'type' => 'quiz',
                        'date' => $result['completion_date'],
                        'details' => "Completed quiz: {$result['quiz_title']} with score {$result['score']}%"
                    ];
                }
            }
            
            // 3. Recent study materials accessed
            $study_logs = supabaseRequest("study_logs?user_id=eq.$student_id&order=access_time.desc&limit=5", 'GET', null, true);
            if (!empty($study_logs)) {
                foreach ($study_logs as $log) {
                    // Get material title safely
                    $material_title = '';
                    if (isset($log['material_title'])) {
                        $material_title = $log['material_title'];
                    } elseif (isset($log['material_id'])) {
                        // Try to fetch the title from the study_materials table
                        try {
                            $material = supabaseRequest("study_materials?id=eq.{$log['material_id']}", 'GET', null, true);
                            if (!empty($material) && isset($material[0]['title'])) {
                                $material_title = $material[0]['title'];
                            } else {
                                $material_title = "Material ID: {$log['material_id']}";
                            }
                        } catch (Exception $e) {
                            $material_title = "Unknown Material";
                            error_log('Error fetching material title: ' . $e->getMessage());
                        }
                    } else {
                        $material_title = "Unknown Material";
                    }
                    
                    // Get access time safely
                    $access_time = '';
                    if (isset($log['access_time'])) {
                        $access_time = $log['access_time'];
                    } elseif (isset($log['timestamp'])) {
                        $access_time = $log['timestamp'];
                    } elseif (isset($log['created_at'])) {
                        $access_time = $log['created_at'];
                    } else {
                        $access_time = date('c'); // Current time as fallback
                    }
                    
                    $recent_activity[] = [
                        'type' => 'study',
                        'date' => $access_time,
                        'details' => "Accessed study material: {$material_title}"
                    ];
                }
            }
            
            // 4. Recent formula sheet views
            $formula_logs = supabaseRequest("formula_access_logs?user_id=eq.$student_id&order=access_time.desc&limit=5", 'GET', null, true);
            if (!empty($formula_logs)) {
                foreach ($formula_logs as $log) {
                    // Get formula title safely
                    $formula_title = '';
                    if (isset($log['formula_title'])) {
                        $formula_title = $log['formula_title'];
                    } elseif (isset($log['formula_id'])) {
                        // Try to fetch the title from the formula_sheets table
                        try {
                            $formula = supabaseRequest("formula_sheets?id=eq.{$log['formula_id']}", 'GET', null, true);
                            if (!empty($formula) && isset($formula[0]['title'])) {
                                $formula_title = $formula[0]['title'];
                            } else {
                                $formula_title = "Formula ID: {$log['formula_id']}";
                            }
                        } catch (Exception $e) {
                            $formula_title = "Unknown Formula Sheet";
                            error_log('Error fetching formula title: ' . $e->getMessage());
                        }
                    } else {
                        $formula_title = "Unknown Formula Sheet";
                    }
                    
                    // Get access time safely
                    $access_time = '';
                    if (isset($log['access_time'])) {
                        $access_time = $log['access_time'];
                    } elseif (isset($log['timestamp'])) {
                        $access_time = $log['timestamp'];
                    } elseif (isset($log['created_at'])) {
                        $access_time = $log['created_at'];
                    } else {
                        $access_time = date('c'); // Current time as fallback
                    }
                    
                    $recent_activity[] = [
                        'type' => 'formula',
                        'date' => $access_time,
                        'details' => "Viewed formula sheet: {$formula_title}"
                    ];
                }
            }
            
            // Sort all activities by date (newest first)
            usort($recent_activity, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            
            // Limit to most recent 10 activities
            $recent_activity = array_slice($recent_activity, 0, 10);
        } else {
            $error_message = 'Student not found.';
        }
    } catch (Exception $e) {
        error_log('Admin Student View Error: ' . $e->getMessage());
        $error_message = 'An error occurred while loading student data.';
    }
}

// Get all students for dropdown
try {
    $students = supabaseRequest("users?user_type=eq.student&order=full_name.asc", 'GET', null, true);
} catch (Exception $e) {
    error_log('Admin Student View Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading students list.';
    $students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student View - Academic Ease Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-size: 1.2rem;
        }
        
        .login-icon { background-color: #cfe2ff; color: #0d6efd; }
        .quiz-icon { background-color: #d1e7dd; color: #198754; }
        .study-icon { background-color: #fff3cd; color: #ffc107; }
        .formula-icon { background-color: #f8d7da; color: #dc3545; }
        
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
            <a href="student-progress.php">
                <i class="bi bi-graph-up me-2"></i> Student Progress
            </a>
            <a href="student-view.php" class="active">
                <i class="bi bi-eye me-2"></i> Student View
            </a>
            <a href="settings.php">
                <i class="bi bi-gear me-2"></i> Settings
            </a>
            <a href="logout.php" class="mt-4 text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Student View</h2>
                <div class="d-flex align-items-center">
                    <span class="me-3"><?php echo htmlspecialchars($admin_name); ?></span>
                    <a href="logout.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Student Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Select Student</h5>
                    <form action="student-view.php" method="get" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student</label>
                            <select class="form-select" id="student_id" name="student_id" required>
                                <option value="">Select a student</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['user_id']; ?>" <?php echo ($student_id == $s['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['full_name']); ?> (<?php echo htmlspecialchars($s['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-2"></i>View Student
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($student): ?>
            <!-- Student Information -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">Student Profile</h5>
                            <div class="text-center mb-3">
                                <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                                    <i class="bi bi-person-fill" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="fw-bold">Name:</span>
                                    <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="fw-bold">Email:</span>
                                    <span><?php echo htmlspecialchars($student['email']); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="fw-bold">Subscription:</span>
                                    <span class="badge <?php echo $student['subscription_status'] === 'active' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                        <?php echo ucfirst(htmlspecialchars($student['subscription_status'] ?? 'none')); ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="fw-bold">Plan:</span>
                                    <span><?php echo ucfirst(htmlspecialchars($student['subscription_plan'] ?? 'none')); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="fw-bold">Expires:</span>
                                    <span>
                                        <?php 
                                        if (!empty($student['subscription_end_date'])) {
                                            echo date('M j, Y', strtotime($student['subscription_end_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span class="fw-bold">Registered:</span>
                                    <span>
                                        <?php 
                                        if (!empty($student['created_at'])) {
                                            echo date('M j, Y', strtotime($student['created_at']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </span>
                                </li>
                                
                                <!-- Parent Information Section -->
                                <?php if (!empty($student['linked_parent'])): ?>
                                <li class="list-group-item border-top mt-2">
                                    <div class="fw-bold text-success mb-2">
                                        <i class="bi bi-person-fill me-1"></i> Linked Parent
                                    </div>
                                    <div class="d-flex flex-column">
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Name:</span>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($student['linked_parent']['full_name']); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Email:</span>
                                            <span><?php echo htmlspecialchars($student['linked_parent']['email']); ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span class="text-muted">Subscription:</span>
                                            <span class="badge <?php echo $student['linked_parent']['subscription_status'] === 'active' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($student['linked_parent']['subscription_status'] ?? 'none')); ?>
                                            </span>
                                        </div>
                                        <div class="mt-2 text-center">
                                            <a href="parent-child.php" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-people-fill me-1"></i> Manage Relationship
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <?php else: ?>
                                <li class="list-group-item border-top mt-2">
                                    <div class="fw-bold text-muted mb-2">
                                        <i class="bi bi-person me-1"></i> No Parent Linked
                                    </div>
                                    <div class="text-center">
                                        <a href="parent-child.php" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-link me-1"></i> Link to Parent
                                        </a>
                                    </div>
                                </li>
                                <?php endif; ?>
                            </ul>
                            <div class="mt-3">
                                <a href="users.php?edit=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil me-1"></i> Edit Profile
                                </a>
                                <a href="#" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#impersonateModal">
                                    <i class="bi bi-person-badge me-1"></i> Impersonate
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="card-title">Recent Activity</h5>
                            <?php if (empty($recent_activity)): ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> No recent activity found for this student.
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="list-group-item list-group-item-action d-flex align-items-center">
                                            <?php 
                                            $icon_class = '';
                                            $icon = '';
                                            
                                            switch ($activity['type']) {
                                                case 'login':
                                                    $icon_class = 'login-icon';
                                                    $icon = 'bi-box-arrow-in-right';
                                                    break;
                                                case 'quiz':
                                                    $icon_class = 'quiz-icon';
                                                    $icon = 'bi-check2-square';
                                                    break;
                                                case 'study':
                                                    $icon_class = 'study-icon';
                                                    $icon = 'bi-book';
                                                    break;
                                                case 'formula':
                                                    $icon_class = 'formula-icon';
                                                    $icon = 'bi-calculator';
                                                    break;
                                                default:
                                                    $icon_class = 'login-icon';
                                                    $icon = 'bi-activity';
                                            }
                                            ?>
                                            <div class="activity-icon <?php echo $icon_class; ?> me-3">
                                                <i class="bi <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['details']); ?></h6>
                                                    <small>
                                                        <?php echo date('M j, g:i a', strtotime($activity['date'])); ?>
                                                    </small>
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
            
            <!-- Student Dashboard Preview -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Student Dashboard Preview</h5>
                    <p class="text-muted">This shows what the student sees when they log in to their dashboard.</p>
                    
                    <ul class="nav nav-tabs mb-3" id="studentViewTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">
                                Dashboard
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="formulas-tab" data-bs-toggle="tab" data-bs-target="#formulas" type="button" role="tab" aria-controls="formulas" aria-selected="false">
                                Formula Sheets
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="exams-tab" data-bs-toggle="tab" data-bs-target="#exams" type="button" role="tab" aria-controls="exams" aria-selected="false">
                                Exam Prep
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="studentViewTabsContent">
                        <div class="tab-pane fade show active" id="dashboard" role="tabpanel" aria-labelledby="dashboard-tab">
                            <div class="border p-3 rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>Student Dashboard</h4>
                                    <a href="../dashboard.php?preview=<?php echo $student['user_id']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open in New Tab
                                    </a>
                                </div>
                                <iframe src="../dashboard.php?preview=<?php echo $student['user_id']; ?>" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="formulas" role="tabpanel" aria-labelledby="formulas-tab">
                            <div class="border p-3 rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>Formula Sheets</h4>
                                    <a href="../formula-sheets.php?preview=<?php echo $student['user_id']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open in New Tab
                                    </a>
                                </div>
                                <iframe src="../formula-sheets.php?preview=<?php echo $student['user_id']; ?>" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
                            </div>
                        </div>
                        
                        <div class="tab-pane fade" id="exams" role="tabpanel" aria-labelledby="exams-tab">
                            <div class="border p-3 rounded bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>Exam Preparation</h4>
                                    <a href="../exam-prep.php?preview=<?php echo $student['user_id']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i> Open in New Tab
                                    </a>
                                </div>
                                <iframe src="../exam-prep.php?preview=<?php echo $student['user_id']; ?>" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Impersonate Modal -->
    <div class="modal fade" id="impersonateModal" tabindex="-1" aria-labelledby="impersonateModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="impersonateModalLabel">Impersonate Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to impersonate <strong><?php echo $student ? htmlspecialchars($student['full_name']) : ''; ?></strong>. This will log you in as this student, allowing you to see exactly what they see.</p>
                    <p class="text-warning"><i class="bi bi-exclamation-triangle me-2"></i> This action is logged for security purposes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../dashboard.php?impersonate=<?php echo $student ? $student['user_id'] : ''; ?>&admin_id=<?php echo $admin_id; ?>" class="btn btn-primary">
                        <i class="bi bi-person-badge me-1"></i> Impersonate Student
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
