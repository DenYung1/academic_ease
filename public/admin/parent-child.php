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
$parents = [];
$students = [];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Link student to parent
    if (isset($_POST['action']) && $_POST['action'] === 'link') {
        $parent_id = $_POST['parent_id'];
        $student_id = $_POST['student_id'];
        
        if (empty($parent_id) || empty($student_id)) {
            $error_message = 'Please select both a parent and a student.';
        } else {
            // Link the student to the parent
            $result = link_child_to_parent($parent_id, $student_id);
            
            if ($result) {
                $success_message = 'Student successfully linked to parent.';
            } else {
                $error_message = 'Failed to link student to parent. Please try again.';
            }
        }
    }
    
    // Unlink student from parent
    if (isset($_POST['action']) && $_POST['action'] === 'unlink') {
        $parent_id = $_POST['parent_id'];
        $student_id = $_POST['student_id'];
        
        if (empty($parent_id) || empty($student_id)) {
            $error_message = 'Invalid request. Missing parent or student ID.';
        } else {
            // Unlink the student from the parent
            $result = unlink_child_from_parent($parent_id, $student_id);
            
            if ($result) {
                $success_message = 'Student successfully unlinked from parent.';
            } else {
                $error_message = 'Failed to unlink student from parent. Please try again.';
            }
        }
    }
}

// Get all parents
try {
    $parents = supabaseRequest("users?user_type=eq.parent&order=full_name.asc", 'GET', null, true);
    
    // Enhance parent data with linked children
    foreach ($parents as &$parent) {
        $linked_children = get_linked_children($parent['user_id']);
        $parent['linked_children'] = $linked_children;
    }
    unset($parent); // Break the reference with the last element
} catch (Exception $e) {
    error_log('Admin Parent-Child Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading parents.';
}

// Get all students
try {
    $students = supabaseRequest("users?user_type=eq.student&order=full_name.asc", 'GET', null, true);
    
    // Enhance student data with linked parent
    foreach ($students as &$student) {
        $linked_parent = get_student_parent($student['user_id']);
        $student['linked_parent'] = $linked_parent;
    }
    unset($student); // Break the reference with the last element
} catch (Exception $e) {
    error_log('Admin Parent-Child Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading students.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent-Child Management - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
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
        
        .relationship-card {
            transition: all 0.3s;
        }
        
        .relationship-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
            <a href="parent-child.php" class="active">
                <i class="bi bi-people-fill me-2"></i> Parent-Child
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Parent-Child Relationship Management</h2>
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
            
            <!-- Link Parent and Student -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Link Parent and Student</h5>
                </div>
                <div class="card-body">
                    <form action="parent-child.php" method="post">
                        <input type="hidden" name="action" value="link">
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label for="parent_id" class="form-label">Select Parent</label>
                                <select class="form-select" id="parent_id" name="parent_id" required>
                                    <option value="">Choose a parent...</option>
                                    <?php foreach ($parents as $parent): ?>
                                        <option value="<?php echo $parent['user_id']; ?>">
                                            <?php echo htmlspecialchars($parent['full_name'] . ' (' . $parent['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Choose a student...</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['user_id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-link me-1"></i> Link
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Existing Relationships -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Existing Parent-Child Relationships</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($parents) || !array_filter($parents, function($parent) { return !empty($parent['linked_children']); })): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            No parent-child relationships found.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($parents as $parent): ?>
                                <?php if (!empty($parent['linked_children'])): ?>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card relationship-card h-100">
                                            <div class="card-header bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0">
                                                        <i class="bi bi-person-fill text-success me-2"></i>
                                                        <?php echo htmlspecialchars($parent['full_name']); ?>
                                                    </h5>
                                                    <span class="badge bg-success"><?php echo count($parent['linked_children']); ?> Children</span>
                                                </div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($parent['email']); ?></div>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="border-bottom pb-2 mb-3">Linked Children</h6>
                                                <ul class="list-group list-group-flush">
                                                    <?php foreach ($parent['linked_children'] as $child): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <i class="bi bi-person text-primary me-2"></i>
                                                                <?php echo htmlspecialchars($child['full_name']); ?>
                                                                <div class="text-muted small"><?php echo htmlspecialchars($child['email']); ?></div>
                                                            </div>
                                                            <form action="parent-child.php" method="post" onsubmit="return confirm('Are you sure you want to unlink this student from the parent?');">
                                                                <input type="hidden" name="action" value="unlink">
                                                                <input type="hidden" name="parent_id" value="<?php echo $parent['user_id']; ?>">
                                                                <input type="hidden" name="student_id" value="<?php echo $child['user_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="bi bi-unlink"></i> Unlink
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <div class="card-footer bg-white">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">Subscription: <?php echo ucfirst($parent['subscription_plan'] ?? 'None'); ?></small>
                                                    <a href="student-progress.php?student_id=<?php echo $parent['linked_children'][0]['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-graph-up me-1"></i> View Progress
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Unlinked Students -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Unlinked Students</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $unlinked_students = array_filter($students, function($student) {
                        return empty($student['linked_parent']);
                    });
                    ?>
                    
                    <?php if (empty($unlinked_students)): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            All students are linked to parents.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table id="unlinkedStudentsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Subscription</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unlinked_students as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td>
                                                <?php if ($student['subscription_status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($student['subscription_status'] === 'trial'): ?>
                                                    <span class="badge bg-info">Trial</span>
                                                <?php elseif ($student['subscription_status'] === 'expired'): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($student['created_at'] ?? 'now')); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary link-student-btn" 
                                                        data-bs-toggle="modal" data-bs-target="#linkStudentModal"
                                                        data-student-id="<?php echo $student['user_id']; ?>"
                                                        data-student-name="<?php echo htmlspecialchars($student['full_name']); ?>">
                                                    <i class="bi bi-link me-1"></i> Link to Parent
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Link Student Modal -->
    <div class="modal fade" id="linkStudentModal" tabindex="-1" aria-labelledby="linkStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkStudentModalLabel">Link Student to Parent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="parent-child.php" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="link">
                        <input type="hidden" name="student_id" id="modal_student_id">
                        
                        <p>Link <strong id="modal_student_name"></strong> to a parent:</p>
                        
                        <div class="mb-3">
                            <label for="modal_parent_id" class="form-label">Select Parent</label>
                            <select class="form-select" id="modal_parent_id" name="parent_id" required>
                                <option value="">Choose a parent...</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['user_id']; ?>">
                                        <?php echo htmlspecialchars($parent['full_name'] . ' (' . $parent['email'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Link Student</button>
                    </div>
                </form>
            </div>
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
            $('#unlinkedStudentsTable').DataTable({
                pageLength: 10,
                language: {
                    search: "Search students:",
                    lengthMenu: "Show _MENU_ students per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ students"
                }
            });
            
            // Handle link student button click
            $('.link-student-btn').click(function() {
                const studentId = $(this).data('student-id');
                const studentName = $(this).data('student-name');
                
                $('#modal_student_id').val(studentId);
                $('#modal_student_name').text(studentName);
            });
        });
    </script>
</body>
</html>
