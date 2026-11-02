<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get material ID from URL
$material_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$material_id) {
    header('Location: study.php');
    exit;
}

// Get user data
try {
    // Get user data
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $user = $users[0];
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if ($subscription_status === 'pending') {
        // Payment is pending, redirect to subscription page
        header('Location: subscribe-direct.php?pending=1');
        exit;
    } else if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    } else if ($subscription_status !== 'active' && $subscription_status !== 'trial') {
        // User doesn't have an active subscription or trial
        header('Location: subscribe-direct.php?incomplete=1');
        exit;
    }
    
    // Get material data from past_questions table
    $endpoint = 'past_questions?id=eq.' . urlencode($material_id) . '&exam_type=eq.study_material';
    $materials = supabaseRequest($endpoint, 'GET', null, true);
    
    if (empty($materials)) {
        // Material not found
        header('Location: study.php');
        exit;
    }
    
    $material = $materials[0];
    
    // Record view in study history
    $study_data = [
        'user_id' => $_SESSION['user_id'],
        'material_id' => $material_id,
        'view_date' => date('Y-m-d H:i:s')
    ];
    
    // Try to record the view - if the table doesn't exist, just continue
    try {
        $endpoint = 'material_views';
        supabaseRequest($endpoint, 'POST', $study_data, true);
    } catch (Exception $e) {
        // Just log the error and continue
        error_log('Could not record material view: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log('View Material Error: ' . $e->getMessage());
    $error = 'An error occurred while loading the material. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($material['title']) ? htmlspecialchars($material['title']) : 'Study Material'; ?> - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../assets/img/logo.png" alt="Academic Ease Logo" height="40">
                Academic Ease
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quiz.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="progress.php">Progress</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="subscribe-direct.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="study.php">Study Materials</a></li>
                        <li class="breadcrumb-item active"><?php echo isset($material['title']) ? htmlspecialchars($material['title']) : 'Study Material'; ?></li>
                    </ol>
                </nav>
                
                <!-- Material Content -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><?php echo isset($material['title']) ? htmlspecialchars($material['title']) : 'Study Material'; ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-primary me-2"><?php echo isset($material['subject']) ? ucfirst($material['subject']) : 'General'; ?></span>
                                <span class="badge bg-secondary me-2"><?php echo isset($material['level']) ? ($material['level'] === 'jhs' ? 'JHS' : 'SHS') : 'All Levels'; ?></span>
                                <span class="badge bg-info"><?php echo isset($material['year']) ? htmlspecialchars($material['year']) : date('Y'); ?></span>
                            </div>
                            <a href="study.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Materials
                            </a>
                        </div>
                        
                        <?php if (isset($material['description']) && !empty($material['description'])): ?>
                            <div class="alert alert-light mb-4">
                                <?php echo nl2br(htmlspecialchars($material['description'])); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="material-content mt-4">
                            <?php if (!empty($material['file_path'])): ?>
                                <div class="text-center p-4">
                                    <p class="mb-3">This study material is available as a downloadable file:</p>
                                    <a href="<?php echo htmlspecialchars('download.php?file=' . urlencode($material['file_path'])); ?>" class="btn btn-primary" target="_blank">
                                        <i class="bi bi-file-earmark-arrow-down me-2"></i> Download File
                                    </a>
                                </div>
                            <?php elseif (!empty($material['description'])): ?>
                                <div class="p-4 bg-light rounded">
                                    <h5 class="mb-3">Study Material Content:</h5>
                                    <div class="study-content">
                                        <?php echo nl2br(htmlspecialchars($material['description'])); ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    This material doesn't have any content to display. Please go back to the materials list and try a different option.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Added on: <?php echo isset($material['created_at']) ? date('M d, Y', strtotime($material['created_at'])) : 'Unknown date'; ?></small>
                            
                            <?php if (isset($material['content_type']) && $material['content_type'] === 'text'): ?>
                                <button class="btn btn-sm btn-success" onclick="window.print()">
                                    <i class="bi bi-printer me-1"></i> Print Material
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">Â© 2025 Academic Ease. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="terms.php" class="text-decoration-none text-muted me-3">Terms of Service</a>
                    <a href="privacy.php" class="text-decoration-none text-muted">Privacy Policy</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
