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

// Get dashboard statistics
$error = '';
$fetchRows = static function ($endpoint, $allowMissing = false) {
    $response = supabaseRequest($endpoint, 'GET', null, true);

    if (isset($response['error']) && $response['error'] === true) {
        if ($allowMissing && ($response['status'] ?? 0) === 404) {
            error_log('Admin Dashboard: Optional Supabase resource is unavailable: ' . $endpoint);
            return [];
        }

        throw new RuntimeException('Unable to load dashboard data from Supabase.');
    }

    if (!is_array($response)) {
        throw new RuntimeException('Invalid dashboard data received from Supabase.');
    }

    return array_values(array_filter($response, 'is_array'));
};

try {
    // Get total users count
    $users = $fetchRows("users");
    $total_users = count($users);
    
    // Count users by type
    $students = array_filter($users, function($user) {
        return ($user['user_type'] ?? null) === 'student';
    });
    $total_students = count($students);
    
    $parents = array_filter($users, function($user) {
        return ($user['user_type'] ?? null) === 'parent';
    });
    $total_parents = count($parents);
    
    // Get active subscriptions
    $active_subscriptions = array_filter($users, function($user) {
        return ($user['subscription_status'] ?? null) === 'active';
    });
    $total_active_subscriptions = count($active_subscriptions);
    
    // Get quizzes
    $quizzes = $fetchRows("quizzes");
    $total_quizzes = count($quizzes);
    
    // Get study materials from both tables
    // Older Supabase deployments do not have a dedicated study_materials table.
    // Study materials uploaded through the current schema are also stored in past_questions.
    $study_materials_table = $fetchRows("study_materials", true);
    $study_materials_from_past_questions = $fetchRows("past_questions?exam_type=eq.study_material");
    
    // Combine the counts
    $total_study_materials = count($study_materials_table) + count($study_materials_from_past_questions);
    
    // Combine the materials for use in other parts of the dashboard
    $study_materials = array_merge($study_materials_table, $study_materials_from_past_questions);
    
    // Get recent quiz results
    $quiz_results = $fetchRows("quiz_results?order=completion_date.desc&limit=5");
    
    // Get recent user registrations
    $recent_users = $fetchRows("users?order=created_at.desc&limit=5");
    
} catch (Exception $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    $users = $quizzes = $study_materials_table = $study_materials_from_past_questions = [];
    $quiz_results = $recent_users = [];
    $total_users = $total_students = $total_parents = $total_active_subscriptions = 0;
    $total_quizzes = $total_study_materials = 0;
    $error = 'The dashboard could not load data. Please check the Supabase connection and try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
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
        
        .stat-card {
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
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
            <h4 class="mb-0">AcademicEase</h4>
            <p class="mb-0 text-muted">Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="active">
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
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Admin Dashboard</h2>
                <div class="d-flex align-items-center">
                    <span class="me-3">Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-primary text-white me-3">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0"><?php echo $total_users; ?></h5>
                                <p class="card-text text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 text-end">
                            <a href="users.php" class="text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-success text-white me-3">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0"><?php echo $total_active_subscriptions; ?></h5>
                                <p class="card-text text-muted mb-0">Active Subscriptions</p>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 text-end">
                            <a href="subscriptions.php" class="text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-warning text-white me-3">
                                <i class="bi bi-question-circle"></i>
                            </div>
                            <div>
                                <h5 class="card-title mb-0"><?php echo $total_quizzes; ?></h5>
                                <p class="card-text text-muted mb-0">Total Quizzes</p>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-0 text-end">
                            <a href="quizzes.php" class="text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="card stat-card h-100">
                        <div class="card-body d-flex align-items-center">
                            <div class="stat-icon bg-info text-white me-3">
                                <i class="bi bi-book"></i>
                            </div>
                            <div>
                                <h5 class="card-title"><?php echo $total_study_materials; ?></h5>
                                <p class="card-text text-muted">Study Materials</p>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0 text-end">
                            <a href="study-materials.php" class="text-decoration-none">View Details <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- New Features Section -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">New Admin Features</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex p-3 mb-3">
                                        <i class="bi bi-eye fs-3 text-primary"></i>
                                    </div>
                                    <h5>Student View</h5>
                                    <p class="text-muted">View the platform from a student's perspective and track their activity</p>
                                    <a href="student-view.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-right"></i> Explore Feature
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="rounded-circle bg-success bg-opacity-10 d-inline-flex p-3 mb-3">
                                        <i class="bi bi-cloud-upload fs-3 text-success"></i>
                                    </div>
                                    <h5>Bulk Upload</h5>
                                    <p class="text-muted">Upload multiple formula sheets, past questions, and study materials at once</p>
                                    <a href="bulk-upload.php" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-arrow-right"></i> Explore Feature
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="rounded-circle bg-info bg-opacity-10 d-inline-flex p-3 mb-3">
                                        <i class="bi bi-shield-lock fs-3 text-info"></i>
                                    </div>
                                    <h5>Enhanced Security</h5>
                                    <p class="text-muted">Improved admin authentication with session protection and login security</p>
                                    <a href="settings.php" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-arrow-right"></i> Explore Feature
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">User Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userDistributionChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Content Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="contentDistributionChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Row -->
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent User Registrations</h5>
                            <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recent_users)): ?>
                                            <?php foreach ($recent_users as $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <?php if ($user['user_type'] === 'student'): ?>
                                                            <span class="badge bg-primary">Student</span>
                                                        <?php elseif ($user['user_type'] === 'parent'): ?>
                                                            <span class="badge bg-success">Parent</span>
                                                        <?php elseif ($user['user_type'] === 'admin'): ?>
                                                            <span class="badge bg-danger">Admin</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Unknown</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-3">No recent registrations</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-3">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Quiz Results</h5>
                            <a href="quiz-results.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Student</th>
                                            <th>Quiz</th>
                                            <th>Score</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($quiz_results)): ?>
                                            <?php foreach ($quiz_results as $result): ?>
                                                <?php
                                                // Get student name
                                                $student_name = 'Unknown';
                                                foreach ($users as $user) {
                                                    if ($user['user_id'] === $result['user_id']) {
                                                        $student_name = $user['full_name'];
                                                        break;
                                                    }
                                                }
                                                
                                                // Get quiz title
                                                $quiz_title = 'Unknown';
                                                foreach ($quizzes as $quiz) {
                                                    if ($quiz['quiz_id'] === $result['quiz_id']) {
                                                        $quiz_title = $quiz['title'];
                                                        break;
                                                    }
                                                }
                                                
                                                // Calculate percentage
                                                $percentage = ($result['max_score'] > 0) ? round(($result['score'] / $result['max_score']) * 100) : 0;
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student_name); ?></td>
                                                    <td><?php echo htmlspecialchars($quiz_title); ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                                <div class="progress-bar bg-<?php echo ($percentage >= 70) ? 'success' : (($percentage >= 50) ? 'warning' : 'danger'); ?>" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                            </div>
                                                            <span><?php echo $percentage; ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($result['completion_date'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-3">No recent quiz results</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Chart Initialization -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Content type selection handling
            $('#content_type').on('change', function() {
                const contentType = $(this).val();
                
                // Hide all content sections
                $('#fileUploadSection, #textContentSection, #linkContentSection').addClass('d-none');
                
                // Show the selected content section
                if (contentType === 'file') {
                    $('#fileUploadSection').removeClass('d-none');
                } else if (contentType === 'text') {
                    $('#textContentSection').removeClass('d-none');
                } else if (contentType === 'link') {
                    $('#linkContentSection').removeClass('d-none');
                }
            });
            
            // User Distribution Chart
            const userDistributionCtx = document.getElementById('userDistributionChart').getContext('2d');
            const userDistributionChart = new Chart(userDistributionCtx, {
                type: 'pie',
                data: {
                    labels: ['Students', 'Parents', 'Admins'],
                    datasets: [{
                        data: [<?php echo $total_students; ?>, <?php echo $total_parents; ?>, <?php echo $total_users - $total_students - $total_parents; ?>],
                        backgroundColor: ['#0d6efd', '#198754', '#dc3545'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            // Content Distribution Chart
            const contentDistributionCtx = document.getElementById('contentDistributionChart').getContext('2d');
            const contentDistributionChart = new Chart(contentDistributionCtx, {
                type: 'bar',
                data: {
                    labels: ['Quizzes', 'Study Materials', 'Past Questions', 'Formula Sheets'],
                    datasets: [{
                        label: 'Content Count',
                        data: [
                            <?php echo $total_quizzes; ?>, 
                            <?php echo $total_study_materials; ?>, 
                            <?php echo isset($total_past_questions) ? $total_past_questions : 0; ?>, 
                            <?php echo isset($total_formula_sheets) ? $total_formula_sheets : 0; ?>
                        ],
                        backgroundColor: ['#ffc107', '#17a2b8', '#6f42c1', '#fd7e14'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        });
    </script>
    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMaterialModalLabel">Add New Study Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="study-materials.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_material">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <?php foreach ($levels as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category">
                            <div class="form-text">E.g., Algebra, Grammar, Biology, etc.</div>
                        </div>
                        <div class="mb-3">
                            <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="content_type" name="content_type" required>
                                <option value="">Select Content Type</option>
                                <option value="file">File Upload</option>
                                <option value="text">Text Content</option>
                                <option value="link">External Link</option>
                            </select>
                        </div>
                        
                        <!-- File Upload (shown when content_type is 'file') -->
                        <div id="fileUploadSection" class="mb-3 d-none">
                            <label for="material_file" class="form-label">Upload File</label>
                            <input type="file" class="form-control" id="material_file" name="material_file">
                            <div class="form-text">Supported formats: PDF, DOCX, PPTX, etc. (Max size: 10MB)</div>
                        </div>
                        
                        <!-- Text Content (shown when content_type is 'text') -->
                        <div id="textContentSection" class="mb-3 d-none">
                            <label for="content_text" class="form-label">Text Content</label>
                            <textarea class="form-control" id="content_text" name="content_text" rows="6"></textarea>
                        </div>
                        
                        <!-- External Link (shown when content_type is 'link') -->
                        <div id="linkContentSection" class="mb-3 d-none">
                            <label for="content_link" class="form-label">External Link</label>
                            <input type="url" class="form-control" id="content_link" name="content_link" placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
