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
    'physics' => 'Physics',
    'chemistry' => 'Chemistry',
    'biology' => 'Biology'
];
$levels = [
    'jhs' => 'Junior High School',
    'shs' => 'Senior High School'
];
$success_message = '';
$error_message = '';
$formula = null;

// Check if formula ID is provided
if (!isset($_GET['formula_id']) || empty($_GET['formula_id'])) {
    header('Location: formula-sheets.php');
    exit;
}

$formula_id = $_GET['formula_id'];

// Get formula data
try {
    $formula_data = supabaseRequest("formula_sheets?formula_id=eq.$formula_id", 'GET', null, true);
    
    if (empty($formula_data)) {
        header('Location: formula-sheets.php');
        exit;
    }
    
    $formula = $formula_data[0];
} catch (Exception $e) {
    error_log('Admin Edit Formula Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading the formula sheet.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_formula') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $topic = trim($_POST['topic']);
        $content_type = trim($_POST['content_type']);
        
        // Initialize update data
        $updateData = [
            'title' => $title,
            'description' => $description,
            'subject' => $subject,
            'level' => $level,
            'topic' => $topic,
            'content_type' => $content_type,
            'updated_at' => date('c')
        ];
        
        // Handle content based on type
        if ($content_type === 'file') {
            // Handle file upload if a new file is provided
            if (isset($_FILES['formula_file']) && $_FILES['formula_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/formula_sheets/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = basename($_FILES['formula_file']['name']);
                $unique_file_name = uniqid() . '_' . $file_name;
                $file_path = $upload_dir . $unique_file_name;
                
                // Move uploaded file
                if (move_uploaded_file($_FILES['formula_file']['tmp_name'], $file_path)) {
                    // Delete old file if exists and different content type
                    if (!empty($formula['file_path']) && $formula['content_type'] === 'file') {
                        $old_file_path = '../../' . $formula['file_path'];
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                        }
                    }
                    
                    $updateData['file_path'] = 'uploads/formula_sheets/' . $unique_file_name;
                    $updateData['file_name'] = $file_name;
                    $updateData['content_text'] = null; // Clear text content if switching to file
                } else {
                    $error_message = 'Failed to upload file. Please try again.';
                }
            } else if ($_FILES['formula_file']['error'] !== UPLOAD_ERR_NO_FILE && $formula['content_type'] !== 'file') {
                $error_message = 'Please upload a file for file content type.';
            }
        } else if ($content_type === 'text') {
            // Handle text content
            if (isset($_POST['formula_text'])) {
                $updateData['content_text'] = trim($_POST['formula_text']);
                
                // If switching from file to text, delete the old file
                if ($formula['content_type'] === 'file' && !empty($formula['file_path'])) {
                    $old_file_path = '../../' . $formula['file_path'];
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                    
                    $updateData['file_path'] = null;
                    $updateData['file_name'] = null;
                }
            } else if (empty($_POST['formula_text']) && $formula['content_type'] !== 'text') {
                $error_message = 'Please enter formula text for text content type.';
            }
        }
        
        if (empty($title) || empty($subject) || empty($level)) {
            $error_message = 'Please fill in all required fields.';
        } else if (empty($error_message)) {
            // Update formula sheet in database
            $result = supabaseRequest("formula_sheets?formula_id=eq.$formula_id", 'PATCH', $updateData, true);
            
            if (!empty($result)) {
                $success_message = 'Formula sheet updated successfully!';
                
                // Refresh formula data
                $formula_data = supabaseRequest("formula_sheets?formula_id=eq.$formula_id", 'GET', null, true);
                $formula = $formula_data[0];
            } else {
                $error_message = 'Failed to update formula sheet. Please try again.';
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
    <title>Edit Formula Sheet - Academic Ease Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- MathJax for rendering math equations -->
    <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    
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
        
        .preview-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-top: 1rem;
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
        <div class="sidebar-menu">\n            <a href="index.php">\n                <i class="bi bi-speedometer2 me-2"></i> Dashboard\n            </a>\n            <a href="users.php">\n                <i class="bi bi-people me-2"></i> Users\n            </a>\n            <a href="formula-sheets.php" class="active">\n                <i class="bi bi-journal-text me-2"></i> Formula Sheets\n            </a>\n            <a href="past-questions.php">\n                <i class="bi bi-file-earmark-text me-2"></i> Past Questions\n            </a>\n            <a href="quizzes.php">\n                <i class="bi bi-question-circle me-2"></i> Quizzes\n            </a>
            <a href="generate-quizzes.php">
                <i class="bi bi-lightning me-2"></i> Generate Quizzes
            </a>\n            <a href="study-materials.php">\n                <i class="bi bi-book me-2"></i> Study Materials\n            </a>\n            <a href="student-progress.php">\n                <i class="bi bi-graph-up me-2"></i> Student Progress\n            </a>\n            <a href="student-view.php">\n                <i class="bi bi-eye me-2"></i> Student View\n            </a>\n            <a href="bulk-upload.php">\n                <i class="bi bi-cloud-upload me-2"></i> Bulk Upload\n            </a>\n            <a href="settings.php">\n                <i class="bi bi-gear me-2"></i> Settings\n            </a>\n            <a href="logout.php" class="mt-4 text-danger">\n                <i class="bi bi-box-arrow-right"></i> Logout\n            </a></div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Formula Sheet</h2>
                <a href="formula-sheets.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Formula Sheets
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
                    <form action="edit-formula.php?formula_id=<?php echo $formula_id; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_formula">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($formula['title'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($formula['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($formula['subject'] ?? '') === $key ? 'selected' : ''; ?>>
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
                                        <option value="<?php echo $key; ?>" <?php echo ($formula['level'] ?? '') === $key ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="topic" class="form-label">Topic</label>
                            <input type="text" class="form-control" id="topic" name="topic" value="<?php echo htmlspecialchars($formula['topic'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="content_type" name="content_type" required>
                                <option value="">Select Content Type</option>
                                <option value="file" <?php echo ($formula['content_type'] ?? '') === 'file' ? 'selected' : ''; ?>>File Upload</option>
                                <option value="text" <?php echo ($formula['content_type'] ?? '') === 'text' ? 'selected' : ''; ?>>Text Content</option>
                            </select>
                        </div>
                        
                        <!-- File Upload Section -->
                        <div id="fileUploadSection" class="mb-3 <?php echo ($formula['content_type'] ?? '') !== 'file' ? 'd-none' : ''; ?>">
                            <label for="formula_file" class="form-label">Upload New File</label>
                            <input type="file" class="form-control" id="formula_file" name="formula_file">
                            <div class="form-text">Leave empty to keep the current file. Upload a new file to replace the existing one.</div>
                            
                            <?php if (($formula['content_type'] ?? '') === 'file' && !empty($formula['file_path'])): ?>
                                <div class="mt-3">
                                    <label class="form-label">Current File</label>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark-pdf fs-4 me-2"></i>
                                        <div>
                                            <div><?php echo htmlspecialchars($formula['file_name'] ?? basename($formula['file_path'])); ?></div>
                                            <a href="../<?php echo htmlspecialchars($formula['file_path']); ?>" class="btn btn-sm btn-outline-primary mt-1" target="_blank">
                                                <i class="bi bi-eye me-1"></i> View File
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Text Content Section -->
                        <div id="textContentSection" class="mb-3 <?php echo ($formula['content_type'] ?? '') !== 'text' ? 'd-none' : ''; ?>">
                            <label for="formula_text" class="form-label">Formula Text <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="formula_text" name="formula_text" rows="6"><?php echo htmlspecialchars($formula['content_text'] ?? ''); ?></textarea>
                            <div class="form-text">
                                Enter formulas in plain text or LaTeX format. For LaTeX, use $ delimiters for inline math and $$ for display math.<br>
                                Example: The quadratic formula is $x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$
                            </div>
                            
                            <?php if (($formula['content_type'] ?? '') === 'text' && !empty($formula['content_text'])): ?>
                                <div class="mt-3">
                                    <label class="form-label">Preview</label>
                                    <div class="preview-card">
                                        <div id="formulaPreview">
                                            <?php echo nl2br(htmlspecialchars($formula['content_text'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="formula-sheets.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Formula Sheet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Show/hide content type sections based on selection
            $('#content_type').on('change', function() {
                const contentType = $(this).val();
                
                // Hide all content sections
                $('#fileUploadSection, #textContentSection').addClass('d-none');
                
                // Show the selected content section
                if (contentType === 'file') {
                    $('#fileUploadSection').removeClass('d-none');
                } else if (contentType === 'text') {
                    $('#textContentSection').removeClass('d-none');
                }
            });
            
            // Live preview for formula text
            $('#formula_text').on('input', function() {
                const text = $(this).val();
                $('#formulaPreview').html(text.replace(/\n/g, '<br>'));
                
                // Render math if MathJax is available
                if (typeof MathJax !== 'undefined') {
                    MathJax.typesetPromise();
                }
            });
            
            // Initialize MathJax for any formulas in the page
            if (typeof MathJax !== 'undefined') {
                MathJax.typesetPromise();
            }
        });
    </script>
</body>
</html>
