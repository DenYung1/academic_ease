<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if payment confirmation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: subscribe.php');
    exit;
}

$confirmation_id = $_GET['id'];
$confirmation_valid = false;
$user_name = '';
$plan_name = '';
$amount = 0;
$payment_date = '';

try {
    // Get user data
    $users = supabaseSelect('users', [
        'filter' => [
            'user_id.eq' => $_SESSION['user_id']
        ]
    ]);
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $user = $users[0];
    $user_name = $user['name'];
    
    // Verify payment confirmation
    $confirmations = supabaseSelect('payment_confirmations', [
        'filter' => [
            'confirmation_id.eq' => $confirmation_id,
            'user_id.eq' => $_SESSION['user_id']
        ]
    ]);
    
    if (!empty($confirmations)) {
        $confirmation = $confirmations[0];
        $confirmation_valid = true;
        
        // Get plan details
        if ($confirmation['plan'] === 'weekly') {
            $plan_name = 'Weekly';
            $amount = SUBSCRIPTION_WEEKLY_PRICE;
        } else if ($confirmation['plan'] === 'monthly') {
            $plan_name = 'Monthly';
            $amount = SUBSCRIPTION_MONTHLY_PRICE;
        } else if ($confirmation['plan'] === 'termly') {
            $plan_name = 'Termly';
            $amount = SUBSCRIPTION_TERMLY_PRICE;
        }
        
        $payment_date = date('F j, Y, g:i a', strtotime($confirmation['payment_date']));
        
        // Mark confirmation as verified if not already
        if ($confirmation['status'] !== 'verified') {
            supabaseUpdate('payment_confirmations', [
                'status' => 'verified'
            ], 'confirmation_id=eq.' . $confirmation_id);
        }
    } else {
        // Invalid confirmation ID
        header('Location: subscribe.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Payment Confirmation Error: ' . $e->getMessage());
    header('Location: subscribe.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .confirmation-card {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .confirmation-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 20px;
        }
        
        .confirmation-body {
            padding: 30px;
        }
        
        .confirmation-icon {
            font-size: 4rem;
            color: #198754;
            margin-bottom: 20px;
        }
        
        .confirmation-details {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="../assets/img/logo.png" alt="Academic Ease Logo" height="40">
                Academic Ease
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="progress.php">Progress</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user_name); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="subscribe.php"><i class="bi bi-star me-2"></i>Subscription</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Payment Confirmation Content -->
    <div class="container py-5">
        <div class="card confirmation-card shadow">
            <div class="confirmation-header text-center">
                <h2 class="mb-0">Payment Confirmation</h2>
            </div>
            <div class="confirmation-body text-center">
                <div class="confirmation-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <h3 class="mb-3">Payment Successful!</h3>
                <p class="lead mb-4">Thank you for subscribing to Academic Ease. Your payment has been confirmed and your subscription is now active.</p>
                
                <div class="confirmation-details">
                    <h5 class="mb-3">Payment Details</h5>
                    
                    <div class="detail-row">
                        <span class="fw-bold">Confirmation ID:</span>
                        <span><?php echo htmlspecialchars($confirmation_id); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="fw-bold">Plan:</span>
                        <span><?php echo htmlspecialchars($plan_name); ?> Subscription</span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="fw-bold">Amount:</span>
                        <span>GH₵<?php echo htmlspecialchars($amount); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="fw-bold">Date:</span>
                        <span><?php echo htmlspecialchars($payment_date); ?></span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="bi bi-house-door me-2"></i> Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
