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
$formula_sheets = [];
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new formula sheet
    if (isset($_POST['action']) && $_POST['action'] === 'add_formula_sheet') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $topic = trim($_POST['topic']);
        $content_type = trim($_POST['content_type']);
        
        // Handle file upload
        $file_path = '';
        $file_name = '';
        
        if ($content_type === 'file' && isset($_FILES['formula_file']) && $_FILES['formula_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/formula_sheets/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = basename($_FILES['formula_file']['name']);
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = uniqid() . '_' . $file_name;
            $file_path = $upload_dir . $unique_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['formula_file']['tmp_name'], $file_path)) {
                $file_path = 'uploads/formula_sheets/' . $unique_file_name;
            } else {
                $error_message = 'Failed to upload file. Please try again.';
            }
        } else if ($content_type === 'file' && $_FILES['formula_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_message = 'File upload error: ' . $_FILES['formula_file']['error'];
        }
        
        if (empty($title) || empty($subject) || empty($level)) {
            $error_message = 'Please fill in all required fields.';
        } else if ($content_type === 'file' && empty($file_path)) {
            $error_message = 'Please upload a file.';
        } else if (empty($error_message)) {
            // Insert formula sheet into database
            $formulaData = [
                'title' => $title,
                'description' => $description,
                'subject' => $subject,
                'level' => $level,
                'topic' => $topic,
                'content_type' => $content_type,
                'file_path' => $file_path,
                'file_name' => $file_name,
                'created_by' => $admin_id,
                'created_at' => date('c')
            ];
            
            // If content is text, add it to the data
            if ($content_type === 'text' && isset($_POST['formula_text'])) {
                $formulaData['content_text'] = trim($_POST['formula_text']);
            }
            
            $result = supabaseRequest('formula_sheets', 'POST', $formulaData, true);
            
            if (!empty($result)) {
                $success_message = 'Formula sheet added successfully!';
            } else {
                $error_message = 'Failed to add formula sheet. Please try again.';
            }
        }
    }
    
    // Delete formula sheet
    if (isset($_POST['action']) && $_POST['action'] === 'delete_formula_sheet') {
        $formula_id = $_POST['formula_id'];
        
        // Get file path before deleting
        $formula = supabaseRequest("formula_sheets?formula_id=eq.$formula_id", 'GET', null, true);
        
        if (!empty($formula) && isset($formula[0]['file_path']) && !empty($formula[0]['file_path'])) {
            $file_path = '../../' . $formula[0]['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete the formula sheet
        $result = supabaseRequest("formula_sheets?formula_id=eq.$formula_id", 'DELETE', null, true);
        
        if (!empty($result)) {
            $success_message = 'Formula sheet deleted successfully!';
        } else {
            $error_message = 'Failed to delete formula sheet. Please try again.';
        }
    }
}

// Get all formula sheets
try {
    $formula_sheets = supabaseRequest("formula_sheets?order=created_at.desc", 'GET', null, true);
} catch (Exception $e) {
    error_log('Admin Formula Sheets Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading formula sheets.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formula Sheets - Academic Ease Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
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
        
        .formula-card {
            transition: transform 0.2s;
        }
        
        .formula-card:hover {
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
            <a href="formula-sheets.php" class="active">
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
            </a></div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Formula Sheets Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFormulaSheetModal">
                    <i class="bi bi-plus-circle me-1"></i> Add New Formula Sheet
                </button>
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
            
            <!-- Filter Controls -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="filterSubject" class="form-label">Filter by Subject</label>
                            <select class="form-select" id="filterSubject">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="filterLevel" class="form-label">Filter by Level</label>
                            <select class="form-select" id="filterLevel">
                                <option value="">All Levels</option>
                                <?php foreach ($levels as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button id="resetFilters" class="btn btn-secondary w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formula Sheets Grid -->
            <div class="row" id="formulaSheetsContainer">
                <?php if (!empty($formula_sheets)): ?>
                    <?php foreach ($formula_sheets as $formula): ?>
                        <div class="col-md-6 col-lg-4 mb-4 formula-item" 
                             data-subject="<?php echo htmlspecialchars($formula['subject'] ?? ''); ?>"
                             data-level="<?php echo htmlspecialchars($formula['level'] ?? ''); ?>">
                            <div class="card h-100 formula-card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($formula['title']); ?></h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="edit-formula.php?formula_id=<?php echo $formula['formula_id']; ?>">
                                                    <i class="bi bi-pencil me-2"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" type="button" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteFormulaSheetModal" 
                                                        data-formula-id="<?php echo $formula['formula_id']; ?>" 
                                                        data-formula-title="<?php echo htmlspecialchars($formula['title']); ?>">
                                                    <i class="bi bi-trash me-2"></i> Delete
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <span class="badge bg-primary"><?php echo isset($subjects[$formula['subject']]) ? htmlspecialchars($subjects[$formula['subject']]) : htmlspecialchars($formula['subject'] ?? ''); ?></span>
                                        <span class="badge bg-secondary"><?php echo isset($levels[$formula['level']]) ? htmlspecialchars($levels[$formula['level']]) : htmlspecialchars($formula['level'] ?? ''); ?></span>
                                        <?php if (!empty($formula['topic'])): ?>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($formula['topic']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (!empty($formula['description'])): ?>
                                        <p class="card-text"><?php echo htmlspecialchars($formula['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (($formula['content_type'] ?? '') === 'file' && !empty($formula['file_path'])): ?>
                                        <div class="mt-3">
                                            <a href="../<?php echo htmlspecialchars($formula['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> View Formula Sheet
                                            </a>
                                        </div>
                                    <?php elseif (($formula['content_type'] ?? '') === 'text' && !empty($formula['content_text'])): ?>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#formulaContent<?php echo $formula['formula_id']; ?>">
                                                <i class="bi bi-eye me-1"></i> View Formulas
                                            </button>
                                        </div>
                                        <div class="collapse mt-3" id="formulaContent<?php echo $formula['formula_id']; ?>">
                                            <div class="card card-body bg-light">
                                                <div class="formula-text">
                                                    <?php echo nl2br(htmlspecialchars($formula['content_text'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer text-muted">
                                    <small>Added: <?php echo date('M d, Y', strtotime($formula['created_at'] ?? 'now')); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            No formula sheets found. Click "Add New Formula Sheet" to create one.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Formula Sheet Modal -->
    <div class="modal fade" id="addFormulaSheetModal" tabindex="-1" aria-labelledby="addFormulaSheetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addFormulaSheetModalLabel">Add New Formula Sheet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="formula-sheets.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_formula_sheet">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                            <div class="form-text">E.g., "Algebra Formulas", "Physics Equations", etc.</div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"></textarea>
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
                            <label for="topic" class="form-label">Topic</label>
                            <input type="text" class="form-control" id="topic" name="topic">
                            <div class="form-text">E.g., "Quadratic Equations", "Newton's Laws", etc.</div>
                        </div>
                        <div class="mb-3">
                            <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="content_type" name="content_type" required>
                                <option value="">Select Content Type</option>
                                <option value="file">File Upload</option>
                                <option value="text">Text Content</option>
                            </select>
                        </div>
                        
                        <!-- File Upload (shown when content_type is 'file') -->
                        <div id="fileUploadSection" class="mb-3 d-none">
                            <label for="formula_file" class="form-label">Upload File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="formula_file" name="formula_file">
                            <div class="form-text">Upload PDF file containing the formulas. (Max size: 10MB)</div>
                        </div>
                        
                        <!-- Text Content (shown when content_type is 'text') -->
                        <div id="textContentSection" class="mb-3 d-none">
                            <label for="formula_text" class="form-label">Formula Text <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="formula_text" name="formula_text" rows="6"></textarea>
                            <div class="form-text">
                                Enter formulas in plain text or LaTeX format. For LaTeX, use $ delimiters for inline math and $$ for display math.<br>
                                Example: The quadratic formula is $x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Formula Sheet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Formula Sheet Modal -->
    <div class="modal fade" id="deleteFormulaSheetModal" tabindex="-1" aria-labelledby="deleteFormulaSheetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteFormulaSheetModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the formula sheet "<span id="deleteFormulaTitle"></span>"?</p>
                    <p class="text-danger mb-0"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form action="formula-sheets.php" method="post">
                        <input type="hidden" name="action" value="delete_formula_sheet">
                        <input type="hidden" name="formula_id" id="deleteFormulaId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
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
            
            // Apply filters
            $('#filterSubject, #filterLevel').on('change', function() {
                const subject = $('#filterSubject').val();
                const level = $('#filterLevel').val();
                
                $('.formula-item').each(function() {
                    const itemSubject = $(this).data('subject');
                    const itemLevel = $(this).data('level');
                    
                    let showItem = true;
                    
                    if (subject && itemSubject !== subject) {
                        showItem = false;
                    }
                    
                    if (level && itemLevel !== level) {
                        showItem = false;
                    }
                    
                    $(this).toggle(showItem);
                });
                
                // Show message if no items are visible
                if ($('.formula-item:visible').length === 0) {
                    if ($('#noItemsMessage').length === 0) {
                        $('#formulaSheetsContainer').append('<div id="noItemsMessage" class="col-12"><div class="alert alert-info">No formula sheets found matching the selected filters.</div></div>');
                    }
                } else {
                    $('#noItemsMessage').remove();
                }
            });
            
            // Reset filters
            $('#resetFilters').on('click', function() {
                $('#filterSubject, #filterLevel').val('');
                $('.formula-item').show();
                $('#noItemsMessage').remove();
            });
            
            // Set delete modal data
            $('#deleteFormulaSheetModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const formulaId = button.data('formula-id');
                const formulaTitle = button.data('formula-title');
                
                $('#deleteFormulaId').val(formulaId);
                $('#deleteFormulaTitle').text(formulaTitle);
            });
            
            // Initialize MathJax for any formulas in the page
            if (typeof MathJax !== 'undefined') {
                MathJax.typesetPromise();
            }
        });
    </script>
</body>
</html>
