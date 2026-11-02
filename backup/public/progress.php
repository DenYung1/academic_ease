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

// Get user data
try {
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
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe.php?expired=1');
        exit;
    }
    
    // Get time period for analytics
    $period = isset($_GET['period']) ? $_GET['period'] : 'week';
    
    // Calculate date range based on period
    $end_date = new DateTime();
    $start_date = clone $end_date;
    
    switch ($period) {
        case 'week':
            $start_date->modify('-7 days');
            break;
        case 'month':
            $start_date->modify('-30 days');
            break;
        case 'year':
            $start_date->modify('-365 days');
            break;
        default:
            $start_date->modify('-7 days');
    }
    
    // Get conversation statistics
    $conversations = supabaseSelect('conversations', [
        'filter' => [
            'user_id.eq' => $_SESSION['user_id'],
            'start_time.gte' => $start_date->format('c')
        ]
    ]);
    
    // Calculate statistics
    $total_conversations = count($conversations);
    $total_questions = 0;
    $subject_distribution = [];
    $daily_activity = [];
    
    // Initialize daily activity array with zeros
    $current_date = clone $start_date;
    while ($current_date <= $end_date) {
        $date_key = $current_date->format('Y-m-d');
        $daily_activity[$date_key] = 0;
        $current_date->modify('+1 day');
    }
    
    foreach ($conversations as $conversation) {
        // Get messages for this conversation
        $messages = supabaseSelect('messages', [
            'filter' => [
                'conversation_id.eq' => $conversation['conversation_id'],
                'sender.eq' => 'user'
            ]
        ]);
        
        $total_questions += count($messages);
        
        // Update subject distribution
        $subject = $conversation['subject'] ?? 'General';
        if (!isset($subject_distribution[$subject])) {
            $subject_distribution[$subject] = 0;
        }
        $subject_distribution[$subject]++;
        
        // Update daily activity
        $conv_date = new DateTime($conversation['start_time']);
        $date_key = $conv_date->format('Y-m-d');
        if (isset($daily_activity[$date_key])) {
            $daily_activity[$date_key]++;
        }
    }
    
    // Sort subject distribution by count
    arsort($subject_distribution);
    
    // Get study time statistics (simulated data for now)
    $study_time_data = [
        'total_hours' => rand(5, 20),
        'average_daily' => rand(30, 120),
        'most_active_day' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][rand(0, 6)],
        'most_active_time' => ['Morning', 'Afternoon', 'Evening', 'Night'][rand(0, 3)]
    ];
    
    // Get quiz performance (simulated data for now)
    $quiz_performance = [
        'total_quizzes' => rand(3, 15),
        'average_score' => rand(60, 95),
        'highest_score' => rand(90, 100),
        'subjects' => [
            'Mathematics' => rand(60, 100),
            'Science' => rand(60, 100),
            'English' => rand(60, 100),
            'Social Studies' => rand(60, 100)
        ]
    ];
    
    // Get learning streaks (simulated data for now)
    $current_streak = rand(1, 14);
    $longest_streak = max($current_streak, rand(7, 30));
    
    // Get recent achievements (simulated data for now)
    $achievements = [
        [
            'title' => '7-Day Streak',
            'description' => 'Used Academic Ease for 7 consecutive days',
            'date' => (new DateTime())->modify('-' . rand(1, 7) . ' days')->format('Y-m-d'),
            'icon' => 'bi-calendar-check'
        ],
        [
            'title' => 'Quiz Master',
            'description' => 'Scored 90% or higher on 5 quizzes',
            'date' => (new DateTime())->modify('-' . rand(1, 14) . ' days')->format('Y-m-d'),
            'icon' => 'bi-trophy'
        ],
        [
            'title' => 'Math Whiz',
            'description' => 'Completed all Mathematics practice exercises',
            'date' => (new DateTime())->modify('-' . rand(1, 21) . ' days')->format('Y-m-d'),
            'icon' => 'bi-calculator'
        ],
        [
            'title' => 'Curious Mind',
            'description' => 'Asked 50+ questions to the AI Assistant',
            'date' => (new DateTime())->modify('-' . rand(1, 30) . ' days')->format('Y-m-d'),
            'icon' => 'bi-lightbulb'
        ]
    ];
    
    // Sort achievements by date (newest first)
    usort($achievements, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
} catch (Exception $e) {
    error_log('Progress Tracking Error: ' . $e->getMessage());
    $error = 'An error occurred while loading your progress data. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracking - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .achievement-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #e9f5ff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #0d6efd;
        }
        
        .streak-card {
            text-align: center;
            padding: 1.5rem;
            border-radius: 10px;
        }
        
        .streak-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0;
            line-height: 1;
        }
        
        .streak-label {
            font-size: 0.9rem;
            color: #6c757d;
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
                        <a class="nav-link active" href="progress.php">Progress</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($user['name']); ?>
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

    <!-- Progress Tracking Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1 class="h3 mb-0">Your Learning Progress</h1>
                            <div class="btn-group">
                                <a href="?period=week" class="btn btn-outline-primary <?php echo $period === 'week' ? 'active' : ''; ?>">Week</a>
                                <a href="?period=month" class="btn btn-outline-primary <?php echo $period === 'month' ? 'active' : ''; ?>">Month</a>
                                <a href="?period=year" class="btn btn-outline-primary <?php echo $period === 'year' ? 'active' : ''; ?>">Year</a>
                            </div>
                        </div>
                        
                        <div class="row g-4">
                            <!-- Learning Streaks -->
                            <div class="col-md-6 col-lg-3">
                                <div class="streak-card bg-primary bg-opacity-10">
                                    <div class="mb-3">
                                        <i class="bi bi-calendar-check text-primary fs-1"></i>
                                    </div>
                                    <p class="streak-number"><?php echo $current_streak; ?></p>
                                    <p class="streak-label">Current Streak (Days)</p>
                                </div>
                            </div>
                            
                            <!-- Total Questions -->
                            <div class="col-md-6 col-lg-3">
                                <div class="streak-card bg-success bg-opacity-10">
                                    <div class="mb-3">
                                        <i class="bi bi-question-circle text-success fs-1"></i>
                                    </div>
                                    <p class="streak-number"><?php echo $total_questions; ?></p>
                                    <p class="streak-label">Questions Asked</p>
                                </div>
                            </div>
                            
                            <!-- Study Time -->
                            <div class="col-md-6 col-lg-3">
                                <div class="streak-card bg-warning bg-opacity-10">
                                    <div class="mb-3">
                                        <i class="bi bi-clock-history text-warning fs-1"></i>
                                    </div>
                                    <p class="streak-number"><?php echo $study_time_data['total_hours']; ?></p>
                                    <p class="streak-label">Study Hours</p>
                                </div>
                            </div>
                            
                            <!-- Quiz Performance -->
                            <div class="col-md-6 col-lg-3">
                                <div class="streak-card bg-info bg-opacity-10">
                                    <div class="mb-3">
                                        <i class="bi bi-graph-up text-info fs-1"></i>
                                    </div>
                                    <p class="streak-number"><?php echo $quiz_performance['average_score']; ?>%</p>
                                    <p class="streak-label">Average Quiz Score</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <!-- Activity Chart -->
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daily Activity</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="activityChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Subject Distribution -->
            <div class="col-lg-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Subject Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <!-- Quiz Performance -->
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Quiz Performance</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="quizChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Study Time Analysis -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Study Time Analysis</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="bi bi-clock text-primary fs-3"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">Average Daily</h6>
                                                <p class="mb-0 fs-5 fw-bold"><?php echo $study_time_data['average_daily']; ?> min</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center">
                                            <div class="flex-shrink-0">
                                                <i class="bi bi-calendar-day text-primary fs-3"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-0">Most Active Day</h6>
                                                <p class="mb-0 fs-5 fw-bold"><?php echo $study_time_data['most_active_day']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Study Time Distribution</h6>
                                <canvas id="timeDistributionChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Achievements -->
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Achievements</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($achievements as $achievement): ?>
                            <div class="col-md-6 col-lg-3 mb-4">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <div class="d-flex justify-content-center mb-3">
                                            <div class="achievement-icon">
                                                <i class="bi <?php echo $achievement['icon']; ?>"></i>
                                            </div>
                                        </div>
                                        <h5 class="card-title"><?php echo htmlspecialchars($achievement['title']); ?></h5>
                                        <p class="card-text text-muted small"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                        <p class="card-text text-muted">
                                            <small>Earned on <?php echo date('j M Y', strtotime($achievement['date'])); ?></small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Charts JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Daily Activity Chart
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(activityCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_keys($daily_activity)); ?>,
                    datasets: [{
                        label: 'Study Sessions',
                        data: <?php echo json_encode(array_values($daily_activity)); ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Subject Distribution Chart
            const subjectCtx = document.getElementById('subjectChart').getContext('2d');
            const subjectChart = new Chart(subjectCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_keys($subject_distribution)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($subject_distribution)); ?>,
                        backgroundColor: [
                            'rgba(13, 110, 253, 0.7)',
                            'rgba(25, 135, 84, 0.7)',
                            'rgba(220, 53, 69, 0.7)',
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(13, 202, 240, 0.7)',
                            'rgba(111, 66, 193, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
            
            // Quiz Performance Chart
            const quizCtx = document.getElementById('quizChart').getContext('2d');
            const quizChart = new Chart(quizCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(<?php echo json_encode($quiz_performance['subjects']); ?>),
                    datasets: [{
                        label: 'Average Score (%)',
                        data: Object.values(<?php echo json_encode($quiz_performance['subjects']); ?>),
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
            
            // Time Distribution Chart
            const timeDistCtx = document.getElementById('timeDistributionChart').getContext('2d');
            const timeDistChart = new Chart(timeDistCtx, {
                type: 'pie',
                data: {
                    labels: ['Morning', 'Afternoon', 'Evening', 'Night'],
                    datasets: [{
                        data: [25, 30, 35, 10],
                        backgroundColor: [
                            'rgba(255, 193, 7, 0.7)',
                            'rgba(13, 202, 240, 0.7)',
                            'rgba(13, 110, 253, 0.7)',
                            'rgba(111, 66, 193, 0.7)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        });
    </script>
</body>
</html>
