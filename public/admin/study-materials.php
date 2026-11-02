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
$materials = [];

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete all placeholder materials
    if (isset($_POST['action']) && $_POST['action'] === 'delete_all_placeholders') {
        // Use direct cURL to delete all placeholders
        $url = SUPABASE_URL . '/rest/v1/study_materials?title=eq.Untitled';
        $key = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : SUPABASE_KEY;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Delete placeholders response: Status={$status}, Error={$error}, Response={$response}");
        
        // Check if deletion was successful
        if ($status >= 200 && $status < 300) {
            $success_message = "All placeholder materials deleted successfully.";
        } else {
            // Try a different approach - delete each one individually
            $all_materials = supabaseRequest("study_materials?title=eq.Untitled", 'GET', null, true) ?: [];
            $deleted_count = 0;
            
            foreach ($all_materials as $material) {
                if (isset($material['id'])) {
                    $material_id = $material['id'];
                    
                    // Use direct cURL for each deletion
                    $url = SUPABASE_URL . '/rest/v1/study_materials?id=eq.' . urlencode($material_id);
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $key,
                        'Authorization: Bearer ' . $key,
                        'Content-Type: application/json',
                        'Prefer: return=minimal'
                    ]);
                    
                    curl_exec($ch);
                    curl_close($ch);
                    
                    $deleted_count++;
                }
            }
            
            if ($deleted_count > 0) {
                $success_message = "{$deleted_count} placeholder materials deleted successfully.";
            } else {
                $info_message = "No placeholder materials found to delete.";
            }
        }
        
        // Refresh the materials list - fetch only study materials from past_questions table
        $materials = supabaseRequest("past_questions?exam_type=eq.study_material&order=created_at.desc", 'GET', null, true) ?: [];
    }
    
    // Add new study material
    if (isset($_POST['action']) && $_POST['action'] === 'add_material') {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $subject = trim($_POST['subject']);
        $level = trim($_POST['level']);
        $content_type = isset($_POST['content_type']) ? trim($_POST['content_type']) : 'text';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        
        // Initialize variables
        $file_path = '';
        $file_name = '';
        $error_message = '';
        
        // Handle file upload for file type materials
        if ($content_type === 'file') {
            // Create upload directory if it doesn't exist
            $upload_dir = '../../uploads/materials/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $error_message = 'Failed to create upload directory. Please contact the administrator.';
                    error_log('Failed to create directory: ' . $upload_dir);
                }
            }
            
            // Process file upload if no errors so far
            if (empty($error_message)) {
                if (isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
                    // Get file details
                    $file_name = $_FILES['material_file']['name'];
                    $file_tmp = $_FILES['material_file']['tmp_name'];
                    $file_size = $_FILES['material_file']['size'];
                    
                    // Validate file type
                    $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png'];
                    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $error_message = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
                    } else {
                        // Generate a unique file name to prevent overwriting
                        $unique_id = uniqid();
                        $new_file_name = $unique_id . '.' . $file_extension;
                        $file_path = 'uploads/materials/' . $new_file_name;
                        
                        // Move the uploaded file to the destination
                        if (move_uploaded_file($file_tmp, '../../' . $file_path)) {
                            // File uploaded successfully
                        } else {
                            $error_message = 'Failed to upload file. Please try again.';
                            error_log('Failed to move uploaded file to: ' . $file_path);
                        }
                    }
                } else if ($_FILES['material_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                    // Handle file upload errors (only if a file was attempted to be uploaded)
                    switch ($_FILES['material_file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = 'The uploaded file exceeds the maximum file size limit.';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = 'The file was only partially uploaded. Please try again.';
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                        case UPLOAD_ERR_CANT_WRITE:
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = 'Server error occurred during file upload. Please try again later.';
                            break;
                        default:
                            $error_message = 'An unknown error occurred during file upload. Please try again.';
                    }
                    // Log the error for debugging
                    error_log('File Upload Error: ' . $_FILES['material_file']['error'] . ' - ' . $error_message);
                }
            }
        }
        
        if (empty($title) || empty($subject) || empty($level)) {
            $error_message = 'Please fill in all required fields.';
        } else if ($content_type === 'text' && empty($content)) {
            $error_message = 'Please enter content for the study material.';
        } else if ($content_type === 'link' && empty($content)) {
            $error_message = 'Please enter a valid link.';
        } else if ($content_type === 'file' && empty($file_path) && $_FILES['material_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $error_message = 'Please upload a file.';
        } else if (empty($error_message)) {
            // Insert study material into past_questions table with a special exam_type
            // to identify it as a study material
            $materialData = [
                'title' => $title,
                'description' => $description,
                'subject' => $subject,
                'level' => $level,
                'exam_type' => 'study_material', // Use exam_type to identify study materials
                'year' => date('Y'),            // Required field in past_questions
                'file_path' => $file_path,
                'file_name' => $file_name,
                'created_by' => isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0,
                'created_at' => date('c')
            ];
            
            // Store content in description if it's text or link
            if ($content_type === 'text' || $content_type === 'link') {
                $materialData['description'] = $content;
            }
            
            // Debug: Log the material data being sent
            error_log('Adding study material: ' . json_encode($materialData));
            
            // Use direct supabaseRequest with service role key to bypass RLS
            $result = supabaseRequest('past_questions', 'POST', $materialData, true);
            
            // Debug: Log the result
            error_log('Add study material result: ' . json_encode($result));
            
            if ($result) {
                $success_message = 'Study material added successfully.';
            } else {
                $error_message = 'Failed to add study material. Please try again.';
            }
            
            // Refresh the materials list - fetch only study materials from past_questions table
            $materials = supabaseRequest("past_questions?exam_type=eq.study_material&order=created_at.desc", 'GET', null, true) ?: [];
        }
    }
    
    // Delete study material (using past_questions table)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_material') {
        $material_id = $_POST['material_id'];
        
        // Log the material ID being deleted
        error_log("Attempting to delete material with ID: {$material_id}");
        
        // Get the material details to check if it exists and has a file
        $material = supabaseRequest("past_questions?question_id=eq.{$material_id}", 'GET', null, true);
        
        // Delete any associated file
        if (!empty($material) && isset($material[0]['file_path']) && !empty($material[0]['file_path'])) {
            $file_path = '../../' . $material[0]['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Use the direct Supabase URL for deletion
        $url = SUPABASE_URL . '/rest/v1/past_questions?question_id=eq.' . urlencode($material_id);
        $key = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : SUPABASE_KEY;
        
        // Set up cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=minimal'
        ]);
        
        // Execute the request
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Consider 204 (No Content) as success for DELETE operations
        if ($status == 204) {
            $success_message = 'Study material deleted successfully.';
        } else {
            $error_message = 'Failed to delete study material. Please try again.';
        }
        
        // Refresh the materials list - fetch only study materials from past_questions table
        $materials = supabaseRequest("past_questions?exam_type=eq.study_material&order=created_at.desc", 'GET', null, true) ?: [];
    }
}

