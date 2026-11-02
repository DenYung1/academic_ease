<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_session.php';
require_once '../../includes/subscription_helper.php';

// Verify admin is logged in with secure session
check_admin_login();

// Get admin data
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'];

// Initialize variables
$users = [];
$success_message = '';
$error_message = '';

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update user
    if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
        // Display all form data for debugging
        error_log("Update user form data: " . json_encode($_POST));
        
        $user_id = $_POST['user_id'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $user_type = $_POST['user_type'] ?? 'student';
        $subscription_status = $_POST['subscription_status'] ?? 'active';
        $subscription_plan = $_POST['subscription_plan'] ?? 'monthly';
        
        // Validate user_id
        if (empty($user_id)) {
            $error_message = 'Missing user ID. Please try again.';
            error_log("Update failed: Missing user ID");
        } else {
            error_log("Processing update for user ID: $user_id");
        }
        
        // Calculate subscription end date based on plan
        $subscription_end_date = date('c');
        if ($subscription_plan === 'weekly') {
            $subscription_end_date = date('c', strtotime('+7 days'));
        } else if ($subscription_plan === 'monthly') {
            $subscription_end_date = date('c', strtotime('+30 days'));
        } else if ($subscription_plan === 'termly') {
            $subscription_end_date = date('c', strtotime('+90 days'));
        } else if ($subscription_plan === 'trial') {
            $subscription_end_date = date('c', strtotime('+1 day'));
        }
        
        if (empty($full_name) || empty($email)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Update all user data in a single request
            $userData = [
                'full_name' => $full_name,
                'email' => $email,
                'user_type' => $user_type,
                'subscription_status' => $subscription_status,
                'subscription_plan' => $subscription_plan,
                'subscription_end_date' => $subscription_end_date
                // Removed admin_renewed field as it doesn't exist in the database
            ];
            
            // Add debugging to see what's happening with the request
            error_log("Updating user $user_id with all data: " . json_encode($userData));
            
            // Use service role key to ensure we have proper permissions
            $result = supabaseRequest("users?user_id=eq.$user_id", 'PATCH', $userData, true);
            
            // Log the result for debugging
            error_log("Update result: " . json_encode($result));

            // Check for specific error conditions
            if (isset($result['error']) && $result['error'] === true) {
                $error_message = 'Failed to update user: ' . ($result['message'] ?? 'Unknown error');
                error_log("Update error: " . ($result['message'] ?? 'Unknown error'));
            } else if (empty($result)) {
                $error_message = 'Failed to update user information. Please try again.';
            } else {
                $success_message = 'User updated successfully!';
                
                // If this is a student account, update any linked parent accounts
                if ($user_type === 'student') {
                    // Get all linked parents
                    $linked_parents = supabaseRequest("parent_child?child_id=eq.$user_id", 'GET', null, true);
                    
                    if (!empty($linked_parents)) {
                        foreach ($linked_parents as $link) {
                            $parent_id = $link['parent_id'];
                            
                            // Update parent with same subscription details
                            $parent_update = supabaseRequest("users?user_id=eq.$parent_id", 'PATCH', [
                                'subscription_status' => $subscription_status,
                                'subscription_plan' => $subscription_plan,
                                'subscription_end_date' => $subscription_end_date
                            ], true);
                            
                            if (!empty($parent_update)) {
                                error_log("Updated subscription for parent $parent_id to match student $user_id");
                            }
                        }
                    }
                }
            }
        }
    }

    // Delete user
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $user_id = $_POST['user_id'];
        
        // Enable detailed error logging
        error_log("Attempting to delete user ID: $user_id");
        
        // Get user details before deletion (for logging purposes)
        $user_to_delete = supabaseRequest("users?user_id=eq.$user_id", 'GET', null, true);
        
        if (!empty($user_to_delete) && isset($user_to_delete[0]['email'])) {
            $email = $user_to_delete[0]['email'];
            $name = $user_to_delete[0]['full_name'] ?? '';
            error_log("Found user to delete: $email ($name)");
            
            // Step 1: Find all conversations for this user
            error_log("Finding conversations for user $user_id");
            $conversations = supabaseRequest("conversations?user_id=eq.$user_id", 'GET', null, true);
            $conversation_count = count($conversations);
            error_log("Found $conversation_count conversations");
            
            // Step 2: Delete messages from those conversations (foreign key constraint)
            if ($conversation_count > 0) {
                $message_count = 0;
                
                foreach ($conversations as $conversation) {
                    $conversation_id = $conversation['conversation_id'];
                    // Delete messages in this conversation
                    $deleteResult = supabaseRequest("messages?conversation_id=eq.$conversation_id", 'DELETE', null, true);
                    $count = is_array($deleteResult) ? count($deleteResult) : 0;
                    $message_count += $count;
                    error_log("Deleted $count messages from conversation $conversation_id");
                }
                
                error_log("Deleted $message_count total messages");
            }
            
            // Step 3: Delete conversations
            $deleteConversations = supabaseRequest("conversations?user_id=eq.$user_id", 'DELETE', null, true);
            $conversation_deleted = is_array($deleteConversations) ? count($deleteConversations) : 0;
            error_log("Deleted $conversation_deleted conversations");
            
            // Step 4: Delete subscriptions
            $deleteSubscriptions = supabaseRequest("subscriptions?user_id=eq.$user_id", 'DELETE', null, true);
            $subs_deleted = is_array($deleteSubscriptions) ? count($deleteSubscriptions) : 0;
            error_log("Deleted $subs_deleted subscriptions");
            
            // Step 5: Delete parent-child relationships
            $deleteParentChildRelations1 = supabaseRequest("parent_child?parent_id=eq.$user_id", 'DELETE', null, true);
            $deleteParentChildRelations2 = supabaseRequest("parent_child?child_id=eq.$user_id", 'DELETE', null, true);
            error_log("Deleted parent-child relationships");
            
            // Step 6: Delete other user-related data
            $tables_to_clean = [
                'user_progress',
                'quiz_attempts',
                'student_notes',
                'user_preferences',
                'user_activity_log',
                'study_plans',  // Added to fix foreign key constraint issue
                'study_schedules'  // Added to fix another foreign key constraint issue
            ];
            
            // Make sure study_schedules is deleted first due to foreign key constraint
            $studySchedulesResult = supabaseRequest("study_schedules?user_id=eq.$user_id", 'DELETE', null, true);
            if (is_array($studySchedulesResult)) {
                $count = count($studySchedulesResult);
                error_log("Deleted $count study schedules");
            }
            
            // Then delete study_plans
            $studyPlansResult = supabaseRequest("study_plans?user_id=eq.$user_id", 'DELETE', null, true);
            if (is_array($studyPlansResult)) {
                $count = count($studyPlansResult);
                error_log("Deleted $count study plans");
            }
            
            // Delete remaining tables
            foreach ($tables_to_clean as $table) {
                if ($table !== 'study_plans' && $table !== 'study_schedules') { // Skip tables we already deleted
                    $deleteResult = supabaseRequest("$table?user_id=eq.$user_id", 'DELETE', null, true);
                    if (is_array($deleteResult)) {
                        $count = count($deleteResult);
                        if ($count > 0) {
                            error_log("Deleted $count records from $table");
                        }
                    }
                }
            }
            
            // Step 7: Finally delete the user
            $customHeaders = [
                'Prefer: return=minimal',
                'Accept: application/json'
            ];
            
            // Now try to delete the user
            $result = supabaseRequest("users?user_id=eq.$user_id", 'DELETE', null, true, $customHeaders);
            
            // Log the result for debugging
            error_log("Delete user result: " . json_encode($result));
            
            // Check if there was an error in the response
            if (is_array($result) && isset($result['error']) && $result['error'] === true) {
                $error_message = "Error deleting user: $email. " . ($result['message'] ?? 'Unknown error');
                error_log($error_message);
                
                // If it's a foreign key constraint error, provide more specific information
                if (isset($result['code']) && $result['code'] === '23503') {
                    // Parse the error details to get the table name
                    $details = $result['details'] ?? '';
                    if (preg_match('/table "([^"]+)"/', $details, $matches)) {
                        $table_name = $matches[1];
                        error_log("Foreign key constraint error from table: $table_name");
                        
                        // Attempt to delete from that specific table
                        $cleanup = supabaseRequest("$table_name?user_id=eq.$user_id", 'DELETE', null, true);
                        error_log("Attempted additional cleanup of $table_name: " . json_encode($cleanup));
                        
                        // Try deleting the user again
                        $retry_result = supabaseRequest("users?user_id=eq.$user_id", 'DELETE', null, true, $customHeaders);
                        if (!is_array($retry_result) || !isset($retry_result['error']) || $retry_result['error'] !== true) {
                            $success_message = "User '$email' deleted successfully after additional cleanup!";
                            error_log($success_message);
                            $_SESSION['success_message'] = $success_message;
                        } else {
                            $_SESSION['error_message'] = $error_message . " Additional cleanup of $table_name was attempted but failed.";
                        }
                    } else {
                        $_SESSION['error_message'] = $error_message;
                    }
                } else {
                    $_SESSION['error_message'] = $error_message;
                }
            } else {
                $success_message = "User '$email' deleted successfully!";
                error_log($success_message);
                $_SESSION['success_message'] = $success_message;
            }
            
            // Redirect back to users.php with a success/error message
            header('Location: users.php');
            exit;
        } else {
            $error_message = 'User not found.';
            error_log("User ID $user_id not found for deletion");
        }
    }
    
    // Add new user
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $user_type = $_POST['user_type'];
        $subscription_status = $_POST['subscription_status'];
        $subscription_plan = $_POST['subscription_plan'];
        
        // Calculate subscription end date based on plan
        $subscription_end_date = date('c');
        if ($subscription_plan === 'weekly') {
            $subscription_end_date = date('c', strtotime('+7 days'));
        } else if ($subscription_plan === 'monthly') {
            $subscription_end_date = date('c', strtotime('+30 days'));
        } else if ($subscription_plan === 'termly') {
            $subscription_end_date = date('c', strtotime('+90 days'));
        } else if ($subscription_plan === 'trial') {
            $subscription_end_date = date('c', strtotime('+1 day'));
        }
        
        if (empty($full_name) || empty($email) || empty($password)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            // Check if email already exists
            $existingUser = supabaseRequest("users?email=eq.".urlencode($email), 'GET', null, true);
            
            if (!empty($existingUser)) {
                $error_message = 'Email already exists. Please use a different email.';
            } else {
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user into database
                $userData = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'password_hash' => $password_hash,
                    'user_type' => $user_type,
                    'subscription_status' => $subscription_status,
                    'subscription_plan' => $subscription_plan,
                    'subscription_end_date' => $subscription_end_date,
                    'created_at' => date('c')
                ];
                
                $result = supabaseRequest('users', 'POST', $userData, true);
                
                if (!empty($result)) {
                    $success_message = 'User added successfully!';
                } else {
                    $error_message = 'Failed to add user. Please try again.';
                }
            }
        }
    }
    
    // Delete user
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $user_id = $_POST['user_id'];
        
        // Delete user
        $result = supabaseRequest("users?user_id=eq.$user_id", 'DELETE', null, true);
        
        if (!empty($result)) {
            $success_message = 'User deleted successfully!';
        } else {
            $error_message = 'Failed to delete user. Please try again.';
        }
    }
}

