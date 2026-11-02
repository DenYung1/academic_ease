<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Log session information
error_log('Check Session - Session ID: ' . session_id());
error_log('Check Session - SESSION: ' . json_encode($_SESSION));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Check - Academic Ease</title>
    
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
                        <h4 class="mb-0">Academic Ease - Session Debug Tool</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-4">Session Information</h5>
                        
                        <div class="alert alert-info">
                            <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
                            <p><strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?></p>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Session Variables</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Key</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($_SESSION)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center">No session variables found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($_SESSION as $key => $value): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($key); ?></td>
                                                <td>
                                                    <?php 
                                                    if (is_array($value)) {
                                                        echo '<pre>' . htmlspecialchars(json_encode($value, JSON_PRETTY_PRINT)) . '</pre>';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Cookie Information</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($_COOKIE)): ?>
                                        <tr>
                                            <td colspan="2" class="text-center">No cookies found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($_COOKIE as $name => $value): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($name); ?></td>
                                                <td><?php echo htmlspecialchars($value); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                            <a href="login.php" class="btn btn-outline-primary ms-2">Go to Login</a>
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
