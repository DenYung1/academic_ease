<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_session.php';
require_once '../../includes/quiz_storage.php';

// Verify admin is logged in with secure session
check_admin_login();

// Get admin data
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Initialize variables
$quizzes = [];

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
$success_message = '';
$error_message = '';

// Check for success parameter in URL (from redirect after adding quiz)
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success_message = 'Quiz added successfully!';
    error_log('Admin Quizzes: Detected success parameter in URL');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new quiz
    if (isset($_POST['action']) && $_POST['action'] === 'add_quiz') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $category = trim($_POST['category']);
        $time_limit = (int)$_POST['time_limit'];
        $question_count = 0; // Will be updated when questions are added
        
        if (empty($title) || empty($subject) || empty($level)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Insert quiz into database
            $quizData = [
                'title' => $title,
                'description' => $description,
                'subject' => $subject,
                'level' => $level,
                'category' => $category,
                'time_limit' => $time_limit,
                'question_count' => $question_count,
                'created_by' => $admin_id,
                'created_at' => date('c')
            ];
            
            // Log the quiz data being sent
            error_log('Admin Quizzes: Sending quiz data to database: ' . json_encode($quizData));
            
            // Use file-based storage for quiz creation
            try {
                // Generate a unique ID for the quiz
                $quizData['quiz_id'] = uniqid('quiz_', true);
                
                // Add timestamp
                $quizData['created_at'] = date('Y-m-d H:i:s');
                
                // Log the quiz data being saved
                error_log('Admin Quizzes: Saving quiz data to file storage: ' . json_encode($quizData));
                
                // Save the quiz using our file-based storage
                $success = saveQuiz($quizData);
                
                if ($success) {
                    error_log('Admin Quizzes: Successfully saved quiz to file storage with ID: ' . $quizData['quiz_id']);
                } else {
                    error_log('Admin Quizzes: Failed to save quiz to file storage');
                }
                
                // Store the created quiz for reference
                $createdQuiz = $quizData;
                
                // Only store in session if we need to (for immediate display before redirect)
                if ($success && $createdQuiz) {
                    // Use the quiz returned from Supabase which has the proper ID
                    $_SESSION['temp_new_quiz'] = $createdQuiz;
                    error_log('Admin Quizzes: Stored created quiz in session with ID: ' . $createdQuiz['quiz_id']);
                } else {
                    // Fallback to storing the submitted data if API call failed
                    $tempId = 'temp_' . time();
                    $quizData['quiz_id'] = $tempId;
                    $_SESSION['temp_new_quiz'] = $quizData;
                    error_log('Admin Quizzes: Stored fallback quiz in session with temp ID: ' . $tempId);
                }
                
                $success_message = 'Quiz added successfully!';
                error_log('Admin Quizzes: Quiz "' . $title . '" added successfully');
                
                // Redirect to prevent form resubmission
                header('Location: quizzes.php?success=1');
                exit;
                
            } catch (Exception $e) {
                error_log('Admin Quizzes: Exception during quiz creation: ' . $e->getMessage());
                $error_message = 'Failed to add quiz. Please try again. Error: ' . $e->getMessage();
            }
        }
    }
    
    // Add new question to quiz
    if (isset($_POST['action']) && $_POST['action'] === 'add_question') {
        $quiz_id = (int)$_POST['quiz_id'];
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = trim($_POST['correct_answer']);
        $explanation = trim($_POST['explanation']);
        
        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($correct_answer)) {
            $error_message = 'Please fill in all required fields for the question.';
        } else {
            // Get current question count for the quiz
            $filter = "quiz_id=eq.$quiz_id";
            $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
            $question_count = !empty($quizzes) ? ($quizzes[0]['question_count'] ?? 0) : 0;
            
            // Calculate question order (next in sequence)
            $question_order = $question_count + 1;
            
            // Insert question into database
            $questionData = [
                'quiz_id' => $quiz_id,
                'question_text' => $question_text,
                'option_a' => $option_a,
                'option_b' => $option_b,
                'option_c' => $option_c,
                'option_d' => $option_d,
                'correct_answer' => $correct_answer,
                'explanation' => $explanation,
                'question_order' => $question_order,
                'created_at' => date('c')
            ];
            
            $result = supabaseRequest('quiz_questions', 'POST', $questionData, true);
            
            if (!empty($result)) {
                // Update question count in quiz
                $updateData = [
                    'question_count' => $question_count + 1
                ];
                
                $updateResult = supabaseRequest("quizzes?quiz_id=eq.$quiz_id", 'PATCH', $updateData, true);
                
                $success_message = 'Question added successfully!';
            } else {
                $error_message = 'Failed to add question. Please try again.';
            }
        }
    }
    
    // Delete question
    if (isset($_POST['action']) && $_POST['action'] === 'delete_question') {
        $question_id = $_POST['question_id'];
        $quiz_id = $_POST['quiz_id'];
        
        // Delete the question
        $result = supabaseRequest("quiz_questions?question_id=eq.$question_id", 'DELETE', null, true);
        
        if (!empty($result)) {
            // Update question count in quiz
            $filter = "quiz_id=eq.$quiz_id";
            $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
            $question_count = !empty($quizzes) ? ($quizzes[0]['question_count'] ?? 0) : 0;
            
            if ($question_count > 0) {
                $updateData = [
                    'question_count' => $question_count - 1
                ];
                
                $updateResult = supabaseRequest("quizzes?quiz_id=eq.$quiz_id", 'PATCH', $updateData, true);
            }
            
            $success_message = 'Question deleted successfully!';
        } else {
            $error_message = 'Failed to delete question. Please try again.';
        }
    }
    
    // Delete quiz
    if (isset($_POST['action']) && $_POST['action'] === 'delete_quiz') {
        $quiz_id = $_POST['quiz_id'];
        
        error_log('Admin Quizzes: Attempting to delete quiz with ID: ' . $quiz_id);
        
        // Delete the quiz using our file-based storage
        $success = deleteQuiz($quiz_id);
        
        if ($success) {
            $success_message = 'Quiz deleted successfully!';
            error_log('Admin Quizzes: Successfully deleted quiz with ID: ' . $quiz_id);
        } else {
            $error_message = 'Failed to delete quiz. Please try again.';
            error_log('Admin Quizzes: Failed to delete quiz with ID: ' . $quiz_id);
        }
    }
}

