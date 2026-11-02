<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Define the project root
$projectRoot = dirname(__DIR__);

// Files to backup and remove
$filesToCleanup = [
    // Redundant application files
    'public/study.php',
    'public/ai-assistant.php',
    'public/progress.php',
    'public/subscribe.php',
    
    // Redundant service worker files
    'public/service-worker-disabled.js',
    'public/service-worker-fixed.js',
    'public/empty-service-worker.js',
    
    // Redundant debug/fix files
    'public/clear-cache.php',
    'public/create-redirect.php',
    'public/dashboard-redirect.php',
    'public/debug-profile.php',
    'public/debug-session.php',
    'public/diagnose.php',
    'public/disable-all-caching.php',
    'public/disable-service-worker.php',
    'public/fix-cache.php',
    'public/fix-homepage.php',
    'public/fix-service-worker.html',
    'public/fix-service-worker.php',
    'public/fix-subscription.php',
    'public/reset-cache.php',
    'public/session-debug.php',
    'public/session-fix.php',
    'public/test-service-worker.html',
    'public/unregister-sw.html',
    
    // Redundant include files
    'includes/db_connect_fixed.php',
    'includes/db_connect_service.php',
];

// Ensure backup directories exist
$backupDirs = [
    $projectRoot . '/backup/public',
    $projectRoot . '/backup/includes',
];

foreach ($backupDirs as $dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("Failed to create backup directory: $dir");
        }
    }
}

// Backup and remove files
$results = [
    'success' => [],
    'not_found' => [],
    'backup_failed' => [],
    'remove_failed' => [],
];

foreach ($filesToCleanup as $file) {
    $fullPath = $projectRoot . '/' . $file;
    
    // Skip if file doesn't exist
    if (!file_exists($fullPath)) {
        $results['not_found'][] = $file;
        continue;
    }
    
    // Determine backup path
    $backupPath = $projectRoot . '/backup/' . $file;
    $backupDir = dirname($backupPath);
    
    // Ensure backup directory exists
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            $results['backup_failed'][] = $file;
            continue;
        }
    }
    
    // Backup file
    if (!copy($fullPath, $backupPath)) {
        $results['backup_failed'][] = $file;
        continue;
    }
    
    // Remove file
    if (!unlink($fullPath)) {
        $results['remove_failed'][] = $file;
        continue;
    }
    
    $results['success'][] = $file;
}

// Rename fixed files to their standard names
$filesToRename = [
    'public/study-fixed.php' => 'public/study.php',
    'public/ai-assistant-fixed.php' => 'public/ai-assistant.php',
    'public/progress-fixed.php' => 'public/progress.php',
];

$renameResults = [
    'success' => [],
    'not_found' => [],
    'failed' => [],
];

foreach ($filesToRename as $source => $destination) {
    $sourcePath = $projectRoot . '/' . $source;
    $destPath = $projectRoot . '/' . $destination;
    
    // Skip if source doesn't exist
    if (!file_exists($sourcePath)) {
        $renameResults['not_found'][] = $source;
        continue;
    }
    
    // Backup destination if it exists (should have been done already)
    if (file_exists($destPath)) {
        $backupPath = $projectRoot . '/backup/' . $destination;
        if (!copy($destPath, $backupPath)) {
            $renameResults['failed'][] = $source . ' -> ' . $destination . ' (backup failed)';
            continue;
        }
        unlink($destPath);
    }
    
    // Rename file
    if (!copy($sourcePath, $destPath)) {
        $renameResults['failed'][] = $source . ' -> ' . $destination;
        continue;
    }
    
    // Remove source file
    unlink($sourcePath);
    
    $renameResults['success'][] = $source . ' -> ' . $destination;
}

// Update references in files
$filesToUpdate = [
    'public/dashboard.php' => [
        'study-fixed.php' => 'study.php',
        'ai-assistant-fixed.php' => 'ai-assistant.php',
        'progress-fixed.php' => 'progress.php',
    ],
];

$updateResults = [
    'success' => [],
    'not_found' => [],
    'failed' => [],
];

foreach ($filesToUpdate as $file => $replacements) {
    $filePath = $projectRoot . '/' . $file;
    
    // Skip if file doesn't exist
    if (!file_exists($filePath)) {
        $updateResults['not_found'][] = $file;
        continue;
    }
    
    // Read file content
    $content = file_get_contents($filePath);
    if ($content === false) {
        $updateResults['failed'][] = $file . ' (read failed)';
        continue;
    }
    
    // Make replacements
    $newContent = $content;
    foreach ($replacements as $search => $replace) {
        $newContent = str_replace($search, $replace, $newContent);
    }
    
    // Write updated content
    if (file_put_contents($filePath, $newContent) === false) {
        $updateResults['failed'][] = $file . ' (write failed)';
        continue;
    }
    
    $updateResults['success'][] = $file;
}

// Generate report
$report = "Academic Ease Cleanup Report\n";
$report .= "========================\n\n";

$report .= "Files Backed Up and Removed:\n";
$report .= "-------------------------\n";
$report .= "Success (" . count($results['success']) . "):\n";
foreach ($results['success'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nNot Found (" . count($results['not_found']) . "):\n";
foreach ($results['not_found'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nBackup Failed (" . count($results['backup_failed']) . "):\n";
foreach ($results['backup_failed'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nRemove Failed (" . count($results['remove_failed']) . "):\n";
foreach ($results['remove_failed'] as $file) {
    $report .= "  - $file\n";
}

$report .= "\nFiles Renamed:\n";
$report .= "-------------\n";
$report .= "Success (" . count($renameResults['success']) . "):\n";
foreach ($renameResults['success'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nNot Found (" . count($renameResults['not_found']) . "):\n";
foreach ($renameResults['not_found'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nFailed (" . count($renameResults['failed']) . "):\n";
foreach ($renameResults['failed'] as $file) {
    $report .= "  - $file\n";
}

$report .= "\nReferences Updated:\n";
$report .= "------------------\n";
$report .= "Success (" . count($updateResults['success']) . "):\n";
foreach ($updateResults['success'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nNot Found (" . count($updateResults['not_found']) . "):\n";
foreach ($updateResults['not_found'] as $file) {
    $report .= "  - $file\n";
}
$report .= "\nFailed (" . count($updateResults['failed']) . "):\n";
foreach ($updateResults['failed'] as $file) {
    $report .= "  - $file\n";
}

// Save report
file_put_contents($projectRoot . '/cleanup-report.txt', $report);

// Output HTML report
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Ease - Cleanup Complete</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Academic Ease - Cleanup Complete</h4>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                            <h2 class="mt-3">Project Cleanup Completed</h2>
                            <p class="lead">Your Academic Ease project has been cleaned up and organized.</p>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> All redundant files have been backed up to the <code>/backup</code> directory before removal.
                        </div>
                        
                        <h5 class="mt-4">Summary:</h5>
                        <ul class="list-group mb-4">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Files backed up and removed
                                <span class="badge bg-primary rounded-pill"><?php echo count($results['success']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Files renamed
                                <span class="badge bg-primary rounded-pill"><?php echo count($renameResults['success']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Files with updated references
                                <span class="badge bg-primary rounded-pill"><?php echo count($updateResults['success']); ?></span>
                            </li>
                        </ul>
                        
                        <div class="mt-4 text-center">
                            <a href="index.php" class="btn btn-primary">
                                <i class="bi bi-house me-2"></i> Go to Homepage
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-primary ms-2">
                                <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
