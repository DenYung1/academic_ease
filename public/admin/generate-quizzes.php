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
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_quizzes') {
    try {
        // Check if the generate_all_subjects checkbox is checked
        $generateForAllSubjects = isset($_POST['generate_all_subjects']) ? true : false;
        
        // Include and run the quiz generation script
        include_once __DIR__ . '/../../scripts/generate_standard_quizzes.php';
        
        // Success message will be set by the included script
        $success_message = "Quiz generation process completed. Created $createdCount quizzes with $errorCount errors.";
    } catch (Exception $e) {
        error_log('Quiz Generation Error: ' . $e->getMessage());
        $error_message = 'An error occurred during quiz generation: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Standard Quizzes - Academic Ease Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #f5f5f5;
        }
        
        .sidebar {
            background-color: #343a40;
            color: white;
            width: 250px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-header {
            padding: 20px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu {
            padding: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-menu a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        
        .quiz-template-card {
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .quiz-template-card.jhs {
            border-left-color: #28a745;
        }
        
        .quiz-template-card.shs {
            border-left-color: #dc3545;
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
            <a href="generate-quizzes.php" class="active">
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
                <h2>Generate Standard Quizzes</h2>
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
            
            <!-- Main Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h4 class="card-title">Generate Standard GES Quizzes</h4>
                    <p class="card-text">
                        This tool will automatically generate standard quizzes for both JHS and SHS subjects following the Ghana Education Service (GES) standards.
                        The quizzes will be created with appropriate questions and answers for each subject and level.
                    </p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Features:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Intelligently generates quizzes only for subjects that don't have quizzes yet</li>
                            <li>Each quiz clearly indicates its format (Mixed Format or Theory Only)</li>
                            <li>Includes both objective (multiple-choice) and theory (essay) questions to develop critical thinking</li>
                            <li>Provides detailed explanations and model answers for educational value</li>
                            <li>Covers a comprehensive range of subjects for both JHS and SHS levels following Ghana Education Service standards</li>
                            <li>Each quiz contains at least 5 challenging questions to prepare students for examinations</li>
                        </ul>
                    </div>
                    <p class="text-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Note:</strong> This process will create multiple quizzes at once. It may take a few moments to complete.
                    </p>
                    
                    <form action="generate-quizzes.php" method="post" class="mt-4">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="generateAllSubjects" name="generate_all_subjects" checked>
                            <label class="form-check-label" for="generateAllSubjects">Generate quizzes for all subjects (even if they already have quizzes)</label>
                        </div>
                        <input type="hidden" name="action" value="generate_quizzes">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-lightning-fill me-2"></i> Generate Standard Quizzes
                        </button>
                        <a href="quizzes.php" class="btn btn-outline-secondary btn-lg ms-2">
                            <i class="bi bi-arrow-left me-2"></i> Back to Quizzes
                        </a>
                    </form>
                </div>
            </div>
            
            <!-- Quiz Templates Preview -->
            <h4 class="mb-3">Quiz Templates That Will Be Generated</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <h5 class="text-success">JHS Quizzes</h5>
                    
                    <div class="card quiz-template-card jhs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">JHS Mathematics - Advanced Algebra (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced algebraic concepts for JHS students</p>
                            <span class="badge bg-success">JHS</span>
                            <span class="badge bg-primary">Mathematics</span>
                            <span class="badge bg-secondary">8 Questions</span>
                            <span class="badge bg-info">Mixed Format</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card jhs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">JHS Mathematics - Advanced Geometry (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced geometric concepts for JHS students</p>
                            <span class="badge bg-success">JHS</span>
                            <span class="badge bg-primary">Mathematics</span>
                            <span class="badge bg-secondary">5 Questions</span>
                            <span class="badge bg-info">Theory Questions</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card jhs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">JHS Social Studies - Advanced Civics (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced civics concepts for JHS students</p>
                            <span class="badge bg-success">JHS</span>
                            <span class="badge bg-primary">Social Studies</span>
                            <span class="badge bg-secondary">5 Questions</span>
                            <span class="badge bg-info">Theory Questions</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card jhs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">JHS English Language - Grammar</h5>
                            <p class="card-text">Standard quiz on English grammar for JHS students</p>
                            <span class="badge bg-success">JHS</span>
                            <span class="badge bg-primary">English</span>
                            <span class="badge bg-secondary">5 Questions</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card jhs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">JHS Integrated Science - Basic Concepts</h5>
                            <p class="card-text">Standard quiz covering basic science concepts for JHS students</p>
                            <span class="badge bg-success">JHS</span>
                            <span class="badge bg-primary">Science</span>
                            <span class="badge bg-secondary">5 Questions</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card jhs">
                        <div class="card-body">
                            <h5 class="card-title">JHS Social Studies - Citizenship</h5>
                            <p class="card-text">Standard quiz on citizenship concepts for JHS students</p>
                            <span class="badge bg-success">JHS</span>
                            <span class="badge bg-primary">Social Studies</span>
                            <span class="badge bg-secondary">5 Questions</span>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5 class="text-danger">SHS Quizzes</h5>
                    
                    <div class="card quiz-template-card shs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">SHS Physics - Advanced Mechanics (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced mechanics concepts for SHS students</p>
                            <span class="badge bg-danger">SHS</span>
                            <span class="badge bg-primary">Physics</span>
                            <span class="badge bg-secondary">6 Questions</span>
                            <span class="badge bg-info">Theory Questions</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card shs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">SHS Chemistry - Advanced Reactions (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced chemical reactions for SHS students</p>
                            <span class="badge bg-danger">SHS</span>
                            <span class="badge bg-primary">Chemistry</span>
                            <span class="badge bg-secondary">6 Questions</span>
                            <span class="badge bg-info">Theory Questions</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card shs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">SHS Biology - Advanced Genetics (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced genetic concepts for SHS students</p>
                            <span class="badge bg-danger">SHS</span>
                            <span class="badge bg-primary">Biology</span>
                            <span class="badge bg-secondary">6 Questions</span>
                            <span class="badge bg-info">Theory Questions</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card shs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">SHS Geography - Advanced Physical Geography (Difficult)</h5>
                            <p class="card-text">Challenging quiz on advanced physical geography concepts for SHS students</p>
                            <span class="badge bg-danger">SHS</span>
                            <span class="badge bg-primary">Geography</span>
                            <span class="badge bg-secondary">5 Questions</span>
                            <span class="badge bg-info">Theory Questions</span>
                            <span class="badge bg-warning text-dark">Difficult</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card shs mb-3">
                        <div class="card-body">
                            <h5 class="card-title">SHS Core Mathematics - Functions</h5>
                            <p class="card-text">Standard quiz covering functions for SHS students</p>
                            <span class="badge bg-danger">SHS</span>
                            <span class="badge bg-primary">Core Mathematics</span>
                            <span class="badge bg-secondary">5 Questions</span>
                        </div>
                    </div>
                    
                    <div class="card quiz-template-card shs">
                        <div class="card-body">
                            <h5 class="card-title">SHS English Language - Comprehension</h5>
                            <p class="card-text">Standard quiz on reading comprehension for SHS students</p>
                            <span class="badge bg-danger">SHS</span>
                            <span class="badge bg-primary">English Language</span>
                            <span class="badge bg-secondary">5 Questions</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-secondary mt-4">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Duplicate Prevention:</strong> When you click "Generate Standard Quizzes", the system will check for existing quizzes and only create new ones that don't already exist in the database. This prevents duplicate quizzes when regenerating.
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
