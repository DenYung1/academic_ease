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
$past_questions = [];

// Comprehensive list of subjects for both JHS and SHS
$subjects = [
    // Core subjects for both JHS and SHS
    'mathematics' => 'Mathematics',
    'english' => 'English Language',
    'science' => 'Integrated Science',
    'social_studies' => 'Social Studies',
    
    // Additional JHS subjects
    'ict_jhs' => 'ICT (JHS)',
    'rme' => 'Religious and Moral Education',
    'ghanaian_language' => 'Ghanaian Language',
    'french' => 'French',
    'bdt' => 'Basic Design and Technology',
    'creative_arts' => 'Creative Arts',
    'physical_education' => 'Physical Education',
    
    // SHS Core subjects
    'core_mathematics' => 'Core Mathematics (SHS)',
    'english_language' => 'English Language (SHS)',
    'integrated_science' => 'Integrated Science (SHS)',
    'social_studies_shs' => 'Social Studies (SHS)',
    
    // SHS Elective subjects - Science
    'elective_mathematics' => 'Elective Mathematics',
    'biology' => 'Biology',
    'chemistry' => 'Chemistry',
    'physics' => 'Physics',
    'ict_shs' => 'ICT (SHS)',
    
    // SHS Elective subjects - Arts
    'literature' => 'Literature in English',
    'history' => 'History',
    'government' => 'Government',
    'economics' => 'Economics',
    'geography' => 'Geography',
    'religious_studies' => 'Religious Studies',
    
    // SHS Elective subjects - Business
    'accounting' => 'Accounting',
    'business_management' => 'Business Management',
    'financial_accounting' => 'Financial Accounting',
    'cost_accounting' => 'Cost Accounting',
    
    // SHS Elective subjects - Visual Arts
    'visual_art' => 'Visual Art',
    'graphic_design' => 'Graphic Design',
    'picture_making' => 'Picture Making',
    
    // SHS Elective subjects - Home Economics
    'food_and_nutrition' => 'Food and Nutrition',
    'management_in_living' => 'Management in Living',
    'clothing_and_textiles' => 'Clothing and Textiles',
    
    // Technical subjects
    'technical_drawing' => 'Technical Drawing',
    'woodwork' => 'Woodwork',
    'metalwork' => 'Metalwork',
    'auto_mechanics' => 'Auto Mechanics',
    'building_construction' => 'Building Construction',
    'electrical_engineering' => 'Electrical Engineering'
];

