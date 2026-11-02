<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/parent_child_helper.php'; // Include parent-child helper functions

// Start session
session_start();

// Get plan from URL parameter if available
if (isset($_GET['plan'])) {
    $_SESSION['selected_plan'] = $_GET['plan'];
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // If user is logged in and has a selected plan, redirect to subscription page
    if (isset($_SESSION['selected_plan'])) {
        header('Location: subscribe-direct.php?plan=' . $_SESSION['selected_plan']);
    } else {
        // Otherwise, redirect to dashboard
        header('Location: dashboard.php');
    }
    exit;
}

// Initialize variables
$full_name = '';
$email = '';
$phone_number = '';
$school_level = '';
$school_name = '';
$error = '';
$success = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone_number = trim($_POST['phone_number']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $user_type = trim($_POST['user_type']);
    $school_level = isset($_POST['school_level']) ? trim($_POST['school_level']) : '';
    $school_name = trim($_POST['school_name']);
    
    // Validate form data
    if (empty($full_name) || empty($email) || empty($phone_number) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else if ($user_type === 'student' && empty($school_level)) {
        $error = 'Please select your school level.';
    } else {
        try {
            // Check if email already exists using direct API request with service role key
            $filter = "email=eq." . urlencode($email);
            $existing_user = supabaseRequest("users?$filter", 'GET', null, true);
            
            // Debug: Log the email check API request
            error_log('Register - Email check API request: users?' . $filter);
            
            // Debug: Log the email check result
            error_log('Email check result: ' . json_encode($existing_user));
            
            if (!empty($existing_user)) {
                $error = 'Email address is already registered.';
            } else {
                // Check if phone number already exists using direct API request with service role key
                $filter = "phone_number=eq." . urlencode($phone_number);
                $existing_phone = supabaseRequest("users?$filter", 'GET', null, true);
                
                // Debug: Log the phone check API request
                error_log('Register - Phone check API request: users?' . $filter);
                
                if (!empty($existing_phone)) {
                    $error = 'Phone number is already registered.';
                } else {
                    // Generate user ID
                    $user_id = generate_uuid();
                    
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                    
                    // Set trial period (1 day)
                    $trial_start = date('c');
                    $trial_end = date('c', strtotime('+1 day'));
                    
                    // Get child information for parent users
                    $child_name = '';
                    $child_email = '';
                    $child_school_level = '';
                    $child_id = null;
                    
                    if ($user_type === 'parent') {
                        $child_name = isset($_POST['child_name']) ? trim($_POST['child_name']) : '';
                        $child_email = isset($_POST['child_email']) ? trim($_POST['child_email']) : '';
                        $child_school_level = isset($_POST['child_school_level']) ? trim($_POST['child_school_level']) : '';
                    }
                    
                    // Create user in database
                    $new_user = [
                        'user_id' => $user_id,
                        'email' => $email,
                        'phone_number' => $phone_number,
                        'password_hash' => $password_hash,
                        'full_name' => $full_name,
                        'user_type' => $user_type,
                        'school_level' => $user_type === 'student' ? $school_level : null,
                        'school_name' => !empty($school_name) ? $school_name : null,
                        'created_at' => date('c'),
                        'last_login' => date('c'),
                        'subscription_status' => 'trial',
                        'subscription_plan' => 'trial',
                        'subscription_start_date' => $trial_start,
                        'subscription_end_date' => $trial_end,
                        'subscription_auto_renew' => false
                    ];
                    
                    // Debug: Log the user data before insertion
                    error_log('Attempting to create user: ' . json_encode($new_user));
                    
                    // Use direct API request with service role key to create user
                    $result = supabaseRequest('users', 'POST', $new_user, true);
                    
                    // Debug: Log the user creation API request
                    error_log('Register - User creation API request with service role key');
                    
                    // Debug: Log the result of the insertion
                    error_log('User creation result: ' . json_encode($result));
                    
                    if (isset($result['error']) && $result['error'] === true) {
                        error_log('User creation error: ' . $result['message']);
                        throw new Exception('Failed to create user account: ' . $result['message']);
                    }
                    
                    if (empty($result)) {
                        error_log('User creation error: No response from database');
                        throw new Exception('Failed to create user account: No response from database');
                    }
                    
                    // Handle parent-child linking if applicable
                    if ($user_type === 'parent' && !empty($child_name)) {
                        $child_user_id = null;
                        
                        // Check if child email was provided and exists in the system
                        if (!empty($child_email)) {
                            $filter = "email=eq." . urlencode($child_email);
                            $existing_child = supabaseRequest("users?$filter", 'GET', null, true);
                            
                            if (!empty($existing_child) && isset($existing_child[0]['user_id'])) {
                                $child_user_id = $existing_child[0]['user_id'];
                            }
                        }
                        
                        // If child doesn't exist and we have enough info, create a new child account
                        if (empty($child_user_id) && !empty($child_school_level)) {
                            // Generate a temporary password
                            $temp_password = substr(md5(rand()), 0, 10);
                            $temp_password_hash = password_hash($temp_password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
                            
                            // Generate child user ID
                            $child_user_id = generate_uuid();
                            
                            // Create child user with minimal information
                            $new_child = [
                                'user_id' => $child_user_id,
                                'email' => 'temp_' . $child_user_id . '@academicease.com', // Temporary email
                                'phone_number' => '0000000000', // Placeholder phone number to satisfy NOT NULL constraint
                                'password_hash' => $temp_password_hash,
                                'full_name' => $child_name,
                                'user_type' => 'student',
                                'school_level' => $child_school_level,
                                'created_at' => date('c'),
                                'subscription_status' => 'trial',
                                'subscription_plan' => 'trial',
                                'subscription_start_date' => $trial_start,
                                'subscription_end_date' => $trial_end
                            ];
                            
                            // Create child user
                            $child_result = supabaseRequest('users', 'POST', $new_child, true);
                            error_log('Child user creation result: ' . json_encode($child_result));
                        }
                        
                        // Create parent-child link if we have a child user ID
                        if (!empty($child_user_id)) {
                            // Use the helper function to link child to parent
                            $link_result = link_child_to_parent($user_id, $child_user_id);
                            error_log('Parent-child linking result: ' . ($link_result ? 'success' : 'failed'));
                            
                            if ($link_result) {
                                // Store the linked child info in session for redirect to parent dashboard
                                $_SESSION['has_linked_children'] = true;
                                $_SESSION['linked_child_id'] = $child_user_id;
                                $_SESSION['linked_child_name'] = $child_name;
                                
                                // Also store in cookies as a backup
                                setcookie('linked_child_id', $child_user_id, time() + 3600, '/');
                                setcookie('linked_child_name', $child_name, time() + 3600, '/');
                            } else {
                                error_log('Failed to link child to parent using helper function');
                            }
                        }
                    }
                    
                    // Set success message
                    $success = 'Your account has been created successfully! Please log in.';
                    
                    // If this is a parent who linked a child, store that info in a database field
                    if ($user_type === 'parent' && !empty($child_user_id)) {
                        // Update the parent's record to store the linked child ID
                        $parent_update = [
                            'school_name' => 'CHILD:' . $child_user_id . ':' . $child_name
                        ];
                        
                        $parent_update_result = supabaseRequest('users?user_id=eq.' . $user_id, 'PATCH', $parent_update, true);
                        error_log('Parent update with linked child: ' . json_encode($parent_update_result));
                        
                        // Also update the child's record to link to the parent
                        $child_update = [
                            'school_name' => 'PARENT:' . $user_id
                        ];
                        
                        $child_update_result = supabaseRequest('users?user_id=eq.' . $child_user_id, 'PATCH', $child_update, true);
                        error_log('Child update with parent link: ' . json_encode($child_update_result));
                    }
                    
                    // Redirect to login page with success message, plan info, and user type
                    $redirect_params = 'success=1';
                    
                    // Add user type to the redirect
                    $redirect_params .= '&user_type=' . $user_type;
                    
                    // Add email to the redirect for auto-filling the login form
                    $redirect_params .= '&email=' . urlencode($email);
                    
                    // Add child info if a parent linked a child
                    if ($user_type === 'parent' && !empty($child_user_id)) {
                        $redirect_params .= '&has_child=1';
                    }
                    
                    // Add plan info if available
                    if (isset($_SESSION['selected_plan'])) {
                        $selected_plan = $_SESSION['selected_plan'];
                        $redirect_params .= '&plan=' . $selected_plan;
                    }
                    
                    // Redirect to login page
                    header('Location: login.php?' . $redirect_params);
                    exit;
                    
                    // Clear form data
                    $full_name = '';
                    $email = '';
                    $phone_number = '';
                    $school_level = '';
                    $school_name = '';
                }
            }
        } catch (Exception $e) {
            error_log('Registration Error: ' . $e->getMessage());
            $error = 'An error occurred during registration. Please try again.';
        }
    }
}

/**
 * Generate a UUID v4
 * 
 * @return string UUID
 */
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="./assets/css/style.css">
    
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
    </style>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="./assets/img/favicon.png">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="features.php">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="pricing.php">Pricing</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                    <a href="register.php" class="btn btn-primary active">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Registration Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7">
                    <div class="card shadow">
                        <div class="card-body p-5">
                            <h2 class="text-center mb-4">Create Your Account</h2>
                            
                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger" role="alert">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success" role="alert">
                                    <?php echo htmlspecialchars($success); ?>
                                    <div class="mt-3">
                                        <a href="login.php" class="btn btn-primary">Login Now</a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <form method="post" action="register.php" id="registration-form">
                                    <div class="mb-4">
                                        <h5>Account Type</h5>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="user_type" id="student" value="student" checked>
                                            <label class="form-check-label" for="student">Student</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="user_type" id="parent" value="parent">
                                            <label class="form-check-label" for="parent">Parent</label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="+233 XX XXX XXXX" required>
                                    </div>
                                    
                                    <div class="mb-3 student-field">
                                        <label for="school_level" class="form-label">School Level</label>
                                        <select class="form-select" id="school_level" name="school_level">
                                            <option value="" disabled <?php echo empty($school_level) ? 'selected' : ''; ?>>Select your school level</option>
                                            <option value="JHS1" <?php echo $school_level === 'JHS1' ? 'selected' : ''; ?>>JHS 1</option>
                                            <option value="JHS2" <?php echo $school_level === 'JHS2' ? 'selected' : ''; ?>>JHS 2</option>
                                            <option value="JHS3" <?php echo $school_level === 'JHS3' ? 'selected' : ''; ?>>JHS 3</option>
                                            <option value="SHS1" <?php echo $school_level === 'SHS1' ? 'selected' : ''; ?>>SHS 1</option>
                                            <option value="SHS2" <?php echo $school_level === 'SHS2' ? 'selected' : ''; ?>>SHS 2</option>
                                            <option value="SHS3" <?php echo $school_level === 'SHS3' ? 'selected' : ''; ?>>SHS 3</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Parent-Child Linking Section (only visible for parents) -->
                                    <div class="mb-3 parent-field" style="display: none;">
                                        <h5 class="mt-4 mb-3">Link Your Child's Account</h5>
                                        <div class="alert alert-info">
                                            <small>Enter your child's information below to link their account to yours. This will allow you to monitor their progress.</small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="child_name" class="form-label">Child's Full Name</label>
                                            <input type="text" class="form-control" id="child_name" name="child_name">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="child_email" class="form-label">Child's Email Address (if registered)</label>
                                            <input type="email" class="form-control" id="child_email" name="child_email">
                                            <div class="form-text">If your child already has an account, enter their email to link it. Otherwise, leave blank.</div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="child_school_level" class="form-label">Child's School Level</label>
                                            <select class="form-select" id="child_school_level" name="child_school_level">
                                                <option value="" selected>Select your child's school level</option>
                                                <option value="JHS1">JHS 1</option>
                                                <option value="JHS2">JHS 2</option>
                                                <option value="JHS3">JHS 3</option>
                                                <option value="SHS1">SHS 1</option>
                                                <option value="SHS2">SHS 2</option>
                                                <option value="SHS3">SHS 3</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="school_name" class="form-label">School Name (Optional)</label>
                                        <input type="text" class="form-control" id="school_name" name="school_name" value="<?php echo htmlspecialchars($school_name); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="form-text">Password must be at least 8 characters long.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                        <label class="form-check-label" for="terms">I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a></label>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary">Create Account</button>
                                    </div>
                                </form>
                                
                                <hr class="my-4">
                                
                                <div class="text-center">
                                    <p class="mb-0">Already have an account? <a href="login.php">Login</a></p>
                                </div>
                                
                                <!-- JavaScript to toggle fields based on user type -->
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        const studentRadio = document.getElementById('student');
                                        const parentRadio = document.getElementById('parent');
                                        const studentFields = document.querySelectorAll('.student-field');
                                        const parentFields = document.querySelectorAll('.parent-field');
                                        
                                        function toggleFields() {
                                            if (studentRadio.checked) {
                                                studentFields.forEach(field => field.style.display = 'block');
                                                parentFields.forEach(field => field.style.display = 'none');
                                            } else if (parentRadio.checked) {
                                                studentFields.forEach(field => field.style.display = 'none');
                                                parentFields.forEach(field => field.style.display = 'block');
                                            }
                                        }
                                        
                                        // Initial toggle
                                        toggleFields();
                                        
                                        // Toggle on change
                                        studentRadio.addEventListener('change', toggleFields);
                                        parentRadio.addEventListener('change', toggleFields);
                                    });
                                </script>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer py-5 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4 mb-lg-0">
                    <h5>Academic Ease</h5>
                    <p>AI-powered homework and study assistant for JHS and SHS students in Ghana.</p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="features.php" class="text-white">Features</a></li>
                        <li><a href="pricing.php" class="text-white">Pricing</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                    <h5>Resources</h5>
                    <ul class="list-unstyled">
                        <li><a href="faq.php" class="text-white">FAQ</a></li>
                        <li><a href="support.php" class="text-white">Support</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-envelope me-2"></i> info@academicease.com</li>
                        <li><i class="bi bi-phone me-2"></i> +233 20 123 4567</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 Academic Ease. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="privacy.php" class="text-white">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="terms.php" class="text-white">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="./assets/js/main.js"></script>
    
    <script>
        // Toggle account type specific fields
        document.addEventListener('DOMContentLoaded', function() {
            const studentRadio = document.getElementById('student');
            const parentRadio = document.getElementById('parent');
            const studentFields = document.querySelectorAll('.student-field');
            const parentFields = document.querySelectorAll('.parent-field');
            
            function toggleAccountTypeFields() {
                // Toggle student fields
                studentFields.forEach(field => {
                    if (studentRadio.checked) {
                        field.style.display = 'block';
                        const inputs = field.querySelectorAll('select, input');
                        inputs.forEach(input => input.required = true);
                    } else {
                        field.style.display = 'none';
                        const inputs = field.querySelectorAll('select, input');
                        inputs.forEach(input => input.required = false);
                    }
                });
                
                // Toggle parent fields
                parentFields.forEach(field => {
                    if (parentRadio.checked) {
                        field.style.display = 'block';
                        // Don't make child fields required, they're optional
                    } else {
                        field.style.display = 'none';
                    }
                });
            }
            
            // Initial toggle
            toggleAccountTypeFields();
            
            // Toggle on change
            studentRadio.addEventListener('change', toggleAccountTypeFields);
            parentRadio.addEventListener('change', toggleAccountTypeFields);
        });
    </script>
</body>
</html>
