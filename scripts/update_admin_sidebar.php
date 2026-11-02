<?php
/**
 * Update Admin Sidebar Script
 * 
 * This script adds the "Generate Quizzes" option to the sidebar in all admin pages.
 */

// Define the admin directory path
$adminDir = __DIR__ . '/../public/admin/';

// Get all PHP files in the admin directory
$files = glob($adminDir . '*.php');

// Counter for tracking updated files
$updatedCount = 0;
$errorCount = 0;

// The sidebar menu item to add
$generateQuizzesMenuItem = '<a href="generate-quizzes.php">' . PHP_EOL . 
                          '                <i class="bi bi-lightning me-2"></i> Generate Quizzes' . PHP_EOL . 
                          '            </a>';

// Process each file
foreach ($files as $file) {
    try {
        // Skip the generate-quizzes.php file as it already has the menu item
        if (basename($file) === 'generate-quizzes.php') {
            continue;
        }
        
        // Read the file content
        $content = file_get_contents($file);
        
        // Check if the Generate Quizzes menu item already exists
        if (strpos($content, 'Generate Quizzes') !== false) {
            continue;
        }
        
        // Find the position to insert the new menu item (after Quizzes)
        $quizzesMenuPos = strpos($content, '<a href="quizzes.php"');
        if ($quizzesMenuPos === false) {
            // If Quizzes menu item not found, try to find Study Materials
            $studyMaterialsMenuPos = strpos($content, '<a href="study-materials.php"');
            if ($studyMaterialsMenuPos === false) {
                // If neither found, skip this file
                echo "Skipping " . basename($file) . " - could not find insertion point.\n";
                continue;
            }
            
            // Find the end of the Study Materials menu item
            $endPos = strpos($content, '</a>', $studyMaterialsMenuPos);
            $insertPos = $endPos + 4; // After the closing </a> tag
        } else {
            // Find the end of the Quizzes menu item
            $endPos = strpos($content, '</a>', $quizzesMenuPos);
            $insertPos = $endPos + 4; // After the closing </a> tag
        }
        
        // Insert the new menu item
        $newContent = substr($content, 0, $insertPos) . PHP_EOL . 
                      '            ' . $generateQuizzesMenuItem . 
                      substr($content, $insertPos);
        
        // Write the updated content back to the file
        file_put_contents($file, $newContent);
        
        $updatedCount++;
        echo "Updated " . basename($file) . "\n";
    } catch (Exception $e) {
        $errorCount++;
        echo "Error updating " . basename($file) . ": " . $e->getMessage() . "\n";
    }
}

// Output results
echo "Update completed.\n";
echo "Successfully updated: $updatedCount files\n";
echo "Errors: $errorCount\n";

// Function to run the script from the browser
if (isset($_GET['run']) && $_GET['run'] === 'true') {
    header('Content-Type: text/plain');
    echo "Running admin sidebar update...\n";
    echo "Updated $updatedCount files with $errorCount errors.\n";
    echo "Refresh the admin pages to see the new menu item.";
}
?>