// Include parent-child helper functions
require_once '../../includes/parent_child_helper.php';

// Pagination parameters
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 100; // Number of users per page
$offset = ($page - 1) * $per_page;
$total_users = 0;
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Log pagination and filtering parameters
error_log("Admin Users: Loading page $page with $per_page users per page");
if (!empty($filter_type)) error_log("Admin Users: Filtering by type: $filter_type");
if (!empty($search_term)) error_log("Admin Users: Searching for: $search_term");

// Build the query
try {
    // Base query - NEVER show parent accounts in the admin dashboard
    if ($filter_type === 'parent') {
        // If they specifically ask for parents, show empty results instead
        $query = "users?user_id=eq.none&order=created_at.desc";
    } else {
        // Only show students and admins by default
        $query = "users?user_type=neq.parent&order=created_at.desc";
        
        // Apply other type filters if specified
        if (!empty($filter_type)) {
            $query = "users?user_type=eq." . urlencode($filter_type) . "&order=created_at.desc";
        }
    }
    
    if (!empty($search_term)) {
        // Search in full_name or email
        $query .= "&or=(full_name.ilike." . urlencode("%$search_term%") . ",email.ilike." . urlencode("%$search_term%") . ")";
    }
    
    // Get total count first (for pagination)
    $count_headers = ["Prefer: count=exact"];
    $count_response = supabaseRequest($query . "&limit=0", 'GET', null, true, $count_headers);
    
    // Extract count from headers
    $total_users = isset($count_response['count']) ? intval($count_response['count']) : 0;
    error_log("Admin Users: Total users found: $total_users");
    
    // Now get the paginated results
    $paginated_query = $query . "&limit=$per_page&offset=$offset";
    $users = supabaseRequest($paginated_query, 'GET', null, true);
    error_log("Admin Users: Retrieved " . count($users) . " users for current page");
    
    // Enhance user data with linked users information
    foreach ($users as &$user) {
        // Validate user data
        if (!isset($user['user_id']) || empty($user['user_id'])) {
            error_log("Admin Users: Skipping invalid user without user_id");
            continue;
        }
        
        // For parents, get their linked children
        if (isset($user['user_type']) && $user['user_type'] === 'parent') {
            try {
                $linked_children = get_linked_children($user['user_id']);
                // Ensure linked_children is an array
                $user['linked_users'] = is_array($linked_children) ? $linked_children : [];
                error_log("Admin Users: Parent {$user['user_id']} has " . count($user['linked_users']) . " linked children");
            } catch (Exception $e) {
                error_log("Admin Users: Error getting linked children for parent {$user['user_id']}: " . $e->getMessage());
                $user['linked_users'] = [];
            }
        }
        // For students, get their linked parent
        else if (isset($user['user_type']) && $user['user_type'] === 'student') {
            try {
                $linked_parent = get_student_parent($user['user_id']);
                // Ensure linked_parent is an array or object before using it
                $user['linked_users'] = (is_array($linked_parent) || is_object($linked_parent)) ? [$linked_parent] : [];
                $parent_count = count($user['linked_users']);
                error_log("Admin Users: Student {$user['user_id']} has " . $parent_count . " linked parent" . ($parent_count !== 1 ? 's' : ''));
            } catch (Exception $e) {
                error_log("Admin Users: Error getting linked parent for student {$user['user_id']}: " . $e->getMessage());
                $user['linked_users'] = [];
            }
        } else {
            $user['linked_users'] = [];
        }
    }
    unset($user); // Break the reference with the last element
} catch (Exception $e) {
    error_log('Admin Users Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading users.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Academic Ease Admin</title>
    
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
            <a href="users.php" class="active">
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
                <h2>User Management</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus me-1"></i> Add New User
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
                            <label for="filterUserType" class="form-label">Filter by User Type</label>
                            <select class="form-select" id="filterUserType">
                                <option value="">All Users</option>
                                <option value="student">Students</option>
                                <option value="parent">Parents</option>
                                <option value="admin">Administrators</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <label for="filterSubscriptionStatus" class="form-label">Filter by Subscription</label>
                            <select class="form-select" id="filterSubscriptionStatus">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="trial">Trial</option>
                                <option value="expired">Expired</option>
                                <option value="pending">Pending</option>
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
            
            <!-- Search and Filter Controls -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Users</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Name or email" value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="type" class="form-label">Filter by Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="" <?php echo $filter_type === '' ? 'selected' : ''; ?>>All Users</option>
                                <option value="student" <?php echo $filter_type === 'student' ? 'selected' : ''; ?>>Students Only</option>
                                <option value="parent" <?php echo $filter_type === 'parent' ? 'selected' : ''; ?>>Parents Only</option>
                                <option value="admin" <?php echo $filter_type === 'admin' ? 'selected' : ''; ?>>Admins Only</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="users.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Users Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">User Management</h5>
                    <span class="badge bg-primary"><?php echo $total_users; ?> total users</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Linked Users</th>
                                    <th>Subscription</th>
                                    <th>Plan</th>
                                    <th>Expires</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['user_type'] === 'student'): ?>
                                                    <span class="badge bg-primary">Student</span>
                                                <?php elseif ($user['user_type'] === 'parent'): ?>
                                                    <span class="badge bg-success">Parent</span>
                                                <?php elseif ($user['user_type'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($user['linked_users'])): ?>
                                                    <?php foreach ($user['linked_users'] as $linked_user): ?>
                                                        <div class="d-flex align-items-center mb-1">
                                                            <?php if ($user['user_type'] === 'parent'): ?>
                                                                <span class="badge bg-primary me-1">Child</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success me-1">Parent</span>
                                                            <?php endif; ?>
                                                            <a href="?highlight=<?php echo $linked_user['user_id']; ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($linked_user['full_name']); ?>
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <small class="text-muted">None</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['subscription_status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($user['subscription_status'] === 'trial'): ?>
                                                    <span class="badge bg-info">Trial</span>
                                                <?php elseif ($user['subscription_status'] === 'expired'): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php elseif ($user['subscription_status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $plan = $user['subscription_plan'] ?? 'none';
                                                    echo ucfirst($plan);
                                                ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $end_date = isset($user['subscription_end_date']) ? strtotime($user['subscription_end_date']) : 0;
                                                    $current_time = time();
                                                    
                                                    if ($end_date > $current_time) {
                                                        // Calculate days more precisely for better accuracy with short trials
                                                        $today_midnight = strtotime('today midnight');
                                                        $end_date_midnight = strtotime('midnight', $end_date);
                                                        $days_left = round(($end_date_midnight - $today_midnight) / (60 * 60 * 24));
                                                        
                                                        // For one-day trials, make sure it shows at least 1 day if not expired
                                                        if ($days_left == 0 && $end_date > $current_time) {
                                                            $days_left = 1;
                                                        }
                                                        
                                                        // For trials expiring today, show "expiring today" instead of "0 days"
                                                        if ($days_left == 0) {
                                                            echo date('M d, Y', $end_date) . " <small class='text-warning'>(expiring today)</small>";
                                                        } else {
                                                            $day_text = $days_left == 1 ? 'day' : 'days';
                                                            echo date('M d, Y', $end_date) . " <small class='text-muted'>($days_left $day_text)</small>";
                                                        }
                                                    } else if ($end_date > 0) {
                                                        echo "<span class='text-danger'>Expired</span>";
                                                    } else {
                                                        echo "N/A";
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary edit-user-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#editUserModal"
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                            data-user-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-user-type="<?php echo $user['user_type']; ?>"
                                                            data-subscription-status="<?php echo $user['subscription_status']; ?>"
                                                            data-subscription-plan="<?php echo $user['subscription_plan']; ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                            data-user-id="<?php echo $user['user_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php if ($total_users > $per_page): ?>
                        <div class="d-flex justify-content-between align-items-center mt-4">
                            <div>
                                <p class="text-muted mb-0">Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + count($users), $total_users); ?> of <?php echo $total_users; ?> users</p>
                            </div>
                            <nav aria-label="User pagination">
                                <ul class="pagination">
                                    <?php 
                                    // Calculate total pages
                                    $total_pages = ceil($total_users / $per_page);
                                    
                                    // Previous page link
                                    $prev_page = max(1, $page - 1);
                                    $prev_disabled = ($page <= 1) ? 'disabled' : '';
                                    
                                    // Next page link
                                    $next_page = min($total_pages, $page + 1);
                                    $next_disabled = ($page >= $total_pages) ? 'disabled' : '';
                                    
                                    // Build query string for pagination links
                                    $query_params = [];
                                    if (!empty($filter_type)) $query_params[] = "type=" . urlencode($filter_type);
                                    if (!empty($search_term)) $query_params[] = "search=" . urlencode($search_term);
                                    $query_string = !empty($query_params) ? '&' . implode('&', $query_params) : '';
                                    ?>
                                    
                                    <!-- First page -->
                                    <li class="page-item <?php echo $prev_disabled; ?>">
                                        <a class="page-link" href="?page=1<?php echo $query_string; ?>" aria-label="First">
                                            <span aria-hidden="true">&laquo;&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <!-- Previous page -->
                                    <li class="page-item <?php echo $prev_disabled; ?>">
                                        <a class="page-link" href="?page=<?php echo $prev_page . $query_string; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    // Show page numbers with ellipsis for large page counts
                                    $start_page = max(1, min($page - 2, $total_pages - 4));
                                    $end_page = min($total_pages, max($page + 2, 5));
                                    
                                    // Show first page if not included in the range
                                    if ($start_page > 1) {
                                        echo "<li class='page-item'><a class='page-link' href='?page=1{$query_string}'>1</a></li>";
                                        if ($start_page > 2) {
                                            echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                                        }
                                    }
                                    
                                    // Show page numbers
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        $active = ($i == $page) ? 'active' : '';
                                        echo "<li class='page-item {$active}'><a class='page-link' href='?page={$i}{$query_string}'>{$i}</a></li>";
                                    }
                                    
                                    // Show last page if not included in the range
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo "<li class='page-item disabled'><span class='page-link'>...</span></li>";
                                        }
                                        echo "<li class='page-item'><a class='page-link' href='?page={$total_pages}{$query_string}'>{$total_pages}</a></li>";
                                    }
                                    ?>
                                    
                                    <!-- Next page -->
                                    <li class="page-item <?php echo $next_disabled; ?>">
                                        <a class="page-link" href="?page=<?php echo $next_page . $query_string; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                    
                                    <!-- Last page -->
                                    <li class="page-item <?php echo $next_disabled; ?>">
                                        <a class="page-link" href="?page=<?php echo $total_pages . $query_string; ?>" aria-label="Last">
                                            <span aria-hidden="true">&raquo;&raquo;</span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="users.php" method="post">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6">
                                <label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="user_type" name="user_type" required>
                                    <option value="student">Student</option>
                                    <option value="parent">Parent</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="subscription_status" class="form-label">Subscription Status</label>
                                <select class="form-select" id="subscription_status" name="subscription_status">
                                    <option value="trial">Trial</option>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="subscription_plan" class="form-label">Subscription Plan</label>
                                <select class="form-select" id="subscription_plan" name="subscription_plan">
                                    <option value="trial">Trial</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="termly">Termly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="users.php" method="post">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_user_type" name="user_type" required>
                                    <option value="student">Student</option>
                                    <option value="parent">Parent</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_subscription_status" class="form-label">Subscription Status</label>
                                <select class="form-select" id="edit_subscription_status" name="subscription_status">
                                    <option value="trial">Trial</option>
                                    <option value="active">Active</option>
                                    <option value="expired">Expired</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="edit_subscription_plan" class="form-label">Subscription Plan</label>
                                <select class="form-select" id="edit_subscription_plan" name="subscription_plan">
                                    <option value="trial">Trial</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="termly">Termly</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user "<span id="delete_user_name"></span>"?</p>
                    <p class="text-danger mb-0"><strong>Warning:</strong> This action cannot be undone and will delete all associated data.</p>
                    <p class="mt-2">You will be redirected back to this page after the user is deleted.</p>
                </div>
                <div class="modal-footer">
                    <form action="users.php" method="post">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
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
            const usersTable = $('#usersTable').DataTable({
                order: [[6, 'desc']], // Sort by registration date by default
                pageLength: 10,
                language: {
                    search: "Search users:",
                    lengthMenu: "Show _MENU_ users per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ users"
                }
            });
            
            // Apply filters
            $('#filterUserType, #filterSubscriptionStatus').on('change', function() {
                const userType = $('#filterUserType').val();
                const subscriptionStatus = $('#filterSubscriptionStatus').val();
                
                // Clear existing filters
                usersTable.columns(2).search('').columns(3).search('');
                
                // Apply new filters
                if (userType) {
                    usersTable.columns(2).search(userType);
                }
                
                if (subscriptionStatus) {
                    usersTable.columns(3).search(subscriptionStatus);
                }
                
                usersTable.draw();
            });
            
            // Reset filters
            $('#resetFilters').on('click', function() {
                $('#filterUserType, #filterSubscriptionStatus').val('');
                usersTable.columns().search('').draw();
            });
            
            // Set edit user modal data - use event delegation for dynamically loaded content
            $(document).on('click', '.edit-user-btn', function() {
                const userId = $(this).data('user-id');
                const userName = $(this).data('user-name');
                const userEmail = $(this).data('user-email');
                const userType = $(this).data('user-type');
                const subscriptionStatus = $(this).data('subscription-status');
                const subscriptionPlan = $(this).data('subscription-plan');
                
                console.log('Edit button clicked for user:', userName); // Debug log
                
                $('#edit_user_id').val(userId);
                $('#edit_full_name').val(userName);
                $('#edit_email').val(userEmail);
                $('#edit_user_type').val(userType);
                $('#edit_subscription_status').val(subscriptionStatus);
                $('#edit_subscription_plan').val(subscriptionPlan);
            });
            
            // Also handle the modal shown event to ensure data is populated
            $('#editUserModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const userId = button.data('user-id');
                const userName = button.data('user-name');
                const userEmail = button.data('user-email');
                const userType = button.data('user-type');
                const subscriptionStatus = button.data('subscription-status');
                const subscriptionPlan = button.data('subscription-plan');
                
                console.log('Modal opening for user:', userName); // Debug log
                
                $('#edit_user_id').val(userId);
                $('#edit_full_name').val(userName);
                $('#edit_email').val(userEmail);
                $('#edit_user_type').val(userType);
                $('#edit_subscription_status').val(subscriptionStatus);
                $('#edit_subscription_plan').val(subscriptionPlan);
            });
            
            // Set delete user modal data
            $('#deleteUserModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const userId = button.data('user-id');
                const userName = button.data('user-name');
                
                $('#delete_user_id').val(userId);
                $('#delete_user_name').text(userName);
            });
        });
    </script>
</body>
</html>