$levels = [
    'jhs' => 'Junior High School',
    'shs' => 'Senior High School'
];
$years = range(date('Y'), 2010);
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new past question
    if (isset($_POST['action']) && $_POST['action'] === 'add_past_question') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $year = trim($_POST['year']);
        $exam_type = trim($_POST['exam_type']);
        
        // Handle file upload
        $file_path = '';
        $file_name = '';
        
        if (isset($_FILES['question_file']) && $_FILES['question_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../../uploads/past_questions/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = basename($_FILES['question_file']['name']);
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_file_name = uniqid() . '_' . $file_name;
            $file_path = $upload_dir . $unique_file_name;
            
            // Move uploaded file
            if (move_uploaded_file($_FILES['question_file']['tmp_name'], $file_path)) {
                $file_path = 'uploads/past_questions/' . $unique_file_name;
            } else {
                $error_message = 'Failed to upload file. Please try again.';
            }
        } else if ($_FILES['question_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_message = 'File upload error: ' . $_FILES['question_file']['error'];
        }
        
        if (empty($title) || empty($subject) || empty($level) || empty($year) || empty($file_path)) {
            $error_message = 'Please fill in all required fields and upload a file.';
        } else if (empty($error_message)) {
            // Insert past question into database
            $questionData = [
                'title' => $title,
                'description' => $description,
                'subject' => $subject,
                'level' => $level,
                'year' => $year,
                'exam_type' => $exam_type,
                'file_path' => $file_path,
                'file_name' => $file_name,
                'created_by' => $admin_id,
                'created_at' => date('c')
            ];
            
            $result = supabaseRequest('past_questions', 'POST', $questionData, true);
            
            if (!empty($result)) {
                $success_message = 'Past question added successfully!';
            } else {
                $error_message = 'Failed to add past question. Please try again.';
            }
        }
    }
    
    // Delete past question
    if (isset($_POST['action']) && $_POST['action'] === 'delete_past_question') {
        $question_id = $_POST['question_id'];
        
        // Get file path before deleting
        $question = supabaseRequest("past_questions?question_id=eq.$question_id", 'GET', null, true);
        
        if (!empty($question) && isset($question[0]['file_path']) && !empty($question[0]['file_path'])) {
            $file_path = '../../' . $question[0]['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete the past question
        $result = supabaseRequest("past_questions?question_id=eq.$question_id", 'DELETE', null, true);
        
        if (!empty($result)) {
            $success_message = 'Past question deleted successfully!';
        } else {
            $error_message = 'Failed to delete past question. Please try again.';
        }
    }
}

// Get all past questions (excluding study materials)
try {
    $past_questions = supabaseRequest("past_questions?exam_type=neq.study_material&order=created_at.desc", 'GET', null, true);
} catch (Exception $e) {
    error_log('Admin Past Questions Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading past questions.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Questions - AcademicEase Admin</title>
    
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
            <a href="index.php">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
            <a href="users.php">
                <i class="bi bi-people me-2"></i> Users
            </a>
            <a href="formula-sheets.php">
                <i class="bi bi-journal-text me-2"></i> Formula Sheets
            </a>
            <a href="past-questions.php" class="active">
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
                <h2>Past Questions Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPastQuestionModal">
                    <i class="bi bi-plus-circle me-1"></i> Add New Past Question
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
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label for="filterSubject" class="form-label">Filter by Subject</label>
                            <select class="form-select" id="filterSubject">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label for="filterLevel" class="form-label">Filter by Level</label>
                            <select class="form-select" id="filterLevel">
                                <option value="">All Levels</option>
                                <?php foreach ($levels as $key => $value): ?>
                                    <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label for="filterYear" class="form-label">Filter by Year</label>
                            <select class="form-select" id="filterYear">
                                <option value="">All Years</option>
                                <?php foreach ($years as $year): ?>
                                    <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button id="resetFilters" class="btn btn-secondary w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Past Questions Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="pastQuestionsTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Level</th>
                                    <th>Year</th>
                                    <th>Exam Type</th>
                                    <th>Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($past_questions)): ?>
                                    <?php foreach ($past_questions as $question): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($question['title']); ?></td>
                                            <td>
                                                <?php 
                                                    $subject_key = $question['subject'] ?? '';
                                                    echo isset($subjects[$subject_key]) ? htmlspecialchars($subjects[$subject_key]) : htmlspecialchars($subject_key);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $level_key = $question['level'] ?? '';
                                                    echo isset($levels[$level_key]) ? htmlspecialchars($levels[$level_key]) : htmlspecialchars($level_key);
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($question['year'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($question['exam_type'] ?? ''); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($question['created_at'] ?? 'now')); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if (!empty($question['file_path'])): ?>
                                                        <a href="../<?php echo htmlspecialchars($question['file_path']); ?>" class="btn btn-outline-primary" title="View File" target="_blank">
                                                            <i class="bi bi-file-earmark-pdf"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="edit-past-question.php?question_id=<?php echo $question['question_id']; ?>" class="btn btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" title="Delete" 
                                                            data-bs-toggle="modal" data-bs-target="#deletePastQuestionModal" 
                                                            data-question-id="<?php echo $question['question_id']; ?>" 
                                                            data-question-title="<?php echo htmlspecialchars($question['title']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No past questions found. Click "Add New Past Question" to upload one.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Past Question Modal -->
    <div class="modal fade" id="addPastQuestionModal" tabindex="-1" aria-labelledby="addPastQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPastQuestionModalLabel">Add New Past Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="past-questions.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_past_question">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required>
                            <div class="form-text">E.g., "BECE Mathematics Paper 1 2022"</div>
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                <select class="form-select" id="year" name="year" required>
                                    <option value="">Select Year</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="exam_type" class="form-label">Exam Type <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="exam_type" name="exam_type" required>
                                <div class="form-text">E.g., BECE, WASSCE, Mock, etc.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="question_file" class="form-label">Upload File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="question_file" name="question_file" required>
                            <div class="form-text">Upload PDF file containing the past questions. (Max size: 10MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Past Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Past Question Modal -->
    <div class="modal fade" id="deletePastQuestionModal" tabindex="-1" aria-labelledby="deletePastQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePastQuestionModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the past question "<span id="deleteQuestionTitle"></span>"?</p>
                    <p class="text-danger mb-0"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form action="past-questions.php" method="post">
                        <input type="hidden" name="action" value="delete_past_question">
                        <input type="hidden" name="question_id" id="deleteQuestionId">
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
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            const questionsTable = $('#pastQuestionsTable').DataTable({
                order: [[5, 'desc']], // Sort by added date by default
                pageLength: 100,
                language: {
                    search: "Search past questions:",
                    lengthMenu: "Show _MENU_ questions per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ questions"
                }
            });
            
            // Apply filters
            $('#filterSubject, #filterLevel, #filterYear').on('change', function() {
                const subject = $('#filterSubject').val();
                const level = $('#filterLevel').val();
                const year = $('#filterYear').val();
                
                // Clear existing filters
                questionsTable.columns(1).search('').columns(2).search('').columns(3).search('');
                
                // Apply new filters
                if (subject) {
                    questionsTable.columns(1).search(subject);
                }
                
                if (level) {
                    questionsTable.columns(2).search(level);
                }
                
                if (year) {
                    questionsTable.columns(3).search(year);
                }
                
                questionsTable.draw();
            });
            
            // Reset filters
            $('#resetFilters').on('click', function() {
                $('#filterSubject, #filterLevel, #filterYear').val('');
                questionsTable.columns().search('').draw();
            });
            
            // Set delete modal data
            $('#deletePastQuestionModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const questionId = button.data('question-id');
                const questionTitle = button.data('question-title');
                
                $('#deleteQuestionId').val(questionId);
                $('#deleteQuestionTitle').text(questionTitle);
            });
        });
    </script>
</body>
</html>