// Debug: Check what tables exist in Supabase
$debug_info = [];

// Clean up placeholder materials
$cleanup_placeholders = true; // Set to true to automatically clean up placeholders
if ($cleanup_placeholders) {
    // Get all materials with 'Untitled' title
    $placeholders = supabaseRequest("study_materials?title=eq.Untitled", 'GET', null, true) ?: [];
    $deleted_count = 0;
    
    // Delete each placeholder one by one
    foreach ($placeholders as $placeholder) {
        if (isset($placeholder['id'])) {
            $material_id = $placeholder['id'];
            // Use direct DELETE request
            $url = SUPABASE_URL . '/rest/v1/study_materials?id=eq.' . urlencode($material_id);
            $key = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : SUPABASE_KEY;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $key,
                'Authorization: Bearer ' . $key,
                'Content-Type: application/json',
                'Prefer: return=minimal'
            ]);
            
            curl_exec($ch);
            curl_close($ch);
            $deleted_count++;
        }
    }
    
    if ($deleted_count > 0) {
        $success_message = "{$deleted_count} placeholder materials automatically cleaned up.";
    }
}

// Get all study materials
try {
    // Use past_questions table since study_materials doesn't exist
    // We'll filter to only show items with category='study_material'
    $materials = supabaseRequest("past_questions?order=created_at.desc", 'GET', null, true) ?: [];
    
    // Add 'all' level option to the existing levels array
    $levels['all'] = 'All Levels';
} catch (Exception $e) {
    error_log('Admin Study Materials Error: ' . $e->getMessage());
    $error_message = 'An error occurred while loading study materials.';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials Management - Academic Ease Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
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
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.1);
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
            <a href="study-materials.php" class="active">
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
                <h2>Study Materials Management</h2>
                <div>
                    <form action="study-materials.php" method="post" class="d-inline me-2">
                        <input type="hidden" name="action" value="delete_all_placeholders">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete all placeholder materials? This cannot be undone.')">
                            <i class="bi bi-trash me-2"></i> Delete All Placeholders
                        </button>
                    </form>
                    <a href="bulk-upload.php?type=study" class="btn btn-success me-2">
                        <i class="bi bi-cloud-upload me-2"></i> Bulk Upload
                    </a>
                    <button type="button" id="addMaterialBtn" class="btn btn-primary" onclick="showAddMaterialModal()">
                        <i class="bi bi-plus-circle me-2"></i> Add New Material
                    </button>
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
            
            <!-- Materials Table -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <table id="materialsTable" class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Subject</th>
                                <th>Level</th>
                                <th>Type</th>
                                <th>Year</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $material): 
                                // Only show items that are either marked as study_material in exam_type or don't have an exam_type
                                $exam_type = isset($material['exam_type']) ? $material['exam_type'] : '';
                                if ($exam_type !== 'study_material' && !empty($exam_type)) continue;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($material['title'] ?? 'Untitled'); ?></td>
                                    <td><?php 
                                        $subject = isset($material['subject']) && is_string($material['subject']) ? $material['subject'] : '';
                                        echo htmlspecialchars(isset($subjects[$subject]) ? $subjects[$subject] : ucfirst($subject)); 
                                    ?></td>
                                    <td><?php 
                                        $level = isset($material['level']) && is_string($material['level']) ? $material['level'] : '';
                                        echo htmlspecialchars(isset($levels[$level]) ? $levels[$level] : ucfirst($level)); 
                                    ?></td>
                                    <td>
                                        <?php if (!empty($material['file_path'])): ?>
                                            <span class="badge bg-primary">File</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Text</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo isset($material['year']) ? htmlspecialchars($material['year']) : ''; ?></td>
                                    <td><?php echo isset($material['created_at']) ? date('M d, Y', strtotime($material['created_at'])) : ''; ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <?php if (!empty($material['file_path'])): ?>
                                                <a href="<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewMaterialModal" data-material-id="<?php echo $material['question_id']; ?>" data-material-title="<?php echo htmlspecialchars($material['title']); ?>" data-material-description="<?php echo htmlspecialchars($material['description'] ?? ''); ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMaterialModal" data-material-id="<?php echo $material['question_id']; ?>" data-material-title="<?php echo htmlspecialchars($material['title']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Material Modal -->
    <div class="modal fade" id="addMaterialModal" tabindex="-1" aria-labelledby="addMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addMaterialModalLabel">Add New Study Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="study-materials.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_material">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
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
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control" id="category" name="category">
                            <div class="form-text">E.g., Algebra, Grammar, Biology, etc.</div>
                        </div>
                        <div class="mb-3">
                            <label for="content_type" class="form-label">Content Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="content_type" name="content_type" required>
                                <option value="">Select Content Type</option>
                                <option value="file">File Upload</option>
                                <option value="text">Text Content</option>
                                <option value="link">External Link</option>
                            </select>
                        </div>
                        
                        <!-- File Upload (shown when content_type is 'file') -->
                        <div id="fileUploadSection" class="mb-3 d-none">
                            <label for="material_file" class="form-label">Upload File</label>
                            <input type="file" class="form-control" id="material_file" name="material_file">
                            <div class="form-text">Supported formats: PDF, DOCX, PPTX, etc. (Max size: 10MB)</div>
                        </div>
                        
                        <!-- Text Content (shown when content_type is 'text') -->
                        <div id="textContentSection" class="mb-3 d-none">
                            <label for="content_text" class="form-label">Text Content</label>
                            <textarea class="form-control" id="content_text" name="content_text" rows="6"></textarea>
                        </div>
                        
                        <!-- External Link (shown when content_type is 'link') -->
                        <div id="linkContentSection" class="mb-3 d-none">
                            <label for="content_link" class="form-label">External Link</label>
                            <input type="url" class="form-control" id="content_link" name="content_link" placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Material Modal -->
    <div class="modal fade" id="deleteMaterialModal" tabindex="-1" aria-labelledby="deleteMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteMaterialModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the study material "<span id="deleteMaterialTitle"></span>"?</p>
                    <p class="text-danger mb-0"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form id="deleteMaterialForm" action="study-materials.php" method="post">
                        <input type="hidden" name="action" value="delete_material">
                        <input type="hidden" name="material_id" id="deleteMaterialId">
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
        // Function to show the add material modal
        function showAddMaterialModal() {
            try {
                // Get the modal element
                var modalElement = document.getElementById('addMaterialModal');
                if (!modalElement) {
                    console.error('Modal element not found');
                    return;
                }
                
                // Create a new Bootstrap modal instance
                var modal = new bootstrap.Modal(modalElement);
                
                // Show the modal
                modal.show();
            } catch (error) {
                console.error('Error showing modal:', error);
                alert('Could not open the modal. Please refresh the page and try again.');
            }
        }
        
        $(document).ready(function() {
            // Add direct event listener to the Add New Material button
            $('#addMaterialBtn').on('click', function() {
                showAddMaterialModal();
            });
            
            // Handle modal hidden event to ensure backdrop is removed
            $('#addMaterialModal').on('hidden.bs.modal', function () {
                // Remove any lingering backdrop
                $('.modal-backdrop').remove();
                // Reset body classes
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            });
            
            // Initialize DataTable
            $('#materialsTable').DataTable({
                order: [[5, 'desc']], // Sort by created date by default
                pageLength: 10,
                language: {
                    search: "Search materials:",
                    lengthMenu: "Show _MENU_ materials per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ materials"
                }
            });
            
            // Show/hide content type sections based on selection
            $('#content_type').on('change', function() {
                const contentType = $(this).val();
                
                // Hide all content sections
                $('#fileUploadSection, #textContentSection, #linkContentSection').addClass('d-none');
                
                // Show the selected content section
                if (contentType === 'file') {
                    $('#fileUploadSection').removeClass('d-none');
                } else if (contentType === 'text') {
                    $('#textContentSection').removeClass('d-none');
                } else if (contentType === 'link') {
                    $('#linkContentSection').removeClass('d-none');
                }
            });
            
            // Set delete material modal data
            $('#deleteMaterialModal').on('show.bs.modal', function (event) {
                const button = $(event.relatedTarget);
                const materialId = button.data('material-id');
                const materialTitle = button.data('material-title');
                
                // Set the material ID and title in the modal
                $('#deleteMaterialId').val(materialId);
                $('#deleteMaterialTitle').text(materialTitle);
                
                console.log('Delete modal opened for material ID:', materialId, 'Title:', materialTitle);
            });
            
            // Handle delete form submission
            $('#deleteMaterialForm').on('submit', function(e) {
                // Show loading state
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                submitBtn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
                submitBtn.prop('disabled', true);
                
                // Form will submit normally, this just shows the loading state
                console.log('Deleting material ID:', $('#deleteMaterialId').val());
            });
        });
    </script>
</body>
</html>
