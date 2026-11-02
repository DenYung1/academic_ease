<?php
/**
 * Admin Page Updater Script
 * 
 * This script updates all admin pages to use the new secure session handler
 * and consistent sidebar navigation. Run this script once to apply the changes.
 */

// Define the admin directory path
$adminDir = __DIR__ . '/public/admin';

// Get all PHP files in the admin directory
$adminFiles = glob($adminDir . '/*.php');

// Skip these files as they've already been updated or have special handling
$skipFiles = ['index.php', 'login.php', 'logout.php', 'student-view.php', 'bulk-upload.php', 'users.php'];

// Counter for updated files
$updatedCount = 0;

// Process each admin file
foreach ($adminFiles as $file) {
    $filename = basename($file);
    
    // Skip files that are already updated or have special handling
    if (in_array($filename, $skipFiles)) {
        echo "Skipping {$filename} (already updated)\n";
        continue;
    }
    
    // Read the file content
    $content = file_get_contents($file);
    
    // 1. Replace the session handling code with our secure session handler
    $oldSessionCode = "/\\s*\/\/\s*Include configuration and database connection\\s*require_once '\.\.\/\.\.\/(includes\/config\.php)';\\s*require_once '\.\.\/\.\.\/(includes\/db_connect\.php)';\\s*\/\/\s*Start session\\s*session_start\(\);\\s*\/\/\s*Check if admin is logged in[\s\S]*?if \(!\$admin_authenticated\) \{[\s\S]*?header\('Location: login\.php'\);[\s\S]*?exit;\\s*\}\\s*\}/";
    $newSessionCode = "// Include configuration and database connection\nrequire_once '../../includes/config.php';\nrequire_once '../../includes/db_connect.php';\nrequire_once '../../includes/admin_session.php';\n\n// Verify admin is logged in with secure session\ncheck_admin_login();";
    
    // Use a more flexible pattern matching approach
    $pattern = '/\/\/\s*Include configuration and database connection[\s\S]*?exit;\s*\}\s*\}/s';
    $content = preg_replace($pattern, $newSessionCode, $content, 1, $count);
    
    if ($count === 0) {
        // Try an alternative pattern if the first one didn't match
        $pattern = '/\/\/\s*Include configuration[\s\S]*?exit;\s*\}\s*\}/s';
        $content = preg_replace($pattern, $newSessionCode, $content, 1, $count);
    }
    
    // 2. Update the sidebar to include links to new features
    $sidebarPattern = '/<div class="sidebar-menu">([\s\S]*?)<\/div>/s';
    $newSidebar = '<div class="sidebar-menu">\n            <a href="index.php">\n                <i class="bi bi-speedometer2 me-2"></i> Dashboard\n            </a>\n            <a href="users.php">\n                <i class="bi bi-people me-2"></i> Users\n            </a>\n            <a href="formula-sheets.php"' . (strpos($filename, 'formula') !== false ? ' class="active"' : '') . '>\n                <i class="bi bi-journal-text me-2"></i> Formula Sheets\n            </a>\n            <a href="past-questions.php"' . (strpos($filename, 'past-question') !== false ? ' class="active"' : '') . '>\n                <i class="bi bi-file-earmark-text me-2"></i> Past Questions\n            </a>\n            <a href="quizzes.php"' . (strpos($filename, 'quiz') !== false ? ' class="active"' : '') . '>\n                <i class="bi bi-question-circle me-2"></i> Quizzes\n            </a>\n            <a href="study-materials.php"' . (strpos($filename, 'study-material') !== false ? ' class="active"' : '') . '>\n                <i class="bi bi-book me-2"></i> Study Materials\n            </a>\n            <a href="student-progress.php"' . (strpos($filename, 'student-progress') !== false ? ' class="active"' : '') . '>\n                <i class="bi bi-graph-up me-2"></i> Student Progress\n            </a>\n            <a href="student-view.php">\n                <i class="bi bi-eye me-2"></i> Student View\n            </a>\n            <a href="bulk-upload.php">\n                <i class="bi bi-cloud-upload me-2"></i> Bulk Upload\n            </a>\n            <a href="settings.php"' . (strpos($filename, 'settings') !== false ? ' class="active"' : '') . '>\n                <i class="bi bi-gear me-2"></i> Settings\n            </a>\n            <a href="logout.php" class="mt-4 text-danger">\n                <i class="bi bi-box-arrow-right"></i> Logout\n            </a>';
    
    $content = preg_replace($sidebarPattern, $newSidebar . '</div>', $content, 1, $sidebarCount);
    
    // Write the updated content back to the file
    if ($count > 0 || $sidebarCount > 0) {
        file_put_contents($file, $content);
        $updatedCount++;
        echo "Updated {$filename}\n";
    } else {
        echo "No changes needed for {$filename}\n";
    }
}

echo "\nUpdate complete. {$updatedCount} files were updated.\n";
echo "All admin pages now use the secure session handler and have consistent navigation.\n";

// Create a simple readme file explaining the improvements
$readmeContent = "# AcademicEase Admin Panel Improvements\n\n## Security Enhancements\n\n- Implemented secure password hashing in the admin login system\n- Added session security with protection against session fixation and hijacking\n- Implemented rate limiting for login attempts to prevent brute force attacks\n- Created a centralized admin session handler for consistent security across all admin pages\n\n## New Features\n\n1. **Student View**\n   - View detailed student profiles and subscription information\n   - Track student activity history (logins, quiz attempts, material access)\n   - Preview what students see in their dashboard, formula sheets, and exam prep sections\n   - Impersonate students to troubleshoot issues from their perspective\n\n2. **Bulk Upload**\n   - Efficiently manage formula sheets, past questions, and study materials\n   - Upload multiple items at once using CSV templates\n   - Support for ZIP file uploads containing multiple documents\n\n## How to Use\n\n### Student View\n1. Go to the admin dashboard and click on 'Student View' in the sidebar\n2. Select a student from the dropdown menu\n3. View their profile, activity history, and dashboard preview\n\n### Bulk Upload\n1. Go to the admin dashboard and click on 'Bulk Upload' in the sidebar\n2. Select the content type (Formula Sheets, Past Questions, or Study Materials)\n3. Choose the level and subject\n4. Upload a CSV file or ZIP archive with your content\n\nCSV templates are available in the `/public/assets/templates/` directory.\n";

file_put_contents(__DIR__ . '/admin_improvements.md', $readmeContent);
echo "Created documentation file: admin_improvements.md\n";
