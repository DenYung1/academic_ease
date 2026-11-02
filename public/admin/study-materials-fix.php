<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_session.php';

// Verify admin is logged in with secure session
check_admin_login();

// Initialize variables
$success_message = '';
$error_message = '';

// Handle delete request
if (isset($_GET['delete'])) {
    $material_id = trim($_GET['delete']);
    
    if (empty($material_id)) {
        $error_message = "No material ID provided for deletion.";
    } else {
        // Log the deletion attempt
        error_log("Attempting to delete study material ID: {$material_id}");
        
        // First try using direct API call with service role key
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
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        error_log("Direct DELETE response: Status={$status}, Error={$error}, Response={$response}");
        
        // Check if deletion was successful
        $check = supabaseRequest("study_materials?id=eq.{$material_id}", 'GET', null, true);
        
        if (empty($check)) {
            $success_message = "Material ID {$material_id} deleted successfully.";
        } else {
            // Try using supabaseDelete as a fallback
            $result = supabaseDelete('study_materials', "id=eq.{$material_id}");
            error_log("supabaseDelete result: " . json_encode($result));
            
            // Check again
            $check_again = supabaseRequest("study_materials?id=eq.{$material_id}", 'GET', null, true);
            if (empty($check_again)) {
                $success_message = "Material ID {$material_id} deleted successfully using supabaseDelete.";
            } else {
                $error_message = "Failed to delete material ID {$material_id}. Status: {$status}, Error: {$error}";
            }
        }
    }
}

// Get all study materials
$study_materials = supabaseRequest("study_materials?order=created_at.desc", 'GET', null, true) ?: [];

// Get the structure of the study_materials table
$table_info = supabaseRequest("study_materials?limit=1", 'GET', null, true);
$table_structure = [];
if (!empty($table_info) && isset($table_info[0])) {
    $table_structure = array_keys($table_info[0]);
}

// Get database information
$debug_info = [];
try {
    // Try to get database information
    $tables_endpoint = '_tables';
    $tables = supabaseRequest($tables_endpoint, 'GET', null, true);
    if ($tables) {
        $debug_info['tables'] = $tables;
    }
} catch (Exception $e) {
    $debug_info['error'] = $e->getMessage();
}

// Add some basic styling
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials Fix Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .json-display {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .material-card {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .material-card .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .field-name {
            font-weight: bold;
            color: #333;
        }
        .field-value {
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Study Materials Fix Tool</h1>';

// Display success or error messages
if (!empty($success_message)) {
    echo '<div class="alert alert-success">' . htmlspecialchars($success_message) . '</div>';
}
if (!empty($error_message)) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($error_message) . '</div>';
}

// Display database information
echo '<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Database Information</h5>
    </div>
    <div class="card-body">
        <p><strong>Total Study Materials:</strong> ' . count($study_materials) . '</p>
        <p><strong>Table Structure:</strong> ' . implode(', ', $table_structure) . '</p>
        
        <div class="mt-3">
            <h6>Debug Information:</h6>
            <div class="json-display">' . json_encode($debug_info, JSON_PRETTY_PRINT) . '</div>
        </div>
    </div>
</div>';

// Display all study materials
echo '<h2 class="mb-3">All Study Materials</h2>';

if (empty($study_materials)) {
    echo '<div class="alert alert-info">No study materials found in the database.</div>';
} else {
    echo '<div class="mb-3">
        <a href="study-materials.php" class="btn btn-primary">Return to Study Materials</a>
            <a href="generate-quizzes.php">
                <i class="bi bi-lightning me-2"></i> Generate Quizzes
            </a>
        <a href="study-materials-fix.php" class="btn btn-secondary ms-2">Refresh</a>
    </div>';
    
    foreach ($study_materials as $material) {
        // Make sure material is an array and has an id
        if (!is_array($material) || !isset($material['id'])) {
            echo '<div class="card material-card bg-warning">
                <div class="card-header">
                    <span>Invalid Material Format</span>
                </div>
                <div class="card-body">
                    <pre>' . print_r($material, true) . '</pre>
                </div>
            </div>';
            continue;
        }
        
        $material_id = $material['id'];
        
        echo '<div class="card material-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>ID: ' . htmlspecialchars($material_id) . '</span>
                <div>
                    <a href="study-materials-fix.php?delete=' . htmlspecialchars($material_id) . '" class="btn btn-danger btn-sm" onclick="return confirm(\'Are you sure you want to delete this material?\')">
                        <i class="bi bi-trash"></i> Delete
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">';
        
        // Display all fields in the material
        foreach ($material as $field => $value) {
            echo '<div class="col-md-6 mb-2">
                <span class="field-name">' . htmlspecialchars($field) . ':</span> 
                <span class="field-value">';
                
            // Handle different value types
            if (is_array($value)) {
                echo htmlspecialchars(json_encode($value));
            } elseif (is_null($value)) {
                echo '<em>null</em>';
            } elseif (is_bool($value)) {
                echo $value ? 'true' : 'false';
            } else {
                echo htmlspecialchars((string)$value);
            }
            
            echo '</span>
            </div>';
        }
        
        echo '</div>
            </div>
        </div>';
    }
}

echo '</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
?>
