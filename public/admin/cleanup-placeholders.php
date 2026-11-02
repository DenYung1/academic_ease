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
$deleted_count = 0;
$remaining_count = 0;

// Add basic styling
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2, h3 { color: #333; }
    .material-item { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; }
    .material-item pre { background: #f5f5f5; padding: 5px; overflow: auto; }
    .success { color: green; }
    .error { color: red; }
    .btn { display: inline-block; padding: 8px 16px; background: #4285f4; color: white; text-decoration: none; border-radius: 4px; }
    .btn-danger { background: #dc3545; }
</style>";

// Get all study materials
$all_materials = supabaseRequest("study_materials", 'GET', null, true) ?: [];

// Display information about what we found
echo "<h2>Study Materials Cleanup</h2>";
echo "<p>Found " . count($all_materials) . " total study materials in the database.</p>";

// Show a form to force delete all materials if needed
echo "<form method='post' onsubmit='return confirm(\"Are you sure you want to delete ALL study materials? This cannot be undone!\")'>";
echo "<input type='hidden' name='action' value='force_delete_all'>";
echo "<button type='submit' class='btn btn-danger'>Force Delete ALL Study Materials</button>";
echo "</form><br>";

// Handle force delete all action
if (isset($_POST['action']) && $_POST['action'] === 'force_delete_all') {
    // Get all materials first
    $all_materials = supabaseRequest("study_materials", 'GET', null, true) ?: [];
    $success_count = 0;
    $error_count = 0;
    
    echo "<h3>Deleting All Materials</h3>";
    
    // Delete each material individually
    foreach ($all_materials as $material) {
        if (isset($material['id'])) {
            $material_id = $material['id'];
            $title = isset($material['title']) ? $material['title'] : 'Untitled';
            
            // Use direct DELETE request with service role key
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
            
            echo "<p>Deleting material ID: {$material_id}, Title: '{$title}' - ";
            if ($status >= 200 && $status < 300) {
                echo "<span class='success'>Success</span>";
                $success_count++;
            } else {
                echo "<span class='error'>Failed (Status: {$status}, Error: {$error})</span>";
                $error_count++;
            }
            echo "</p>";
        }
    }
    
    echo "<h3>Deletion Summary</h3>";
    echo "<p>Successfully deleted: {$success_count} materials</p>";
    if ($error_count > 0) {
        echo "<p class='error'>Failed to delete: {$error_count} materials</p>";
    }
    
    echo "<p><a href='study-materials.php' class='btn'>Return to Study Materials</a></p>";
    exit;
}

// Display all materials with details and individual delete buttons
echo "<h3>All Study Materials</h3>";

// Handle individual delete
if (isset($_POST['action']) && $_POST['action'] === 'delete_material') {
    $material_id = $_POST['material_id'];
    
    // Use direct DELETE request with service role key
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
    
    echo "<p class='" . ($status >= 200 && $status < 300 ? 'success' : 'error') . "'>";
    echo "Material ID {$material_id} deletion result: Status={$status}, Error={$error}</p>";
    
    // Refresh the materials list
    $all_materials = supabaseRequest("study_materials", 'GET', null, true) ?: [];
}

// Process each material
foreach ($all_materials as $material) {
    if (isset($material['id'])) {
        $material_id = $material['id'];
        $title = isset($material['title']) ? $material['title'] : 'Untitled';
        $content = isset($material['content']) ? $material['content'] : '';
        $subject = isset($material['subject']) ? $material['subject'] : 'Unknown';
        $level = isset($material['level']) ? $material['level'] : 'Unknown';
        
        echo "<div class='material-item'>";
        echo "<h4>ID: {$material_id}</h4>";
        echo "<p><strong>Title:</strong> {$title}</p>";
        echo "<p><strong>Subject:</strong> {$subject}</p>";
        echo "<p><strong>Level:</strong> {$level}</p>";
        echo "<p><strong>Content:</strong></p>";
        echo "<pre>" . htmlspecialchars(substr($content, 0, 200)) . (strlen($content) > 200 ? '...' : '') . "</pre>";
        
        // Add delete button for this material
        echo "<form method='post' onsubmit='return confirm(\"Are you sure you want to delete this material? This cannot be undone!\")'>";
        echo "<input type='hidden' name='action' value='delete_material'>";
        echo "<input type='hidden' name='material_id' value='{$material_id}'>";
        echo "<button type='submit' class='btn btn-danger'>Delete This Material</button>";
        echo "</form>";
        echo "</div>";
    }
}

// Display return link
echo "<p><a href='study-materials.php' class='btn'>Return to Study Materials</a></p>";
?>
