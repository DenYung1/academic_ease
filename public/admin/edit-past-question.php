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
$years = range(date('Y'), 2010);
$success_message = '';
$error_message = '';
$question = null;

// Check if question ID is provided
if (!isset($_GET['question_id']) || empty($_GET['question_id'])) {
    header('Location: past-questions.php');
    exit;
}

$question_id = $_GET['question_id'];

// Get question data
try {
    $question_data = supabaseRequest("past_questions?question_id=eq.$question_id", 'GET', null, true);
    
    if (empty($question_data)) {
        header('Location: past-questions.php');
        exit;
    }
    
    $question = $question_data[0];
} catch (Exception $e) {
    error_log('Admin Edit Past Question Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading the past question.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_past_question') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $year = trim($_POST['year']);
        $exam_type = trim($_POST['exam_type']);
        
        // Initialize update data
        $updateData = [
            'title' => $title,
            'description' => $description,
            'subject' => $subject,
            'level' => $level,
            'year' => $year,
            'exam_type' => $exam_type,
            'updated_at' => date('c')
        ];
        
        // Handle file upload if a new file is provided
        if (isset($_FILES['question_file']) && $_FILES['question_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/past_questions/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = basename($_FILES['question_file']['name']);
            $unique_file_name = uniqid() . '_' . $file_name;
            $file_path = $upload_dir . $unique_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['question_file']['tmp_name'], $file_path)) {
                // Delete old file if exists
                if (!empty($question['file_path'])) {
                    $old_file_path = '../../' . $question['file_path'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                
                $updateData['file_path'] = 'uploads/past_questions/' . $unique_file_name;
                $updateData['file_name'] = $file_name;
            } else {
                $error_message = 'Failed to upload file. Please try again.';
            }
        }
        
        if (empty($title) || empty($subject) || empty($level) || empty($year)) {
            $error_message = 'Please fill in all required fields.';
        } else if (empty($error_message)) {
            // Update past question in database
            $result = supabaseRequest("past_questions?question_id=eq.$question_id", 'PATCH', $updateData, true);
            
            if (!empty($result)) {
                $success_message = 'Past question updated successfully!';
                
                // Refresh question data
                $question_data = supabaseRequest("past_questions?question_id=eq.$question_id", 'GET', null, true);
                $question = $question_data[0];
            } else {
                $error_message = 'Failed to update past question. Please try again.';
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
    <title>Edit Past Question - Academic Ease Admin</title>
    
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
            <h4 class="mb-0">Academic Ease</h4>
            <p class="mb-0 text-muted">Admin Panel</p>
        </div>
        <div class="sidebar-menu">\n            <a href="index.php">\n                <i class="bi bi-speedometer2 me-2"></i> Dashboard\n            </a>\n            <a href="users.php">\n                <i class="bi bi-people me-2"></i> Users\n            </a>\n            <a href="formula-sheets.php">\n                <i class="bi bi-journal-text me-2"></i> Formula Sheets\n            </a>\n            <a href="past-questions.php" class="active">\n                <i class="bi bi-file-earmark-text me-2"></i> Past Questions\n            </a>\n            <a href="quizzes.php">\n                <i class="bi bi-question-circle me-2"></i> Quizzes\n            </a>
            <a href="generate-quizzes.php">
                <i class="bi bi-lightning me-2"></i> Generate Quizzes
            </a>\n            <a href="study-materials.php">\n                <i class="bi bi-book me-2"></i> Study Materials\n            </a>\n            <a href="student-progress.php">\n                <i class="bi bi-graph-up me-2"></i> Student Progress\n            </a>\n            <a href="student-view.php">\n                <i class="bi bi-eye me-2"></i> Student View\n            </a>\n            <a href="bulk-upload.php">\n                <i class="bi bi-cloud-upload me-2"></i> Bulk Upload\n            </a>\n            <a href="settings.php">\n                <i class="bi bi-gear me-2"></i> Settings\n            </a>\n            <a href="logout.php" class="mt-4 text-danger">\n                <i class="bi bi-box-arrow-right"></i> Logout\n            </a></div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Past Question</h2>
                <a href="past-questions.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Past Questions
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
            
            <!-- Edit Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form action="edit-past-question.php?question_id=<?php echo $question_id; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_past_question">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($question['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($question['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($question['subject'] ?? '') === $key ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $key; ?>" <?php echo ($question['level'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                <select class="form-select" id="year" name="year" required>
                                    <option value="">Select Year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" <?php echo ($question['year'] ?? '') == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="exam_type" class="form-label">Exam Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="exam_type" name="exam_type" value="<?php echo htmlspecialchars($question['exam_type'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="question_file" class="form-label">Upload New File</label>
                            <input type="file" class="form-control" id="question_file" name="question_file">
                            <div class="form-text">Leave empty to keep the current file. Upload a new file to replace the existing one.</div>
                        </div>
                        
                        <?php if (!empty($question['file_path'])): ?>
                            <div class="mb-4">
                                <label class="form-label">Current File</label>
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-earmark-pdf fs-4 me-2"></i>
                                    <div>
                                        <div><?php echo htmlspecialchars($question['file_name'] ?? basename($question['file_path'])); ?></div>
                                        <a href="../<?php echo htmlspecialchars($question['file_path']); ?>" class="btn btn-sm btn-outline-primary mt-1" target="_blank">
                                            <i class="bi bi-eye me-1"></i> View File
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="past-questions.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Past Question</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