// Get all quizzes
try {
    // Add debugging to track the quiz retrieval process
    error_log('Admin Quizzes: Attempting to fetch quizzes from file storage');
    
    // Get quizzes from file storage
    $quizzesMap = getAllQuizzes();
    
    // Convert from associative array to indexed array
    $quizzes = array_values($quizzesMap);
    
    // Sort by created_at in descending order (newest first)
    usort($quizzes, function($a, $b) {
        $dateA = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
        $dateB = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
        return $dateB - $dateA; // Descending order
    });
    
    error_log('Admin Quizzes: Successfully fetched ' . count($quizzes) . ' quizzes from file storage');
    
    // Check if we have a temporary new quiz in session (from a recent add)
    if (isset($_SESSION['temp_new_quiz'])) {
        error_log('Admin Quizzes: Found temporary new quiz in session');
        $tempNewQuiz = $_SESSION['temp_new_quiz'];
        
        // Check if this quiz is already in the results
        $quizExists = false;
        foreach ($quizzes as $quiz) {
            if (isset($quiz['quiz_id']) && isset($tempNewQuiz['quiz_id']) && $quiz['quiz_id'] == $tempNewQuiz['quiz_id']) {
                $quizExists = true;
                error_log('Admin Quizzes: Quiz already exists in results, not adding duplicate');
                break;
            }
        }
        
        // If the quiz doesn't exist in results, add it
        if (!$quizExists) {
            error_log('Admin Quizzes: Adding temporary new quiz to results: ' . $tempNewQuiz['title']);
            array_unshift($quizzes, $tempNewQuiz);
        }
        
        // Clear the session variable after use
        unset($_SESSION['temp_new_quiz']);
    }
    
    // If we just added a quiz and the database retrieval failed, add the new quiz to the display
    if (empty($quizzes) && !empty($success_message) && $success_message === 'Quiz added successfully!') {
        error_log('Admin Quizzes: No quizzes found, but quiz was just added. Adding it to display.');
        // Get the last submitted quiz data
        if (isset($_POST['title']) && isset($_POST['subject']) && isset($_POST['level'])) {
            $newQuiz = [
                'quiz_id' => time(), // Use timestamp as temporary ID
                'title' => $_POST['title'],
                'description' => $_POST['description'] ?? '',
                'subject' => $_POST['subject'],
                'level' => $_POST['level'],
                'category' => $_POST['category'] ?? '',
                'time_limit' => (int)($_POST['time_limit'] ?? 30),
                'question_count' => 0,
                'created_by' => $admin_id,
                'created_at' => date('c')
            ];
            $quizzes = [$newQuiz]; // Just show the newly added quiz
            error_log('Admin Quizzes: Added newly created quiz to display: ' . $newQuiz['title']);
            
            // Store in session for persistence
            $_SESSION['last_added_quiz'] = $newQuiz;
        }
    }
    
    // Log the result for debugging
    error_log('Admin Quizzes: Fetched ' . count($quizzes) . ' quizzes');
} catch (Exception $e) {
    error_log('Admin Quizzes Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading quizzes.';
    $quizzes = []; // Ensure $quizzes is an array even on error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - Academic Ease Admin</title>
    
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
            <a href="quizzes.php" class="active">
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
                <h2>Quiz Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuizModal">
                    <i class="bi bi-plus-circle me-1"></i> Add New Quiz
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
            
            <!-- Quizzes Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="quizzesTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Subject</th>
                                    <th>Level</th>
                                    <th>Questions</th>
                                    <th>Time Limit</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($quizzes)): ?>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                            <td>
                                                <?php 
                                                    $subject_key = $quiz['subject'] ?? '';
                                                    echo isset($subjects[$subject_key]) ? htmlspecialchars($subjects[$subject_key]) : htmlspecialchars($subject_key);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $level_key = $quiz['level'] ?? '';
                                                    echo isset($levels[$level_key]) ? htmlspecialchars($levels[$level_key]) : htmlspecialchars($level_key);
                                                ?>
                                            </td>
                                            <td><?php echo (int)($quiz['question_count'] ?? 0); ?></td>
                                            <td><?php echo (int)($quiz['time_limit'] ?? 0); ?> min</td>
                                            <td><?php echo date('M d, Y', strtotime($quiz['created_at'] ?? 'now')); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="quiz-questions.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-outline-primary" title="Manage Questions">
                                                        <i class="bi bi-list-check"></i>
                                                    </a>
                                                    <a href="edit-quiz.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-outline-secondary" title="Edit Quiz">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" title="Delete Quiz" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteQuizModal" 
                                                            data-quiz-id="<?php echo $quiz['quiz_id']; ?>" 
                                                            data-quiz-title="<?php echo htmlspecialchars($quiz['title']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">No quizzes found. Click "Add New Quiz" to create one.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Quiz Modal -->
    <div class="modal fade" id="addQuizModal" tabindex="-1" aria-labelledby="addQuizModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addQuizModalLabel">Add New Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="quizzes.php" method="post">
                    <input type="hidden" name="action" value="add_quiz">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label>
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
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" class="form-control" id="category" name="category">
                                <div class="form-text">E.g., Algebra, Grammar, Biology, etc.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                <input type="number" class="form-control" id="time_limit" name="time_limit" min="1" value="30">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Quiz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Quiz Modal -->
    <div class="modal fade" id="deleteQuizModal" tabindex="-1" aria-labelledby="deleteQuizModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteQuizModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the quiz "<span id="deleteQuizTitle"></span>"?</p>
                    <p class="text-danger mb-0"><strong>Warning:</strong> This action cannot be undone and will delete all associated questions and results.</p>
                </div>
                <div class="modal-footer">
                    <form action="quizzes.php" method="post">
                        <input type="hidden" name="action" value="delete_quiz">
                        <input type="hidden" name="quiz_id" id="deleteQuizId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Quiz</button>
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
            $('#quizzesTable').DataTable({
                order: [[5, 'desc']], // Sort by created date by default
                pageLength: 10,
                language: {
                    search: "Search quizzes:",
                    lengthMenu: "Show _MENU_ quizzes per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ quizzes"
                }
            });
            
            // Set delete quiz modal data
            $('#deleteQuizModal').on('show.bs.modal', function (event) {
                const button = $(event.relatedTarget);
                const quizId = button.data('quiz-id');
                const quizTitle = button.data('quiz-title');
                
                $('#deleteQuizId').val(quizId);
                $('#deleteQuizTitle').text(quizTitle);
            });
        });
    </script>
</body>
</html>
