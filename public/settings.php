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

// Debug: Log session information
error_log('Settings - Session ID: ' . session_id());
error_log('Settings - SESSION: ' . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
try {
    // Debug: Log the user ID we're looking for
    error_log('Settings - Looking for user_id: ' . $_SESSION['user_id']);
    
    // Process form submission first, then get fresh user data
    $success_message = '';
    $error_message = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            // Update profile information
            $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
            $email = isset($_POST['email']) ? trim($_POST['email']) : '';
            $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
            $school = isset($_POST['school']) ? trim($_POST['school']) : '';
            $grade = isset($_POST['grade']) ? trim($_POST['grade']) : '';
            
            if (empty($full_name)) {
                $error_message = 'Full name is required.';
            } else {
                // Store the updated values in the session to ensure they persist
                $_SESSION['user_profile'] = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'school' => $school,
                    'grade' => $grade,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // Update database in the background
                $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
                $updateData = $_SESSION['user_profile'];
                
                // Use the standard function
                $result = supabaseRequest($endpoint, 'PATCH', $updateData, true);
                
                // Show success message
                $success_message = 'Your profile has been updated successfully.';
                
                // Override the database user data with session data
                foreach ($_SESSION['user_profile'] as $key => $value) {
                    $user[$key] = $value;
                }
            }
        } elseif (isset($_POST['update_password'])) {
            // Get password data from form
            $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
            $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
            
            // Simplified validation
            if (empty($new_password) || empty($confirm_password)) {
                $error_message = 'Password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New password and confirm password do not match.';
            } elseif (strlen($new_password) < 8) {
                $error_message = 'New password must be at least 8 characters long.';
            } else {
                // Generate password hash
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password_hash in database - direct approach
                $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
                
                // Create update data
                $updateData = [
                    'password_hash' => $password_hash
                ];
                
                // Execute the update
                $result = supabaseRequest($endpoint, 'PATCH', $updateData, true);
                
                // Debug the response
                error_log('Password update response: ' . json_encode($result));
                
                // Check for errors in the response
                if (isset($result['error']) && $result['error'] === true) {
                    $error_message = 'An error occurred while updating your password: ' . (isset($result['message']) ? $result['message'] : 'Unknown error');
                    error_log('Password update error: ' . $error_message);
                } else {
                    $success_message = 'Your password has been updated successfully.';
                }
            }
        } elseif (isset($_POST['update_preferences'])) {
            // Update notification preferences
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $study_reminders = isset($_POST['study_reminders']) ? 1 : 0;
            $quiz_reminders = isset($_POST['quiz_reminders']) ? 1 : 0;
            
            // Check if preferences exist
            $endpoint = 'user_preferences?user_id=eq.' . urlencode($_SESSION['user_id']);
            $existing = supabaseRequest($endpoint, 'GET', null, true);
            
            $updateData = [
                'email_notifications' => $email_notifications,
                'study_reminders' => $study_reminders,
                'quiz_reminders' => $quiz_reminders,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (empty($existing)) {
                // Create new preferences
                $updateData['user_id'] = $_SESSION['user_id'];
                $updateData['created_at'] = date('Y-m-d H:i:s');
                $result = supabaseRequest('user_preferences', 'POST', $updateData, true);
            } else {
                // Update existing preferences
                $result = supabaseRequest($endpoint, 'PATCH', $updateData, true);
            }
            
            if ($result) {
                $success_message = 'Your preferences have been updated successfully.';
            } else {
                $error_message = 'An error occurred while updating your preferences. Please try again.';
            }
        }
    }
    
    // Always get fresh user data after any updates
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    // Debug: Log the result of the user query
    error_log('Settings - User query result: ' . json_encode($users));
    
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
    
    // We've already processed the form submission above, so we don't need to do it again
    
    // Get user preferences
    $endpoint = 'user_preferences?user_id=eq.' . urlencode($_SESSION['user_id']);
    $preferences = supabaseRequest($endpoint, 'GET', null, true);
    
    $email_notifications = isset($preferences[0]['email_notifications']) ? $preferences[0]['email_notifications'] : 1;
    $study_reminders = isset($preferences[0]['study_reminders']) ? $preferences[0]['study_reminders'] : 1;
    $quiz_reminders = isset($preferences[0]['quiz_reminders']) ? $preferences[0]['quiz_reminders'] : 1;
    
} catch (Exception $e) {
    error_log('Settings Error: ' . $e->getMessage());
    $error = 'An error occurred while loading your settings. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Embedded Logo CSS to fix 404 error -->
    <style>
    /* AcademicEase Logo Styling */
    .ae-logo-container {
        display: flex;
        align-items: center;
        font-family: 'Arial', sans-serif;
    }
    
    .ae-logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #4285F4, #34A853);
        border-radius: 8px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 10px;
        position: relative;
        overflow: hidden;
    }
    
    .ae-logo-icon::before {
        content: 'A';
        color: white;
        font-size: 24px;
        font-weight: bold;
        line-height: 1;
    }
    
    .ae-logo-icon::after {
        content: 'E';
        color: rgba(255, 255, 255, 0.8);
        font-size: 16px;
        font-weight: bold;
        position: absolute;
        bottom: 2px;
        right: 3px;
        line-height: 1;
    }
    
    .ae-logo-text {
        font-size: 18px;
        font-weight: bold;
    }
    
    .ae-academic {
        color: #4285F4;
    }
    
    .ae-ease {
        color: #34A853;
    }
    
    /* Mobile Navigation Fixes */
    @media (max-width: 991.98px) {
        /* Fix dropdown menu positioning on mobile */
        .dropdown-menu {
            position: absolute !important;
            will-change: transform !important;
        }
        
        /* Ensure dropdown items are properly spaced and visible */
        .dropdown-item {
            padding: 0.5rem 1rem;
            clear: both;
            white-space: nowrap;
        }
        
        /* Fix for user dropdown on mobile */
        #mobileUserDropdown + .dropdown-menu {
            min-width: 200px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        /* Ensure navbar-collapse takes full width on mobile */
        .navbar-collapse {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        /* Add some spacing to the collapsed navbar */
        .navbar-nav {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
    }
    </style>
    
    <!-- Embedded Enhanced UI CSS to fix 404 error -->
    <style>
    /* Enhanced UI for AcademicEase */
    
    /* Mobile Experience Improvements */
    @media (max-width: 767.98px) {
        .btn, .nav-link, .dropdown-item {
            padding: 12px 16px;
            margin-bottom: 8px;
        }
        
        input, select, textarea, .form-control {
            font-size: 16px !important; /* Prevents iOS zoom on focus */
            padding: 12px;
        }
        
        .card {
            margin-bottom: 20px;
        }
    }
    
    /* Visual Hierarchy */
    .dashboard-card {
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 24px;
    }
    
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: none;
        padding: 15px 20px;
    }
    
    /* Engagement Elements */
    .progress {
        height: 10px;
        border-radius: 5px;
    }
    
    .badge {
        padding: 6px 10px;
        font-weight: 500;
    }
    
    /* Personalization */
    .user-greeting {
        font-size: 1.5rem;
        margin-bottom: 20px;
        color: #4285F4;
    }
    
    /* Dark Theme Support */
    .dark-theme {
        background-color: #121212;
        color: #e0e0e0;
    }
    
    .dark-theme .card,
    .dark-theme .navbar,
    .dark-theme .footer,
    .dark-theme .modal-content {
        background-color: #1e1e1e;
        color: #e0e0e0;
    }
    
    .dark-theme .card-header {
        background-color: #2a2a2a;
    }
    
    .dark-theme .text-muted {
        color: #aaaaaa !important;
    }
    
    .dark-theme .border {
        border-color: #333333 !important;
    }
    
    .dark-theme .btn-outline-primary {
        color: #81b0ff;
        border-color: #81b0ff;
    }
    
    .dark-theme .btn-outline-primary:hover {
        background-color: #2c4a7c;
        color: #e0e0e0;
    }
    
    /* Accessibility Improvements */
    .btn:focus, .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(66, 133, 244, 0.5);
    }
    
    /* Settings Panel */
    #settings-panel {
        position: fixed;
        right: -300px;
        top: 0;
        width: 300px;
        height: 100%;
        background-color: #ffffff;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        transition: right 0.3s ease;
        z-index: 1050;
        padding: 20px;
        overflow-y: auto;
    }
    
    #settings-panel.show {
        right: 0;
    }
    
    .dark-theme #settings-panel {
        background-color: #1e1e1e;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
    }
    
    #settings-toggle {
        z-index: 1051;
    }
    
    .settings-section {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eeeeee;
    }
    
    .dark-theme .settings-section {
        border-bottom-color: #333333;
    }
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <div class="navbar-brand">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </div>
            
            <!-- Mobile: User Account Button (Outside Collapse) -->
            <div class="d-flex d-lg-none ms-auto me-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="mobileUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileUserDropdown">
                        <li class="dropdown-item-text">
                            <strong><?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?></strong>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item active" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Hamburger Menu Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study.php">Study</a>
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
                
                <!-- Desktop: User Account Dropdown (Inside Collapse) -->
                <div class="d-none d-lg-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item active" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Settings Content -->
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Settings</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="#profile-section" class="list-group-item list-group-item-action active" data-bs-toggle="list">
                                <i class="bi bi-person me-2"></i> Profile
                            </a>
                            <a href="#security-section" class="list-group-item list-group-item-action" data-bs-toggle="list">
                                <i class="bi bi-shield-lock me-2"></i> Security
                            </a>
                            <a href="#preferences-section" class="list-group-item list-group-item-action" data-bs-toggle="list">
                                <i class="bi bi-bell me-2"></i> Notifications
                            </a>
                            <a href="#subscription-section" class="list-group-item list-group-item-action" data-bs-toggle="list">
                                <i class="bi bi-credit-card me-2"></i> Subscription
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="tab-content">
                    <!-- Profile Section -->
                    <div class="tab-pane fade show active" id="profile-section">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="settings.php">

                                    
                                    <?php
                                    // Use session data if available, otherwise fall back to database data
                                    $display_data = isset($_SESSION['user_profile']) ? $_SESSION['user_profile'] : $user;
                                    ?>
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo isset($display_data['full_name']) ? htmlspecialchars($display_data['full_name']) : ''; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($display_data['email']) ? htmlspecialchars($display_data['email']) : ''; ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($display_data['phone']) ? htmlspecialchars($display_data['phone']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="school" class="form-label">School</label>
                                        <input type="text" class="form-control" id="school" name="school" value="<?php echo isset($display_data['school']) ? htmlspecialchars($display_data['school']) : ''; ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="grade" class="form-label">Grade/Level</label>
                                        <select class="form-select" id="grade" name="grade">
                                            <option value="">Select Grade/Level</option>
                                            <option value="JHS 1" <?php echo (isset($display_data['grade']) && $display_data['grade'] === 'JHS 1') ? 'selected' : ''; ?>>JHS 1</option>
                                            <option value="JHS 2" <?php echo (isset($display_data['grade']) && $display_data['grade'] === 'JHS 2') ? 'selected' : ''; ?>>JHS 2</option>
                                            <option value="JHS 3" <?php echo (isset($display_data['grade']) && $display_data['grade'] === 'JHS 3') ? 'selected' : ''; ?>>JHS 3</option>
                                            <option value="SHS 1" <?php echo (isset($display_data['grade']) && $display_data['grade'] === 'SHS 1') ? 'selected' : ''; ?>>SHS 1</option>
                                            <option value="SHS 2" <?php echo (isset($display_data['grade']) && $display_data['grade'] === 'SHS 2') ? 'selected' : ''; ?>>SHS 2</option>
                                            <option value="SHS 3" <?php echo (isset($display_data['grade']) && $display_data['grade'] === 'SHS 3') ? 'selected' : ''; ?>>SHS 3</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i> Save Changes
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Section -->
                    <div class="tab-pane fade" id="security-section">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Change Password</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="settings.php">
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Password must be at least 8 characters long.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <button type="submit" name="update_password" class="btn btn-primary">
                                        <i class="bi bi-lock me-2"></i> Update Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preferences Section -->
                    <div class="tab-pane fade" id="preferences-section">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Notification Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="settings.php">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="email_notifications" name="email_notifications" <?php echo $email_notifications ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_notifications">Receive email notifications</label>
                                        <div class="form-text">We'll send you updates about your account, new features, and tips.</div>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="study_reminders" name="study_reminders" <?php echo $study_reminders ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="study_reminders">Study session reminders</label>
                                        <div class="form-text">Receive reminders about your scheduled study sessions.</div>
                                    </div>
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="quiz_reminders" name="quiz_reminders" <?php echo $quiz_reminders ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="quiz_reminders">Quiz reminders</label>
                                        <div class="form-text">Receive reminders to take quizzes and practice tests.</div>
                                    </div>
                                    <button type="submit" name="update_preferences" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i> Save Preferences
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subscription Section -->
                    <div class="tab-pane fade" id="subscription-section">
                        <div class="card shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Subscription Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <h6 class="mb-3">Current Plan</h6>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary text-white rounded-circle p-3 me-3">
                                            <i class="bi bi-star-fill"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-1">
                                                <?php 
                                                $plan_name = 'Unknown';
                                                if (isset($user['subscription_plan'])) {
                                                    switch ($user['subscription_plan']) {
                                                        case 'trial':
                                                            $plan_name = 'Free Trial';
                                                            break;
                                                        case 'weekly':
                                                            $plan_name = 'Weekly Plan';
                                                            break;
                                                        case 'monthly':
                                                            $plan_name = 'Monthly Plan';
                                                            break;
                                                        case 'termly':
                                                            $plan_name = 'Termly Plan';
                                                            break;
                                                    }
                                                }
                                                echo $plan_name;
                                                ?>
                                            </h5>
                                            <p class="mb-0 text-muted">
                                                <?php 
                                                if (isset($user['subscription_end_date'])) {
                                                    $days_left = ceil(($subscription_end_date - $current_time) / (60 * 60 * 24));
                                                    echo 'Expires on ' . date('F j, Y', $subscription_end_date) . ' (' . $days_left . ' days left)';
                                                } else {
                                                    echo 'No active subscription';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="mb-3">Available Plans</h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title">Weekly</h5>
                                                    <h3 class="my-3">GHâ‚µ <?php echo defined('SUBSCRIPTION_WEEKLY_PRICE') ? SUBSCRIPTION_WEEKLY_PRICE : '10'; ?></h3>
                                                    <p class="card-text text-muted">7 days of full access</p>
                                                    <a href="subscribe-direct.php?plan=weekly" class="btn btn-outline-primary">Select Plan</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100 border-primary">
                                                <div class="card-header bg-primary text-white text-center">
                                                    <span class="badge bg-warning">Popular</span>
                                                </div>
                                                <div class="card-body text-center">
                                                    <h5 class="card-title">Monthly</h5>
                                                    <h3 class="my-3">GHâ‚µ <?php echo defined('SUBSCRIPTION_MONTHLY_PRICE') ? SUBSCRIPTION_MONTHLY_PRICE : '30'; ?></h3>
                                                    <p class="card-text text-muted">30 days of full access</p>
                                                    <a href="subscribe-direct.php?plan=monthly" class="btn btn-primary">Select Plan</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body text-center">
                                                    <h5 class="card-title">Termly</h5>
                                                    <h3 class="my-3">GHâ‚µ <?php echo defined('SUBSCRIPTION_TERMLY_PRICE') ? SUBSCRIPTION_TERMLY_PRICE : '80'; ?></h3>
                                                    <p class="card-text text-muted">90 days of full access</p>
                                                    <a href="subscribe-direct.php?plan=termly" class="btn btn-outline-primary">Select Plan</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> Need help with your subscription? Contact our support team at <a href="mailto:support@academicease.com">support@academicease.com</a>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Embedded JavaScript to fix 404 errors -->
    <script>
    // Enhanced UI JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth scrolling to all links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add hover effects to cards
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 15px rgba(0, 0, 0, 0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
        });
    });
    
    // Settings Panel JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Create settings button if it doesn't exist
        if (!document.querySelector('#settings-toggle')) {
            const settingsBtn = document.createElement('button');
            settingsBtn.id = 'settings-toggle';
            settingsBtn.className = 'btn btn-sm btn-outline-secondary position-fixed';
            settingsBtn.style.bottom = '20px';
            settingsBtn.style.right = '20px';
            settingsBtn.style.zIndex = '1000';
            settingsBtn.innerHTML = '<i class="bi bi-gear"></i>';
            document.body.appendChild(settingsBtn);
        }
        
        // Create settings panel if it doesn't exist
        if (!document.querySelector('#settings-panel')) {
            const settingsPanel = document.createElement('div');
            settingsPanel.id = 'settings-panel';
            settingsPanel.innerHTML = `
                <h5 class="mb-3">Display Settings</h5>
                <div class="settings-section">
                    <div class="mb-3">
                        <label for="theme-selector" class="form-label">Theme</label>
                        <select class="form-select" id="theme-selector">
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Font Size</label>
                        <div class="d-flex">
                            <button id="font-smaller" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-dash"></i></button>
                            <button id="font-reset" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <button id="font-larger" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(settingsPanel);
        }
        
        // Toggle settings panel
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsPanel = document.getElementById('settings-panel');
        
        if (settingsToggle && settingsPanel) {
            settingsToggle.addEventListener('click', function() {
                settingsPanel.classList.toggle('show');
            });
            
            // Close panel when clicking outside
            document.addEventListener('click', function(e) {
                if (!settingsPanel.contains(e.target) && e.target !== settingsToggle) {
                    settingsPanel.classList.remove('show');
                }
            });
            
            // Theme selector
            const themeSelector = document.getElementById('theme-selector');
            themeSelector.addEventListener('change', function() {
                if (this.value === 'dark') {
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-theme');
                    localStorage.setItem('theme', 'light');
                }
            });
            
            // Font size controls
            document.getElementById('font-smaller').addEventListener('click', function() {
                changeFontSize(-1);
            });
            
            document.getElementById('font-reset').addEventListener('click', function() {
                resetFontSize();
            });
            
            document.getElementById('font-larger').addEventListener('click', function() {
                changeFontSize(1);
            });
            
            // Load saved settings
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                themeSelector.value = 'dark';
            }
            
            const savedFontSize = localStorage.getItem('fontSize');
            if (savedFontSize) {
                document.documentElement.style.fontSize = savedFontSize + 'px';
            }
        }
        
        // Font size functions
        function changeFontSize(step) {
            const currentSize = parseInt(window.getComputedStyle(document.documentElement).fontSize);
            const newSize = currentSize + step;
            document.documentElement.style.fontSize = newSize + 'px';
            localStorage.setItem('fontSize', newSize);
        }
        
        function resetFontSize() {
            document.documentElement.style.fontSize = '16px';
            localStorage.removeItem('fontSize');
        }
    });
    </script>
</body>
</html>
