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

// Get user data using direct request with service role key
try {
    // Direct query using the service role key
    $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
    $users = supabaseRequest($endpoint, 'GET', null, true);
    
    if (empty($users)) {
        // User not found, log out
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $user = $users[0];
    
    // Check if user has an active subscription or has been renewed by admin
    // If so, redirect them to the dashboard instead of showing subscription page
    if (isset($_GET['incomplete']) && $_GET['incomplete'] == 1) {
        // This is the case we need to fix - check if user actually has a valid subscription
        if ($user['subscription_status'] === 'active' || 
            $user['subscription_status'] === 'renewed' || 
            (isset($user['admin_renewed']) && $user['admin_renewed'] == true) ||
            ($user['subscription_status'] === 'trial' && strtotime($user['subscription_end_date']) > time())) {
            
            // Log that we're redirecting a user with a valid subscription
            error_log("Redirecting user {$_SESSION['user_id']} with valid subscription status '{$user['subscription_status']}' to dashboard");
            
            // Update user status to active if it's not already
            if ($user['subscription_status'] !== 'active') {
                $updateData = [
                    'subscription_status' => 'active',
                    'admin_renewed' => true
                ];
                supabaseRequest("users?user_id=eq.{$_SESSION['user_id']}", 'PATCH', $updateData, true);
                error_log("Updated user {$_SESSION['user_id']} subscription status to active");
            }
            
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        }
    }
    
    // Check if the subscription has expired, is incomplete, or is pending payment
    $subscription_expired = false;
    $subscription_incomplete = false;
    $subscription_pending = false;
    
    if (isset($_GET['expired']) && $_GET['expired'] == 1) {
        $subscription_expired = true;
    }
    
    if (isset($_GET['incomplete']) && $_GET['incomplete'] == 1) {
        $subscription_incomplete = true;
    }
    
    if (isset($_GET['pending']) && $_GET['pending'] == 1 || $user['subscription_status'] === 'pending') {
        $subscription_pending = true;
    }
    
    // Only show trial message if not directed from login/dashboard for subscription completion
    $is_trial = false;
    $show_trial_message = false;
    
    if ($user['subscription_status'] === 'trial' && !$subscription_expired && !$subscription_incomplete && !isset($_GET['plan'])) {
        $is_trial = true;
        $show_trial_message = true;
        $trial_end_date = new DateTime($user['subscription_end_date']);
        $current_date = new DateTime();
        
        // Calculate hours left in trial (one-day trial)
        $interval = $current_date->diff($trial_end_date);
        $hours_left = ($interval->days * 24) + $interval->h;
        
        // Only mark as expired if they've been inactive for more than 24 hours after trial ends
        $inactive_period = new DateTime($trial_end_date->format('Y-m-d H:i:s'));
        $inactive_period->modify('+24 hours');

        if ($current_date > $inactive_period) {
            $subscription_expired = true;
            
            // Automatically update user status to expired if trial has ended and they've been inactive
            $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
            $updateData = ['subscription_status' => 'expired'];
            supabaseRequest($endpoint, 'PATCH', $updateData, true);
        }
    }
    
    // Process subscription payment
    $payment_success = false;
    $payment_error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $plan = isset($_POST['plan']) ? $_POST['plan'] : '';
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
        
        if (empty($plan) || empty($payment_method)) {
            $payment_error = 'Please select a subscription plan and payment method.';
        } else {
            // In a real implementation, this would integrate with Paystack or other payment gateway
            // For now, we'll simulate a successful payment
            
            // Calculate subscription end date based on plan
            $current_date = new DateTime();
            $end_date = clone $current_date;
            
            if ($plan === 'trial') {
                $end_date->modify('+1 day');
                $amount = 0;
                $plan_name = 'Free Trial';
            } else if ($plan === 'weekly') {
                $end_date->modify('+7 days');
                $amount = SUBSCRIPTION_WEEKLY_PRICE;
                $plan_name = 'Weekly';
            } else if ($plan === 'monthly') {
                $end_date->modify('+30 days');
                $amount = SUBSCRIPTION_MONTHLY_PRICE;
                $plan_name = 'Monthly';
            } else if ($plan === 'termly') {
                $end_date->modify('+90 days');
                $amount = SUBSCRIPTION_TERMLY_PRICE;
                $plan_name = 'Termly';
            } else {
                $payment_error = 'Invalid subscription plan.';
            }
            
            if (empty($payment_error)) {
                // Update user subscription in database
                $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
                
                // For paid plans, set status to 'pending' until payment is confirmed
                // Only trial plans are immediately active
                $updateData = [
                    'subscription_status' => ($plan === 'trial') ? 'trial' : 'pending',
                    'subscription_plan' => $plan,
                    'subscription_start_date' => $current_date->format('c'),
                    'subscription_end_date' => $end_date->format('c'),
                    'subscription_auto_renew' => ($plan !== 'trial') ? true : false
                ];
                $update_result = supabaseRequest($endpoint, 'PATCH', $updateData, true);
                
                // Record payment
                $paymentData = [
                    'user_id' => $_SESSION['user_id'],
                    'amount' => $amount,
                    'payment_method' => $payment_method,
                    'payment_date' => $current_date->format('c'),
                    'plan' => $plan,
                    'status' => 'completed'
                ];
                $payment_result = supabaseRequest('payments', 'POST', $paymentData, true);
                
                if (!isset($update_result['error']) && !isset($payment_result['error'])) {
                    $payment_success = true;
                    
                    // Create a payment confirmation record
                    $confirmation_id = uniqid('payment_');
                    $confirmationData = [
                        'confirmation_id' => $confirmation_id,
                        'user_id' => $_SESSION['user_id'],
                        'payment_date' => $current_date->format('c'),
                        'amount' => $amount,
                        'plan' => $plan,
                        'status' => 'confirmed'
                    ];
                    $confirmation_result = supabaseRequest('payment_confirmations', 'POST', $confirmationData, true);
                    
                    // Store confirmation ID in session for verification
                    $_SESSION['payment_confirmed'] = true;
                    $_SESSION['payment_confirmation_id'] = $confirmation_id;
                    
                    // Update user status only after successful payment verification
                    if (verify_payment($_SESSION['user_id'], $plan)) {
                        // Only update status to active for paid plans
                        if ($plan !== 'trial') {
                            $endpoint = 'users?user_id=eq.' . urlencode($_SESSION['user_id']);
                            $updateData = [
                                'subscription_status' => 'active',
                                'subscription_plan' => $plan,
                                'subscription_start_date' => $current_date->format('c'),
                                'subscription_end_date' => $end_date->format('c'),
                                'subscription_auto_renew' => true
                            ];
                            $final_update_result = supabaseRequest($endpoint, 'PATCH', $updateData, true);
                            
                            if (!isset($final_update_result['error'])) {
                                // Redirect to confirmation page after successful payment
                                header('Refresh: 5; URL=payment-confirmation.php?id=' . $confirmation_id);
                                exit;
                            }
                        }
                    }
                    
                    // If verification fails, keep user on subscription page
                    $payment_error = 'Payment verification failed. Please try again.';
                }
            }
        }
    }
    
} catch (Exception $e) {
    $error = 'An error occurred: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="./assets/css/style.css" rel="stylesheet">
    <!-- Embedded Logo CSS to fix 404 error -->
    <style>
    /* AcademicEase Logo Styling */
    .ae-logo-container {
        display: flex;
        align-items: center;
        font-family: 'Arial', sans-serif;
    }
    
    .ae-logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #4285F4, #34A853);
        border-radius: 8px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-right: 10px;
        position: relative;
        overflow: hidden;
    }
    
    .ae-logo-icon::before {
        content: 'A';
        color: white;
        font-size: 24px;
        font-weight: bold;
        line-height: 1;
    }
    
    .ae-logo-icon::after {
        content: 'E';
        color: rgba(255, 255, 255, 0.8);
        font-size: 16px;
        font-weight: bold;
        position: absolute;
        bottom: 2px;
        right: 3px;
        line-height: 1;
    }
    
    .ae-logo-text {
        font-size: 18px;
        font-weight: bold;
    }
    
    .ae-academic {
        color: #4285F4;
    }
    
    .ae-ease {
        color: #34A853;
    }
    
    /* Mobile Navigation Fixes */
    @media (max-width: 991.98px) {
        /* Fix dropdown menu positioning on mobile */
        .dropdown-menu {
            position: absolute !important;
            will-change: transform !important;
        }
        
        /* Ensure dropdown items are properly spaced and visible */
        .dropdown-item {
            padding: 0.5rem 1rem;
            clear: both;
            white-space: nowrap;
        }
        
        /* Fix for user dropdown on mobile */
        #mobileUserDropdown + .dropdown-menu {
            min-width: 200px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        /* Ensure navbar-collapse takes full width on mobile */
        .navbar-collapse {
            max-height: 80vh;
            overflow-y: auto;
        }
        
        /* Add some spacing to the collapsed navbar */
        .navbar-nav {
            padding-top: 0.5rem;
            padding-bottom: 0.5rem;
        }
    }
    </style>
    
    <!-- Embedded Enhanced UI CSS to fix 404 error -->
    <style>
    /* Enhanced UI for AcademicEase */
    
    /* Mobile Experience Improvements */
    @media (max-width: 767.98px) {
        .btn, .nav-link, .dropdown-item {
            padding: 12px 16px;
            margin-bottom: 8px;
        }
        
        input, select, textarea, .form-control {
            font-size: 16px !important; /* Prevents iOS zoom on focus */
            padding: 12px;
        }
        
        .card {
            margin-bottom: 20px;
        }
    }
    
    /* Visual Hierarchy */
    .dashboard-card {
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 24px;
    }
    
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: none;
        padding: 15px 20px;
    }
    
    /* Engagement Elements */
    .progress {
        height: 10px;
        border-radius: 5px;
    }
    
    .badge {
        padding: 6px 10px;
        font-weight: 500;
    }
    
    /* Personalization */
    .user-greeting {
        font-size: 1.5rem;
        margin-bottom: 20px;
        color: #4285F4;
    }
    
    /* Dark Theme Support */
    .dark-theme {
        background-color: #121212;
        color: #e0e0e0;
    }
    
    .dark-theme .card,
    .dark-theme .navbar,
    .dark-theme .footer,
    .dark-theme .modal-content {
        background-color: #1e1e1e;
        color: #e0e0e0;
    }
    
    .dark-theme .card-header {
        background-color: #2a2a2a;
    }
    
    .dark-theme .text-muted {
        color: #aaaaaa !important;
    }
    
    .dark-theme .border {
        border-color: #333333 !important;
    }
    
    .dark-theme .btn-outline-primary {
        color: #81b0ff;
        border-color: #81b0ff;
    }
    
    .dark-theme .btn-outline-primary:hover {
        background-color: #2c4a7c;
        color: #e0e0e0;
    }
    
    /* Accessibility Improvements */
    .btn:focus, .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(66, 133, 244, 0.5);
    }
    
    /* Settings Panel */
    #settings-panel {
        position: fixed;
        right: -300px;
        top: 0;
        width: 300px;
        height: 100%;
        background-color: #ffffff;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        transition: right 0.3s ease;
        z-index: 1050;
        padding: 20px;
        overflow-y: auto;
    }
    
    #settings-panel.show {
        right: 0;
    }
    
    .dark-theme #settings-panel {
        background-color: #1e1e1e;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.3);
    }
    
    #settings-toggle {
        z-index: 1051;
    }
    
    .settings-section {
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eeeeee;
    }
    
    .dark-theme .settings-section {
        border-bottom-color: #333333;
    }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <div class="navbar-brand">
                <div class="ae-logo-container">
                    <div class="ae-logo-icon"></div>
                    <div class="ae-logo-text">
                        <span class="ae-academic">Academic</span><span class="ae-ease">Ease</span>
                    </div>
                </div>
            </div>
            
            <!-- Mobile: User Account Button (Outside Collapse) -->
            <div class="d-flex d-lg-none ms-auto me-2">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="mobileUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="mobileUserDropdown">
                        <li class="dropdown-item-text">
                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><a class="dropdown-item active" href="subscribe-direct.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Hamburger Menu Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if ($user['subscription_status'] === 'active' || $user['subscription_status'] === 'trial') : ?>
                        <!-- Show navigation links for users with active subscriptions or on trial -->
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
                        <li class="nav-item">
                            <a class="nav-link" href="schedule.php">Schedule</a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Desktop: User Account Dropdown (Inside Collapse) -->
                <div class="d-none d-lg-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="dropdown-toggle text-decoration-none text-dark" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item active" href="subscribe-direct.php"><i class="bi bi-credit-card me-2"></i>Subscription</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Subscription Content -->
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h1 class="mb-4">Subscription Plans</h1>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($payment_success): ?>
                    <div class="alert alert-success" role="alert">
                        <h4 class="alert-heading">Payment Successful!</h4>
                        <p>Thank you for subscribing to Academic Ease. Your subscription has been activated successfully.</p>
                        <p>You will be redirected to the payment confirmation page in a few seconds...</p>
                    </div>
                <?php else: ?>
                    <?php if ($subscription_expired): ?>
                        <div class="alert alert-warning" role="alert">
                            <h4 class="alert-heading">Subscription Expired</h4>
                            <p>Your subscription has expired. Please renew your subscription to continue using all features of Academic Ease.</p>
                        </div>
                    <?php elseif ($subscription_pending): ?>
                        <div class="alert alert-warning" role="alert">
                            <h4 class="alert-heading">Payment Pending</h4>
                            <p>Your payment is being processed. You will gain access to all features once your payment is confirmed. This usually takes a few minutes.</p>
                            <p>If you believe this is an error, please contact support.</p>
                        </div>
                    <?php elseif ($subscription_incomplete): ?>
                        <div class="alert alert-info" role="alert">
                            <h4 class="alert-heading">Complete Your Subscription</h4>
                            <p>You need to complete your subscription to access the Academic Ease platform. Please select a plan below to continue.</p>
                        </div>
                    <?php elseif ($show_trial_message): ?>
                        <div class="alert alert-info" role="alert">
                            <h4 class="alert-heading">Free Trial Active</h4>
                            <p>You are currently on a free trial that will expire in <?php echo $hours_left; ?> hours. Subscribe now to continue using Academic Ease without interruption.</p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($payment_error): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($payment_error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white text-center">
                                    <h5 class="mb-0">Weekly</h5>
                                </div>
                                <div class="card-body text-center">
                                    <h3 class="card-title pricing-card-title">GH₵<?php echo SUBSCRIPTION_WEEKLY_PRICE; ?></h3>
                                    <p class="text-muted">per week</p>
                                    <ul class="list-unstyled mt-3 mb-4">
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Unlimited AI assistance</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Study materials access</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Progress tracking</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white text-center">
                                    <h5 class="mb-0">Monthly</h5>
                                    <span class="badge bg-warning">Popular</span>
                                </div>
                                <div class="card-body text-center">
                                    <h3 class="card-title pricing-card-title">GH₵<?php echo SUBSCRIPTION_MONTHLY_PRICE; ?></h3>
                                    <p class="text-muted">per month</p>
                                    <ul class="list-unstyled mt-3 mb-4">
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Unlimited AI assistance</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Study materials access</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Progress tracking</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Personalized study plan</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white text-center">
                                    <h5 class="mb-0">Termly</h5>
                                    <span class="badge bg-success">Best Value</span>
                                </div>
                                <div class="card-body text-center">
                                    <h3 class="card-title pricing-card-title">GH₵<?php echo SUBSCRIPTION_TERMLY_PRICE; ?></h3>
                                    <p class="text-muted">per term</p>
                                    <ul class="list-unstyled mt-3 mb-4">
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Unlimited AI assistance</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Study materials access</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Progress tracking</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Personalized study plan</li>
                                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Exam preparation guides</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Information</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="subscribe-direct.php">
                                <div class="mb-3">
                                    <label for="plan" class="form-label">Select Subscription Plan</label>
                                    <?php
                                    // Get plan from URL parameter if available
                                    $selected_plan = isset($_GET['plan']) ? $_GET['plan'] : '';
                                    ?>
                                    <select class="form-select" id="plan" name="plan" required>
                                        <option value="">Choose a plan...</option>
                                        <?php if ($user['subscription_status'] !== 'expired' && $user['subscription_status'] !== 'trial'): ?>
                                        <option value="trial" <?php echo ($selected_plan === 'trial') ? 'selected' : ''; ?>>Free Trial - 1 Day (Free)</option>
                                        <?php endif; ?>
                                        <option value="weekly" <?php echo ($selected_plan === 'weekly') ? 'selected' : ''; ?>>Weekly - GH₵<?php echo SUBSCRIPTION_WEEKLY_PRICE; ?></option>
                                        <option value="monthly" <?php echo ($selected_plan === 'monthly') ? 'selected' : ''; ?>>Monthly - GH₵<?php echo SUBSCRIPTION_MONTHLY_PRICE; ?></option>
                                        <option value="termly" <?php echo ($selected_plan === 'termly') ? 'selected' : ''; ?>>Termly - GH₵<?php echo SUBSCRIPTION_TERMLY_PRICE; ?></option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="momo" value="momo" required>
                                            <label class="form-check-label" for="momo">
                                                Mobile Money
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="card" value="card">
                                            <label class="form-check-label" for="card">
                                                Credit/Debit Card
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="bank" value="bank">
                                            <label class="form-check-label" for="bank">
                                                Bank Transfer
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Subscribe Now</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; 2023 AcademicEase. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    
                    
                    
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Embedded JavaScript to fix 404 errors -->
    <script>
    // Enhanced UI JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Add smooth scrolling to all links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add hover effects to cards
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 8px 15px rgba(0, 0, 0, 0.1)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            });
        });
    });
    
    // Settings Panel JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Create settings button if it doesn't exist
        if (!document.querySelector('#settings-toggle')) {
            const settingsBtn = document.createElement('button');
            settingsBtn.id = 'settings-toggle';
            settingsBtn.className = 'btn btn-sm btn-outline-secondary position-fixed';
            settingsBtn.style.bottom = '20px';
            settingsBtn.style.right = '20px';
            settingsBtn.style.zIndex = '1000';
            settingsBtn.innerHTML = '<i class="bi bi-gear"></i>';
            document.body.appendChild(settingsBtn);
        }
        
        // Create settings panel if it doesn't exist
        if (!document.querySelector('#settings-panel')) {
            const settingsPanel = document.createElement('div');
            settingsPanel.id = 'settings-panel';
            settingsPanel.innerHTML = `
                <h5 class="mb-3">Display Settings</h5>
                <div class="settings-section">
                    <div class="mb-3">
                        <label for="theme-selector" class="form-label">Theme</label>
                        <select class="form-select" id="theme-selector">
                            <option value="light">Light</option>
                            <option value="dark">Dark</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Font Size</label>
                        <div class="d-flex">
                            <button id="font-smaller" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-dash"></i></button>
                            <button id="font-reset" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-arrow-counterclockwise"></i></button>
                            <button id="font-larger" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(settingsPanel);
        }
        
        // Toggle settings panel
        const settingsToggle = document.getElementById('settings-toggle');
        const settingsPanel = document.getElementById('settings-panel');
        
        if (settingsToggle && settingsPanel) {
            settingsToggle.addEventListener('click', function() {
                settingsPanel.classList.toggle('show');
            });
            
            // Close panel when clicking outside
            document.addEventListener('click', function(e) {
                if (!settingsPanel.contains(e.target) && e.target !== settingsToggle) {
                    settingsPanel.classList.remove('show');
                }
            });
            
            // Theme selector
            const themeSelector = document.getElementById('theme-selector');
            themeSelector.addEventListener('change', function() {
                if (this.value === 'dark') {
                    document.body.classList.add('dark-theme');
                    localStorage.setItem('theme', 'dark');
                } else {
                    document.body.classList.remove('dark-theme');
                    localStorage.setItem('theme', 'light');
                }
            });
            
            // Font size controls
            document.getElementById('font-smaller').addEventListener('click', function() {
                changeFontSize(-1);
            });
            
            document.getElementById('font-reset').addEventListener('click', function() {
                resetFontSize();
            });
            
            document.getElementById('font-larger').addEventListener('click', function() {
                changeFontSize(1);
            });
            
            // Load saved settings
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-theme');
                themeSelector.value = 'dark';
            }
            
            const savedFontSize = localStorage.getItem('fontSize');
            if (savedFontSize) {
                document.documentElement.style.fontSize = savedFontSize + 'px';
            }
        }
        
        // Font size functions
        function changeFontSize(step) {
            const currentSize = parseInt(window.getComputedStyle(document.documentElement).fontSize);
            const newSize = currentSize + step;
            document.documentElement.style.fontSize = newSize + 'px';
            localStorage.setItem('fontSize', newSize);
        }
        
        function resetFontSize() {
            document.documentElement.style.fontSize = '16px';
            localStorage.removeItem('fontSize');
        }
    });
    </script>
</body>
</html>
