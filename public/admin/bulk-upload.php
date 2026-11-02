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
$content_type = isset($_GET['type']) ? $_GET['type'] : 'formula';
$success_message = '';
$error_message = '';

// Define allowed content types
$allowed_types = [
    'formula' => 'Formula Sheets',
    'exam' => 'Past Questions',
    'study' => 'Study Materials'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // Process bulk upload
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_upload') {
        $upload_type = $_POST['upload_type'] ?? '';
        $level = $_POST['level'];
        $subject = $_POST['subject'];

        if (!array_key_exists($upload_type, $allowed_types)) {
            $error_message = 'Invalid upload type.';
        }
        
        // Debug information
        $debug_info = "POST data received: " . json_encode($_POST) . "<br>";
        $debug_info .= "FILES data: " . (isset($_FILES['bulk_file']) ? json_encode($_FILES['bulk_file']) : "No file data") . "<br>";
        
        if (!isset($_FILES['bulk_file'])) {
            $error_message = "No file data received. Please check your form configuration.";
        } else if ($_FILES['bulk_file']['error'] !== UPLOAD_ERR_OK) {
            // Translate error code to message
            $upload_errors = array(
                UPLOAD_ERR_INI_SIZE => "The uploaded file exceeds the upload_max_filesize directive in php.ini",
                UPLOAD_ERR_FORM_SIZE => "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form",
                UPLOAD_ERR_PARTIAL => "The uploaded file was only partially uploaded",
                UPLOAD_ERR_NO_FILE => "No file was uploaded",
                UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder",
                UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
                UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
            );
            $error_code = $_FILES['bulk_file']['error'];
            $error_message = isset($upload_errors[$error_code]) ? $upload_errors[$error_code] : "Unknown upload error";
        }
        
        // Check if file was uploaded successfully
        if (empty($error_message) && isset($_FILES['bulk_file']) && $_FILES['bulk_file']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['bulk_file']['size'] > MAX_UPLOAD_SIZE) {
                $error_message = 'The uploaded file is too large.';
            }

            // Process the uploaded file (CSV or ZIP)
            $file_extension = strtolower(pathinfo($_FILES['bulk_file']['name'], PATHINFO_EXTENSION));
            
            if ($file_extension === 'csv') {
                // Process CSV file
                $csv_file = $_FILES['bulk_file']['tmp_name'];
                $csv_data = array_map('str_getcsv', file($csv_file));
                
                // Remove header row
                $header = array_shift($csv_data);
                
                $success_count = 0;
                $error_count = 0;
                
                // Process each row
                foreach ($csv_data as $row) {
                    // Map CSV columns to database fields
                    if (count($row) < 3) {
                        $error_count++;
                        continue; // Skip invalid rows
                    }
                    
                    $title = trim($row[0]);
                    $description = trim($row[1]);
                    $topic = trim($row[2]);
                    $content = isset($row[3]) ? trim($row[3]) : '';
                    
                    // Skip empty rows
                    if (empty($title)) {
                        continue;
                    }
                    
                    // Prepare data for insertion
                    $data = [
                        'title' => $title,
                        'description' => $description,
                        'subject' => $subject,
                        'level' => $level,
                        'topic' => $topic,
                        'created_by' => $admin_id,
                        'created_at' => date('c')
                    ];
                    
                    // Insert into appropriate table based on upload type
                    $table = '';
                    switch ($upload_type) {
                        case 'formula':
                            $table = 'formula_sheets';
                            $data['content_type'] = 'text';
                            $data['content'] = $content;
                            break;
                        case 'exam':
                            $table = 'past_questions';
                            $data['year'] = isset($row[3]) ? trim($row[3]) : date('Y');
                            $data['exam_type'] = isset($row[4]) ? trim($row[4]) : 'WASSCE';
                            break;
                        case 'study':
                            $table = 'study_materials';
                            $data['content_type'] = 'text';
                            $data['content'] = $content;
                            break;
                    }
                    
                    if (!empty($table)) {
                        $result = supabaseInsert($table, $data);
                        
                        if (!empty($result) && !isset($result['error'])) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    }
                }
                
                if ($success_count > 0) {
                    $success_message = "Successfully imported $success_count items.";
                    if ($error_count > 0) {
                        $success_message .= " $error_count items failed to import.";
                    }
                } else {
                    $error_message = "Failed to import any items. Please check your CSV file format.";
                }
            } else if ($file_extension === 'zip') {
                // Process ZIP file containing multiple PDFs or other documents
                $zip_file = $_FILES['bulk_file']['tmp_name'];
                $extract_dir = '../../uploads/temp/' . uniqid();
                
                // Create extraction directory
                if (!file_exists($extract_dir)) {
                    mkdir($extract_dir, 0777, true);
                }
                
                // Extract ZIP file
                $zip = new ZipArchive;
                if ($zip->open($zip_file) === TRUE) {
                    $valid_archive = true;
                    for ($entry_index = 0; $entry_index < $zip->numFiles; $entry_index++) {
                        $entry_name = $zip->getNameIndex($entry_index);
                        if ($entry_name === false || strpos($entry_name, '..') !== false ||
                            preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $entry_name)) {
                            $valid_archive = false;
                            break;
                        }
                    }

                    if (!$valid_archive) {
                        $zip->close();
                        $error_message = 'The ZIP archive contains an unsafe path.';
                    } else {
                    $zip->extractTo($extract_dir);
                    $zip->close();
                    
                    // Process extracted files
                    $files = glob($extract_dir . '/*.*');
                    $success_count = 0;
                    $error_count = 0;
                    
                    foreach ($files as $file) {
                        $file_name = basename($file);
                        $file_title = pathinfo($file_name, PATHINFO_FILENAME);
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        // Skip non-document files
                        if (!in_array($file_ext, ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'txt'])) {
                            continue;
                        }
                        
                        // Determine upload directory based on content type
                        $upload_dir = '';
                        $table = '';
                        
                        switch ($upload_type) {
                            case 'formula':
                                $upload_dir = '../../uploads/formula_sheets/';
                                $table = 'formula_sheets';
                                break;
                            case 'exam':
                                $upload_dir = '../../uploads/past_questions/';
                                $table = 'past_questions';
                                break;
                            case 'study':
                                $upload_dir = '../../uploads/study_materials/';
                                $table = 'study_materials';
                                break;
                        }
                        
                        // Create upload directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        // Generate unique filename
                        $unique_file_name = uniqid() . '_' . $file_name;
                        $destination = $upload_dir . $unique_file_name;
                        
                        // Move file to final destination
                        if (copy($file, $destination)) {
                            // Prepare data for insertion
                            $data = [
                                'title' => $file_title,
                                'description' => 'Uploaded via bulk import',
                                'subject' => $subject,
                                'level' => $level,
                                'topic' => '',
                                'content_type' => 'file',
                                'file_path' => str_replace('../../', '', $destination),
                                'file_name' => $file_name,
                                'created_by' => $admin_id,
                                'created_at' => date('c')
                            ];
                            
                            // Add specific fields based on content type
                            if ($upload_type === 'exam') {
                                // Try to extract year from filename (e.g., "Math_2022.pdf" -> 2022)
                                preg_match('/\d{4}/', $file_name, $matches);
                                $data['year'] = !empty($matches) ? $matches[0] : date('Y');
                                $data['exam_type'] = 'WASSCE'; // Default
                            }
                            
                            $result = supabaseInsert($table, $data);
                            
                            if (!empty($result) && !isset($result['error'])) {
                                $success_count++;
                            } else {
                                $error_count++;
                            }
                        } else {
                            $error_count++;
                        }
                        }
                    }
                    
                    // Clean up temporary directory
                    array_map('unlink', glob("$extract_dir/*.*"));
                    rmdir($extract_dir);
                    
                    if ($success_count > 0) {
                        $success_message = "Successfully imported $success_count files.";
                        if ($error_count > 0) {
                            $success_message .= " $error_count files failed to import.";
                        }
                    } else {
                        $error_message = "Failed to import any files. Please check your ZIP file contents.";
                    }
                } else {
                    $error_message = "Failed to open ZIP file. Please check that it's a valid ZIP archive.";
                }
            } else {
                $error_message = "Unsupported file format. Please upload a CSV or ZIP file.";
            }
        } else {
            $error_message = "Please select a file to upload.";
        }
    }
}

