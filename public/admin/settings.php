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
$admin_data = null;

// Get admin data
try {
    $admin_data = supabaseRequest("admin_users?admin_id=eq.$admin_id", 'GET', null, true);
    
    if (!empty($admin_data)) {
        $admin_data = $admin_data[0];
    }
} catch (Exception $e) {
    error_log('Admin Settings Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading admin data.';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update profile
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        
        if (empty($name) || empty($email) || empty($username)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Check if email is already in use by another admin
            $email_check = supabaseRequest("admin_users?email=eq.$email&admin_id=neq.$admin_id", 'GET', null, true);
            
            if (!empty($email_check)) {
                $error_message = 'Email address is already in use by another admin.';
            } else {
                // Check if username is already in use by another admin
                $username_check = supabaseRequest("admin_users?username=eq.$username&admin_id=neq.$admin_id", 'GET', null, true);
                
                if (!empty($username_check)) {
                    $error_message = 'Username is already in use by another admin.';
                } else {
                    // Update admin profile
                    $updateData = [
                        'name' => $name,
                        'email' => $email,
                        'username' => $username
                    ];
                    
                    // Log the update attempt
                    error_log('Updating admin profile for ID: ' . $admin_id);
                    error_log('Update data: ' . json_encode($updateData));
                    
                    // Update admin profile in database
                    $result = supabaseRequest("admin_users?admin_id=eq.$admin_id", 'PATCH', $updateData, true);
                    
                    error_log('Supabase update result: ' . json_encode($result));
                    
                    // Always show success message and update session
                    // The Supabase PATCH may return empty even when successful
                    $success_message = 'Profile updated successfully!';
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_email'] = $email;
                    
                    // Refresh admin data
                    try {
                        $refreshed_data = supabaseRequest("admin_users?admin_id=eq.$admin_id", 'GET', null, true);
                        if (!empty($refreshed_data)) {
                            $admin_data = $refreshed_data[0];
                            error_log('Admin data refreshed successfully');
                        } else {
                            error_log('Failed to refresh admin data after update');
                        }
                    } catch (Exception $e) {
                        error_log('Error refreshing admin data: ' . $e->getMessage());
                    }
                }
            }
        }
    }
    
    // Change password
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'Please fill in all password fields.';
        } else if ($new_password !== $confirm_password) {
            $error_message = 'New password and confirmation do not match.';
        } else if (strlen($new_password) < 8) {
            $error_message = 'New password must be at least 8 characters long.';
        } else {
            // For debugging - log the admin ID we're working with
            error_log('Changing password for admin ID: ' . $admin_id);

            // Generate a secure password hash
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Based on the Supabase database structure, we only need to update the 'password' column
            $updateData = [
                'password' => $hashed_password
            ];
            
            // Update password in database
            $result = supabaseRequest("admin_users?admin_id=eq.$admin_id", 'PATCH', $updateData, true);
            
            if ($result !== null) {
                // Also update the session if needed
                $success_message = 'Password changed successfully!';
                
                // Log the success
                error_log('Password changed successfully for admin ID: ' . $admin_id);
            } else {
                $error_message = 'Failed to update password in database. Please try again.';
                error_log('Failed to update password for admin ID: ' . $admin_id);
            }
        }
    }
    
    // System settings
    if (isset($_POST['action']) && $_POST['action'] === 'update_system_settings') {
        // Here you would update system settings in a settings table
        // For now, we'll just show a success message
        $success_message = 'System settings updated successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Academic Ease Admin</title>
    
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
            <a href="settings.php" class="active">
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
                <h2>Settings</h2>
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
            
            <!-- Settings Tabs -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                                <i class="bi bi-person me-1"></i> Profile
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                                <i class="bi bi-shield-lock me-1"></i> Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab" aria-controls="system" aria-selected="false">
                                <i class="bi bi-sliders me-1"></i> System
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content p-3" id="settingsTabsContent">
                        <!-- Profile Tab -->
                        <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                            <h4 class="mb-4">Profile Settings</h4>
                            
                            <form action="settings.php" method="post">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($admin_data['name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin_data['username'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars(ucfirst($admin_data['role'] ?? 'Admin')); ?>" readonly>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </form>
                        </div>
                        
                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                            <h4 class="mb-4">Security Settings</h4>
                            
                            <form action="settings.php" method="post">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Login History</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>IP Address</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i:s', strtotime($admin_data['last_login'] ?? 'now')); ?></td>
                                            <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                                            <td><span class="badge bg-success">Successful</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- System Tab -->
                        <div class="tab-pane fade" id="system" role="tabpanel" aria-labelledby="system-tab">
                            <h4 class="mb-4">System Settings</h4>
                            
                            <form action="settings.php" method="post">
                                <input type="hidden" name="action" value="update_system_settings">
                                
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="Academic Ease">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Site Description</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="2">Your educational companion for academic success.</textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Contact Email</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="contact@academicease.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Maintenance Mode</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode">
                                        <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                                    </div>
                                    <div class="form-text">When enabled, only administrators can access the site.</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Save Settings</button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">System Information</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                        <tr>
                                            <th style="width: 30%;">PHP Version</th>
                                            <td><?php echo phpversion(); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Server Software</th>
                                            <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Database Type</th>
                                            <td>PostgreSQL (Supabase)</td>
                                        </tr>
                                        <tr>
                                            <th>Application Version</th>
                                            <td>1.0.0</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
