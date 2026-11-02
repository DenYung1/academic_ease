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
$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$quiz = null;
$questions = [];
$success_message = '';
$error_message = '';

// Validate quiz_id
if ($quiz_id <= 0) {
    header('Location: quizzes.php');
    exit;
}

// Get quiz details
try {
    // Ensure quiz_id is an integer
    $quiz_id = (int)$quiz_id;
    
    $filter = "quiz_id=eq.$quiz_id";
    $quizzes = supabaseRequest("quizzes?$filter", 'GET', null, true);
    
    if (empty($quizzes)) {
        header('Location: quizzes.php');
        exit;
    }
    
    $quiz = $quizzes[0];
    
    // Get quiz questions
    $filter = "quiz_id=eq.$quiz_id";
    $order = "order=question_order.asc";
    $questions = supabaseRequest("quiz_questions?$filter&$order", 'GET', null, true);
    
    // Debug the questions response
    error_log('Quiz Questions Response Type: ' . gettype($questions));
    if (is_string($questions)) {
        error_log('Quiz Questions Response (string): ' . $questions);
        // Try to decode JSON string if that's what we got
        $decoded = json_decode($questions, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $questions = $decoded;
            error_log('Successfully decoded questions JSON string to array');
        } else {
            error_log('Failed to decode questions JSON: ' . json_last_error_msg());
            $questions = [];
        }
    }
    
    // Final validation to ensure questions is an array
    if (!is_array($questions)) {
        error_log('Questions is not an array after processing, setting to empty array');
        $questions = [];
    }
    
    // Validate each question is an array with the expected keys
    foreach ($questions as $key => $question) {
        if (!is_array($question)) {
            error_log('Question at index ' . $key . ' is not an array: ' . gettype($question));
            unset($questions[$key]);
            continue;
        }
        
        // Ensure required keys exist
        $requiredKeys = ['question_id', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];
        foreach ($requiredKeys as $requiredKey) {
            if (!isset($question[$requiredKey])) {
                error_log('Question at index ' . $key . ' is missing required key: ' . $requiredKey);
                $questions[$key][$requiredKey] = '';
            }
        }
    }
} catch (Exception $e) {
    error_log('Admin Quiz Questions Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading quiz data.';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new question
    if (isset($_POST['action']) && $_POST['action'] === 'add_question') {
        $question_text = trim($_POST['question_text']);
        $option_a = trim($_POST['option_a']);
        $option_b = trim($_POST['option_b']);
        $option_c = trim($_POST['option_c']);
        $option_d = trim($_POST['option_d']);
        $correct_answer = trim($_POST['correct_answer']);
        $explanation = trim($_POST['explanation']);
        
        if (empty($question_text) || empty($option_a) || empty($option_b) || empty($correct_answer)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Calculate question order (next in sequence)
            $question_order = count($questions) + 1;
            
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
                    'question_count' => count($questions) + 1
                ];
                
                $updateResult = supabaseRequest("quizzes?quiz_id=eq.$quiz_id", 'PATCH', $updateData, true);
                
                $success_message = 'Question added successfully!';
                
                // Refresh questions list
                $filter = "quiz_id=eq.$quiz_id";
                $order = "order=question_order.asc";
                $questions = supabaseRequest("quiz_questions?$filter&$order", 'GET', null, true);
            } else {
                $error_message = 'Failed to add question. Please try again.';
            }
        }
    }
    
    // Delete question
    if (isset($_POST['action']) && $_POST['action'] === 'delete_question') {
        $question_id = $_POST['question_id'];
        
        // Delete the question
        $result = supabaseRequest("quiz_questions?question_id=eq.$question_id", 'DELETE', null, true);
        
        if (!empty($result)) {
            // Update question count in quiz
            $updateData = [
                'question_count' => count($questions) - 1
            ];
            
            $updateResult = supabaseRequest("quizzes?quiz_id=eq.$quiz_id", 'PATCH', $updateData, true);
            
            $success_message = 'Question deleted successfully!';
            
            // Refresh questions list
            $filter = "quiz_id=eq.$quiz_id";
            $order = "order=question_order.asc";
            $questions = supabaseRequest("quiz_questions?$filter&$order", 'GET', null, true);
        } else {
            $error_message = 'Failed to delete question. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Questions - Academic Ease Admin</title>
    
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
            bottom: 0;
            left: 0;
            width: 250px;
            padding-top: 20px;
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu {
            padding-top: 20px;
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
        
        .question-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 20px;
        }
        
        .correct-answer {
            background-color: rgba(25, 135, 84, 0.1);
            border-left: 3px solid #198754;
            padding: 10px 15px;
            border-radius: 4px;
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
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Quiz Questions</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="quizzes.php">Quizzes</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($quiz['title'] ?? 'Questions'); ?></li>
                        </ol>
                    </nav>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                    <i class="bi bi-plus-circle me-1"></i> Add New Question
                </button>
            </div>
            
            <!-- Quiz Info -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4><?php echo htmlspecialchars($quiz['title'] ?? ''); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></p>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-end">
                                <div class="me-3">
                                    <small class="d-block text-muted">Subject</small>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($quiz['subject'] ?? ''); ?></span>
                                </div>
                                <div class="me-3">
                                    <small class="d-block text-muted">Level</small>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($quiz['level'] ?? ''); ?></span>
                                </div>
                                <div>
                                    <small class="d-block text-muted">Questions</small>
                                    <span class="badge bg-success"><?php echo (int)($quiz['question_count'] ?? 0); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
            
            <!-- Questions List -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Questions (<?php echo count($questions); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($questions)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            No questions have been added to this quiz yet. Click "Add New Question" to get started.
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="card question-card mb-4">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Question <?php echo (int)$index + 1; ?></h6>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteQuestionModal" data-question-id="<?php echo $question['question_id']; ?>">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <p class="fw-bold"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <div class="form-check mb-2 <?php echo $question['correct_answer'] === 'A' ? 'correct-answer' : ''; ?>">
                                                <input class="form-check-input" type="radio" disabled <?php echo $question['correct_answer'] === 'A' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    A: <?php echo htmlspecialchars($question['option_a']); ?>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2 <?php echo $question['correct_answer'] === 'B' ? 'correct-answer' : ''; ?>">
                                                <input class="form-check-input" type="radio" disabled <?php echo $question['correct_answer'] === 'B' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    B: <?php echo htmlspecialchars($question['option_b']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check mb-2 <?php echo $question['correct_answer'] === 'C' ? 'correct-answer' : ''; ?>">
                                                <input class="form-check-input" type="radio" disabled <?php echo $question['correct_answer'] === 'C' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    C: <?php echo htmlspecialchars($question['option_c'] ?? ''); ?>
                                                </label>
                                            </div>
                                            <div class="form-check mb-2 <?php echo $question['correct_answer'] === 'D' ? 'correct-answer' : ''; ?>">
                                                <input class="form-check-input" type="radio" disabled <?php echo $question['correct_answer'] === 'D' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    D: <?php echo htmlspecialchars($question['option_d'] ?? ''); ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($question['explanation'])): ?>
                                        <div class="mt-3">
                                            <h6>Explanation:</h6>
                                            <p class="text-muted"><?php echo htmlspecialchars($question['explanation']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Question Modal -->
    <div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addQuestionModalLabel">Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="quiz-questions.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                    <input type="hidden" name="action" value="add_question">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="question_text" class="form-label">Question <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="option_a" class="form-label">Option A <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="option_a" name="option_a" required>
                            </div>
                            <div class="col-md-6">
                                <label for="option_b" class="form-label">Option B <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="option_b" name="option_b" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="option_c" class="form-label">Option C</label>
                                <input type="text" class="form-control" id="option_c" name="option_c">
                            </div>
                            <div class="col-md-6">
                                <label for="option_d" class="form-label">Option D</label>
                                <input type="text" class="form-control" id="option_d" name="option_d">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="correct_answer" class="form-label">Correct Answer <span class="text-danger">*</span></label>
                            <select class="form-select" id="correct_answer" name="correct_answer" required>
                                <option value="">Select Correct Answer</option>
                                <option value="A">Option A</option>
                                <option value="B">Option B</option>
                                <option value="C">Option C</option>
                                <option value="D">Option D</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="explanation" class="form-label">Explanation</label>
                            <textarea class="form-control" id="explanation" name="explanation" rows="3"></textarea>
                            <div class="form-text">Provide an explanation for the correct answer (optional).</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Question Modal -->
    <div class="modal fade" id="deleteQuestionModal" tabindex="-1" aria-labelledby="deleteQuestionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteQuestionModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this question?</p>
                    <p class="text-danger mb-0"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form action="quiz-questions.php?quiz_id=<?php echo $quiz_id; ?>" method="post">
                        <input type="hidden" name="action" value="delete_question">
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
    
    <script>
        // Set delete question modal data
        $('#deleteQuestionModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const questionId = button.data('question-id');
            $('#deleteQuestionId').val(questionId);
        });
        
        // Handle modal hidden event to ensure backdrop is removed
        $('.modal').on('hidden.bs.modal', function () {
            // Remove any lingering backdrop
            $('.modal-backdrop').remove();
            // Reset body classes
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        });
    </script>
</body>
</html>
