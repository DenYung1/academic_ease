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
$quiz = [];
$subjects = [
    'mathematics' => 'Mathematics',
    'science' => 'Science',
    'english' => 'English',
    'social_studies' => 'Social Studies',
    'ict' => 'ICT',
    'chemistry' => 'Chemistry',
    'physics' => 'Physics',
    'biology' => 'Biology'
];
$levels = [
    'jhs' => 'Junior High School',
    'shs' => 'Senior High School'
];
$success_message = '';
$error_message = '';

// Check if quiz ID is provided
if (!isset($_GET['quiz_id']) || empty($_GET['quiz_id'])) {
    // Redirect to quizzes page if no quiz ID is provided
    header('Location: quizzes.php');
    exit;
}

$quiz_id = $_GET['quiz_id'];

// Get quiz data
try {
    $filter = "quiz_id=eq.$quiz_id";
    $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
    
    if (empty($quizzes)) {
        // Quiz not found, redirect to quizzes page
        header('Location: quizzes.php?error=quiz_not_found');
        exit;
    }
    
    $quiz = $quizzes[0];
} catch (Exception $e) {
    error_log('Admin Edit Quiz Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading the quiz.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update quiz
    if (isset($_POST['action']) && $_POST['action'] === 'update_quiz') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $category = trim($_POST['category']);
        $time_limit = (int)$_POST['time_limit'];
        
        if (empty($title) || empty($subject) || empty($level)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Update quiz in database
            $quizData = [
                'title' => $title,
                'description' => $description,
                'subject' => $subject,
                'level' => $level,
                'category' => $category,
                'time_limit' => $time_limit
            ];
            
            $result = supabaseRequest("quizzes?quiz_id=eq.$quiz_id", 'PATCH', $quizData, true);
            
            if (!empty($result)) {
                $success_message = 'Quiz updated successfully!';
                
                // Update local quiz data
                $quiz = array_merge($quiz, $quizData);
            } else {
                $error_message = 'Failed to update quiz. Please try again.';
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
    <title>Edit Quiz - Academic Ease Admin</title>
    
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
        
        .sidebar-menu i {
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
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
            <h3>Academic Ease</h3>
            <p class="text-muted mb-0">Admin Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="users.php"><i class="bi bi-people"></i> Users</a>
            <a href="formula-sheets.php"><i class="bi bi-file-earmark-text"></i> Formula Sheets</a>
            <a href="past-questions.php"><i class="bi bi-question-circle"></i> Past Questions</a>
            <a href="quizzes.php" class="active"><i class="bi bi-check2-square"></i> Quizzes</a>
            <a href="generate-quizzes.php">
                <i class="bi bi-lightning me-2"></i> Generate Quizzes
            </a>
            <a href="study-materials.php"><i class="bi bi-book"></i> Study Materials</a>
            <a href="student-progress.php"><i class="bi bi-graph-up"></i> Student Progress</a>
            <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
            <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Quiz</h2>
                <a href="quizzes.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Quizzes
                </a>
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
            
            <!-- Edit Quiz Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form action="edit-quiz.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                        <input type="hidden" name="action" value="update_quiz">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($quiz['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($quiz['subject'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <?php foreach ($levels as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($quiz['level'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category" value="<?php echo htmlspecialchars($quiz['category'] ?? ''); ?>">
                                <div class="form-text">E.g., Algebra, Grammar, Biology, etc.</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                <input type="number" class="form-control" id="time_limit" name="time_limit" min="1" value="<?php echo (int)($quiz['time_limit'] ?? 30); ?>">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="quizzes.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quiz Questions -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Quiz Questions</h5>
                    <a href="quiz-questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil-square me-1"></i> Manage Questions
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        This quiz has <?php echo (int)($quiz['question_count'] ?? 0); ?> questions.
                        Click the "Manage Questions" button to add, edit, or remove questions.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
