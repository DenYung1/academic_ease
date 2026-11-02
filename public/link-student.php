<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session
session_start();

// Check if user is logged in and is a parent
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'parent') {
    header('Location: login.php');
    exit;
}

// Initialize variables
$success = '';
$error = '';
$parent_id = $_SESSION['user_id'];
$linked_students = [];

// Get parent data
try {
    $users = supabaseSelect('users', [
        'filter' => [
            'user_id.eq' => $parent_id
        ]
    ]);
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $parent = $users[0];
    
    // Get existing linked students
    try {
        $linked_students_data = supabaseSelect('parent_student_links', [
            'filter' => [
                'parent_id.eq' => $parent_id
            ]
        ]);
        
        if (!empty($linked_students_data)) {
            foreach ($linked_students_data as $link) {
                $student_data = supabaseSelect('users', [
                    'filter' => [
                        'user_id.eq' => $link['student_id']
                    ]
                ]);
                
                if (!empty($student_data)) {
                    $linked_students[] = $student_data[0];
                }
            }
        }
    } catch (Exception $e) {
        // Table might not exist yet, we'll create it when linking a student
        error_log('Error fetching linked students: ' . $e->getMessage());
    }
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'link_existing') {
            // Link existing student account
            $student_email = trim($_POST['student_email']);
            
            if (empty($student_email)) {
                $error = 'Please enter the student\'s email address.';
            } else {
                // Check if student exists
                $student_data = supabaseSelect('users', [
                    'filter' => [
                        'email.eq' => $student_email,
                        'user_type.eq' => 'student'
                    ]
                ]);
                
                if (empty($student_data)) {
                    $error = 'No student account found with that email address.';
                } else {
                    $student = $student_data[0];
                    $student_id = $student['user_id'];
                    
                    // Check if already linked
                    $existing_link = false;
                    foreach ($linked_students as $linked_student) {
                        if ($linked_student['user_id'] === $student_id) {
                            $existing_link = true;
                            break;
                        }
                    }
                    
                    if ($existing_link) {
                        $error = 'This student is already linked to your account.';
                    } else {
                        // Create parent_student_links table if it doesn't exist
                        try {
                            // First, check if the table exists by trying to query it
                            supabaseRequest('parent_student_links?limit=1', 'GET', null, true);
                        } catch (Exception $e) {
                            // Table doesn't exist, create it
                            $create_table_sql = "
                                CREATE TABLE parent_student_links (
                                    link_id UUID PRIMARY KEY,
                                    parent_id UUID NOT NULL,
                                    student_id UUID NOT NULL,
                                    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
                                    status TEXT DEFAULT 'active',
                                    FOREIGN KEY (parent_id) REFERENCES users(user_id),
                                    FOREIGN KEY (student_id) REFERENCES users(user_id),
                                    UNIQUE(parent_id, student_id)
                                );
                            ";
                            
                            // Use a direct SQL query to create the table
                            // Note: This is a simplified example, in a real app you would use proper migration tools
                            error_log('Creating parent_student_links table');
                        }
                        
                        // Create link
                        $link_id = generate_uuid();
                        $link_data = [
                            'link_id' => $link_id,
                            'parent_id' => $parent_id,
                            'student_id' => $student_id,
                            'created_at' => date('c'),
                            'status' => 'active'
                        ];
                        
                        $result = supabaseRequest('parent_student_links', 'POST', $link_data, true);
                        
                        if (isset($result['error'])) {
                            $error = 'Failed to link student: ' . $result['message'];
                        } else {
                            $success = 'Student linked successfully!';
                            // Refresh page to show updated list
                            header('Location: link-student.php?success=1');
                            exit;
                        }
                    }
                }
            }
        } else if ($action === 'create_new') {
            // Create new student account and link
            $student_name = trim($_POST['student_name']);
            $student_school_level = trim($_POST['student_school_level']);
            
            if (empty($student_name) || empty($student_school_level)) {
                $error = 'Please fill in all required fields.';
            } else {
                // Generate temporary password
                $temp_password = substr(md5(rand()), 0, 10);
                $password_hash = password_hash($temp_password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                
                // Generate student ID
                $student_id = generate_uuid();
                
                // Create student user
                $new_student = [
                    'user_id' => $student_id,
                    'email' => '', // Will need to be set later
                    'phone_number' => '', // Will need to be set later
                    'password_hash' => $password_hash,
                    'full_name' => $student_name,
                    'user_type' => 'student',
                    'school_level' => $student_school_level,
                    'created_at' => date('c'),
                    'created_by_parent' => $parent_id,
                    'subscription_status' => 'trial',
                    'subscription_plan' => 'trial',
                    'subscription_start_date' => date('c'),
                    'subscription_end_date' => date('c', strtotime('+1 day')),
                    'requires_setup' => true
                ];
                
                $result = supabaseRequest('users', 'POST', $new_student, true);
                
                if (isset($result['error'])) {
                    $error = 'Failed to create student account: ' . $result['message'];
                } else {
                    // Create parent_student_links table if it doesn't exist
                    try {
                        // First, check if the table exists by trying to query it
                        supabaseRequest('parent_student_links?limit=1', 'GET', null, true);
                    } catch (Exception $e) {
                        // Table doesn't exist, create it
                        $create_table_sql = "
                            CREATE TABLE parent_student_links (
                                link_id UUID PRIMARY KEY,
                                parent_id UUID NOT NULL,
                                student_id UUID NOT NULL,
                                created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
                                status TEXT DEFAULT 'active',
                                FOREIGN KEY (parent_id) REFERENCES users(user_id),
                                FOREIGN KEY (student_id) REFERENCES users(user_id),
                                UNIQUE(parent_id, student_id)
                            );
                        ";
                        
                        // Use a direct SQL query to create the table
                        // Note: This is a simplified example, in a real app you would use proper migration tools
                        error_log('Creating parent_student_links table');
                    }
                    
                    // Create link
                    $link_id = generate_uuid();
                    $link_data = [
                        'link_id' => $link_id,
                        'parent_id' => $parent_id,
                        'student_id' => $student_id,
                        'created_at' => date('c'),
                        'status' => 'active'
                    ];
                    
                    $link_result = supabaseRequest('parent_student_links', 'POST', $link_data, true);
                    
                    if (isset($link_result['error'])) {
                        $error = 'Student account created but failed to link: ' . $link_result['message'];
                    } else {
                        $success = 'Student account created and linked successfully! Temporary password: ' . $temp_password;
                        // Refresh page to show updated list
                        header('Location: link-student.php?success=1&temp_password=' . urlencode($temp_password));
                        exit;
                    }
                }
            }
        }
    }
    
    // Check for success message in URL
    if (isset($_GET['success']) && $_GET['success'] == '1') {
        $success = 'Student linked successfully!';
        if (isset($_GET['temp_password'])) {
            $success .= ' Temporary password: ' . $_GET['temp_password'];
        }
    }
    
} catch (Exception $e) {
    $error = 'An error occurred: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Student Account - Academic Ease</title>
    
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
                        <a class="nav-link" href="parent-dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Children</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Messages</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="logout.php" class="btn btn-outline-danger btn-sm">Sign Out</a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="h2 mb-4">Link Student Account</h1>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>
                
                <!-- Current Linked Students -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Currently Linked Students</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($linked_students)): ?>
                        <p class="text-muted">You don't have any student accounts linked yet.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>School Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($linked_students as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                    <i class="bi bi-person-fill text-white"></i>
                                                </span>
                                                <?php echo htmlspecialchars($student['full_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($student['school_level'] ?? 'Not specified'); ?></td>
                                        <td>
                                            <a href="parent-dashboard.php?student=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-primary">View Progress</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Link Options -->
                <div class="row">
                    <!-- Link Existing Account -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">Link Existing Student Account</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">If your child already has an Academic Ease account, you can link it here.</p>
                                
                                <form method="post" action="link-student.php">
                                    <input type="hidden" name="action" value="link_existing">
                                    
                                    <div class="mb-3">
                                        <label for="student_email" class="form-label">Student's Email Address</label>
                                        <input type="email" class="form-control" id="student_email" name="student_email" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary">Link Account</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Create New Account -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Create New Student Account</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Create a new account for your child and link it to your parent account.</p>
                                
                                <form method="post" action="link-student.php">
                                    <input type="hidden" name="action" value="create_new">
                                    
                                    <div class="mb-3">
                                        <label for="student_name" class="form-label">Student's Full Name</label>
                                        <input type="text" class="form-control" id="student_name" name="student_name" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="student_school_level" class="form-label">School Level</label>
                                        <select class="form-select" id="student_school_level" name="student_school_level" required>
                                            <option value="" selected disabled>Select school level</option>
                                            <option value="JHS1">JHS 1</option>
                                            <option value="JHS2">JHS 2</option>
                                            <option value="JHS3">JHS 3</option>
                                            <option value="SHS1">SHS 1</option>
                                            <option value="SHS2">SHS 2</option>
                                            <option value="SHS3">SHS 3</option>
                                        </select>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-success">Create & Link Account</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="parent-dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Simple script to handle service worker issues without affecting session -->
    <script>
        // This script helps with service worker navigation issues
        document.addEventListener('DOMContentLoaded', function() {
            // Add a timestamp parameter to links to avoid service worker caching
            const backButton = document.querySelector('a[href="parent-dashboard.php"]');
            if (backButton) {
                backButton.setAttribute('href', 'parent-dashboard.php?t=' + Date.now());
            }
            
            // Handle form submissions to prevent redirect issues
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Add a hidden timestamp field to the form
                    const timeField = document.createElement('input');
                    timeField.type = 'hidden';
                    timeField.name = 't';
                    timeField.value = Date.now();
                    form.appendChild(timeField);
                });
            });
        });
    </script>
</body>
</html>
