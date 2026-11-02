<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_session.php';

// Verify admin is logged in with secure session
check_admin_login();

// Add basic styling
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Sample Study Materials</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .json-display { background-color: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="container py-4">
        <h1 class="mb-4">Create Sample Study Materials</h1>';

// Create sample study materials
if (isset($_POST['create_materials'])) {
    $key = defined('SUPABASE_SERVICE_KEY') ? SUPABASE_SERVICE_KEY : SUPABASE_KEY;
    $success_count = 0;
    $error_count = 0;
    
    // Sample study materials to create
    $sample_materials = [
        [
            'title' => 'Mathematics Fundamentals',
            'description' => 'Basic concepts in mathematics for JHS students',
            'content' => '<p>This study material covers fundamental mathematical concepts including:</p><ul><li>Basic Algebra</li><li>Geometry</li><li>Number Systems</li><li>Fractions and Decimals</li></ul>',
            'subject' => 'mathematics',
            'level' => 'jhs',
            'category' => 'Core',
            'content_type' => 'text'
        ],
        [
            'title' => 'English Grammar Guide',
            'description' => 'Comprehensive guide to English grammar for high school students',
            'content' => '<p>This guide covers essential grammar topics including:</p><ul><li>Parts of Speech</li><li>Sentence Structure</li><li>Punctuation</li><li>Common Grammar Mistakes</li></ul>',
            'subject' => 'english',
            'level' => 'shs',
            'category' => 'Language',
            'content_type' => 'text'
        ],
        [
            'title' => 'Science Experiments',
            'description' => 'Collection of practical science experiments for JHS students',
            'content' => '<p>This collection includes simple experiments that demonstrate:</p><ul><li>Basic Physics Principles</li><li>Chemistry Reactions</li><li>Biological Processes</li><li>Environmental Science</li></ul>',
            'subject' => 'science',
            'level' => 'jhs',
            'category' => 'Practical',
            'content_type' => 'text'
        ]
    ];
    
    echo '<h2>Creating Sample Study Materials</h2>';
    
    // Create each sample material
    foreach ($sample_materials as $material) {
        // Use the Supabase API to create the material
        $url = SUPABASE_URL . '/rest/v1/study_materials';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($material));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $key,
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo '<div class="card mb-3">';
        echo '<div class="card-header">';
        echo '<strong>Creating: ' . htmlspecialchars($material['title']) . '</strong>';
        echo '</div>';
        echo '<div class="card-body">';
        
        if ($status >= 200 && $status < 300) {
            echo '<p class="text-success">Successfully created!</p>';
            $success_count++;
        } else {
            echo '<p class="text-danger">Error: Status ' . $status . '</p>';
            if ($error) {
                echo '<p>' . htmlspecialchars($error) . '</p>';
            }
            echo '<div class="json-display">' . htmlspecialchars($response) . '</div>';
            $error_count++;
        }
        
        echo '</div></div>';
    }
    
    echo '<div class="alert ' . ($error_count > 0 ? 'alert-warning' : 'alert-success') . '">';
    echo '<h4>Summary</h4>';
    echo '<p>Successfully created: ' . $success_count . ' materials</p>';
    if ($error_count > 0) {
        echo '<p>Failed to create: ' . $error_count . ' materials</p>';
    }
    echo '</div>';
    
    // Check if materials exist now
    $check_url = SUPABASE_URL . '/rest/v1/study_materials?limit=10';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $check_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key
    ]);
    
    $check_response = curl_exec($ch);
    $check_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo '<h2>Verification</h2>';
    if ($check_status >= 200 && $check_status < 300) {
        $materials = json_decode($check_response, true);
        if (is_array($materials) && count($materials) > 0) {
            echo '<div class="alert alert-success">Successfully verified ' . count($materials) . ' study materials in the database!</div>';
        } else {
            echo '<div class="alert alert-warning">Table exists but no materials found.</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Verification failed. Status: ' . $check_status . '</div>';
        echo '<div class="json-display">' . htmlspecialchars($check_response) . '</div>';
    }
} else {
    // Show the form to create sample materials
    echo '<div class="alert alert-info">';
    echo '<p>This tool will create sample study materials directly in your Supabase database.</p>';
    echo '<p>Instead of trying to create the table structure (which requires SQL permissions), this approach directly creates study materials using the REST API.</p>';
    echo '</div>';
    
    echo '<form method="post" class="mb-4">';
    echo '<input type="hidden" name="create_materials" value="1">';
    echo '<button type="submit" class="btn btn-primary">Create Sample Study Materials</button>';
    echo '</form>';
}

echo '<div class="mt-4">';
echo '<a href="study-materials.php" class="btn btn-secondary">Return to Study Materials</a>
            <a href="generate-quizzes.php">
                <i class="bi bi-lightning me-2"></i> Generate Quizzes
            </a>';
echo '</div>';

echo '</div>
</body>
</html>';
?>