// Get subjects and levels
$subjects = [
    'mathematics' => 'Mathematics',
    'science' => 'Science',
    'english' => 'English',
    'social_studies' => 'Social Studies',
    'ict' => 'ICT',
    'chemistry' => 'Chemistry',
    'physics' => 'Physics',
    'biology' => 'Biology'
];

$levels = [
    'jhs' => 'Junior High School',
    'shs' => 'Senior High School'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload - Academic Ease Admin</title>
    
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .upload-box {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s;
        }
        
        .upload-box:hover {
            border-color: #0d6efd;
            background-color: #f1f8ff;
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
            <a href="bulk-upload.php" class="active">
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
                <h2>Bulk Upload</h2>
                <div class="d-flex align-items-center">
                    <span class="me-3"><?php echo htmlspecialchars($admin_name); ?></span>
                    <a href="logout.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($debug_info)): ?>
                <div class="alert alert-info">
                    <h5>Debug Information:</h5>
                    <pre><?php echo $debug_info; ?></pre>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Content Type Selection -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Select Content Type</h5>
                    <div class="btn-group w-100" role="group" aria-label="Content Type Selection">
                        <?php foreach ($allowed_types as $type => $label): ?>
                            <a href="?type=<?php echo $type; ?>" class="btn <?php echo $content_type === $type ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Upload Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title">Bulk Upload <?php echo htmlspecialchars($allowed_types[$content_type]); ?></h5>
                    <p class="text-muted">Upload multiple items at once using CSV or ZIP files.</p>
                    
                    <form action="bulk-upload.php?type=<?php echo $content_type; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="bulk_upload">
                        <input type="hidden" name="upload_type" value="<?php echo $content_type; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <?php foreach ($levels as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $key => $value): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="bulk_file" class="form-label">Upload File <span class="text-danger">*</span></label>
                            <div class="mb-3">
                                <!-- Add MAX_FILE_SIZE directive to limit file size (2MB) -->
                                <input type="hidden" name="MAX_FILE_SIZE" value="2097152" />
                                <input type="file" class="form-control" id="bulk_file" name="bulk_file" accept=".csv,.zip" required>
                                <div class="form-text">Accepted formats: CSV, ZIP. Maximum file size: 2MB</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-text">
                                <strong>CSV Format:</strong> For <?php echo htmlspecialchars($allowed_types[$content_type]); ?>, include columns for Title, Description, Topic, and Content (for formula sheets and study materials).
                            </div>
                            <div class="form-text">
                                <strong>ZIP Format:</strong> Include multiple PDF or document files. Filenames will be used as titles.
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-upload me-2"></i>Upload Content
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Templates Section -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Download Templates</h5>
                    <p class="text-muted">Use these templates for your bulk uploads.</p>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-journal-text" style="font-size: 2rem; color: #0d6efd;"></i>
                                    <h5 class="mt-3">Formula Sheets Template</h5>
                                    <p class="text-muted">CSV template for formula sheets</p>
                                    <a href="../assets/templates/formula_sheets_template.csv" download class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-file-earmark-text" style="font-size: 2rem; color: #0d6efd;"></i>
                                    <h5 class="mt-3">Past Questions Template</h5>
                                    <p class="text-muted">CSV template for past questions</p>
                                    <a href="../assets/templates/past_questions_template.csv" download class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="bi bi-book" style="font-size: 2rem; color: #0d6efd;"></i>
                                    <h5 class="mt-3">Study Materials Template</h5>
                                    <p class="text-muted">CSV template for study materials</p>
                                    <a href="../assets/templates/study_materials_template.csv" download class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Simple file input validation
            $('form').on('submit', function(e) {
                const fileInput = $('#bulk_file');
                if (fileInput.length && fileInput[0].files.length === 0) {
                    e.preventDefault();
                    alert('Please select a file to upload.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
