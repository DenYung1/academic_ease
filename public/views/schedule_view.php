<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_plan ? htmlspecialchars($current_plan['plan_name']) : 'Study Schedule'; ?> - AcademicEase</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
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
        
        /* Schedule Styling */
        .schedule-card {
            border-left: 5px solid #0d6efd;
            transition: transform 0.2s;
        }
        
        .schedule-card:hover {
            transform: translateY(-2px);
        }
        
        .schedule-card.mathematics { border-left-color: #0d6efd; }
        .schedule-card.english { border-left-color: #dc3545; }
        .schedule-card.science, .schedule-card.integrated_science { border-left-color: #198754; }
        .schedule-card.social_studies { border-left-color: #fd7e14; }
        .schedule-card.break { border-left-color: #6c757d; }
        
        .day-column {
            min-height: 150px;
        }
        
        .time-badge {
            font-size: 0.8rem;
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Header -->
        <div class="row align-items-center mb-4">
            <div class="col-md-8">
                <?php if ($current_plan): ?>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="study-planner.php">Study Plans</a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($current_plan['plan_name']); ?></li>
                        </ol>
                    </nav>
                    <h1 class="mb-0"><?php echo htmlspecialchars($current_plan['plan_name']); ?></h1>
                    <p class="text-muted">
                        <?php 
                        echo date('M j, Y', strtotime($current_plan['start_date'])) . ' - ' . 
                             date('M j, Y', strtotime($current_plan['end_date']));
                        ?>
                    </p>
                <?php else: ?>
                    <h1 class="mb-0">Study Schedule</h1>
                    <p class="text-muted">Plan your study time effectively</p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editScheduleModal">
                        <i class="bi bi-pencil-square me-2"></i> Edit Schedule
                    </button>
                    <?php if ($current_plan): ?>
                        <a href="study-planner.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Plans
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Weekly Schedule -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h4 class="card-title mb-0">Weekly Schedule</h4>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <?php foreach ($days as $day_key => $day_name): ?>
                                    <th class="text-center"><?php echo $day_name; ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($days as $day_key => $day_name): ?>
                                <td class="day-column">
                                    <?php
                                    $day_items = array_filter($schedule_items, function($item) use ($day_key) {
                                        return isset($item['day_of_week']) && $item['day_of_week'] === $day_key;
                                    });
                                    
                                    if (empty($day_items)): 
                                    ?>
                                        <div class="text-center text-muted p-3">
                                            <i class="bi bi-calendar-x"></i>
                                            <p class="mb-0">No study sessions</p>
                                        </div>
                                    <?php else: ?>
                                        <?php 
                                        // Sort by start time
                                        usort($day_items, function($a, $b) {
                                            return strtotime($a['start_time']) - strtotime($b['start_time']);
                                        });
                                        
                                        foreach ($day_items as $item): 
                                        ?>
                                            <div class="card mb-2 schedule-card <?php echo $item['subject']; ?>">
                                                <div class="card-body p-2">
                                                    <h6 class="mb-1">
                                                        <?php
                                                        $subject_name = '';
                                                        foreach ($all_subjects as $category => $subjects) {
                                                            if (isset($subjects[$item['subject']])) {
                                                                $subject_name = $subjects[$item['subject']];
                                                                break;
                                                            }
                                                        }
                                                        echo htmlspecialchars($subject_name ?: $item['subject']);
                                                        ?>
                                                    </h6>
                                                    <div class="time-badge">
                                                        <?php 
                                                        echo date('g:i A', strtotime($item['start_time'])) . ' - ' . 
                                                             date('g:i A', strtotime($item['end_time'])); 
                                                        ?>
                                                    </div>
                                                    <?php if (!empty($item['notes'])): ?>
                                                        <small class="text-muted d-block mt-1">
                                                            <?php echo htmlspecialchars($item['notes']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Study Tips -->
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h4 class="mb-0">Study Tips</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-alarm me-2"></i>Time Management
                                </h5>
                                <ul class="mb-0">
                                    <li>Study in 25-minute intervals with 5-minute breaks (Pomodoro Technique)</li>
                                    <li>Schedule difficult subjects when you're most alert</li>
                                    <li>Include short breaks to avoid burnout</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-lightning-charge me-2"></i>Effective Learning
                                </h5>
                                <ul class="mb-0">
                                    <li>Use active recall instead of passive reading</li>
                                    <li>Teach concepts to others to solidify understanding</li>
                                    <li>Connect new information to things you already know</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-stars me-2"></i>Stay Motivated
                                </h5>
                                <ul class="mb-0">
                                    <li>Set specific, achievable goals for each session</li>
                                    <li>Reward yourself after completing study blocks</li>
                                    <li>Track your progress to see improvements</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Schedule Modal -->
    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Study Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleForm" method="post" action="">
                        <?php if ($plan_id): ?>
                            <input type="hidden" name="plan_id" value="<?php echo htmlspecialchars($plan_id); ?>">
                        <?php endif; ?>
                        
                        <div id="scheduleItemsContainer">
                            <?php if (!empty($schedule_items)): ?>
                                <?php foreach ($schedule_items as $index => $item): ?>
                                    <div class="schedule-item-form border-bottom pb-3 mb-3">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Subject</label>
                                                <select class="form-select" name="schedule_items[<?php echo $index; ?>][subject]" required>
                                                    <option value="">Select Subject</option>
                                                    
                                                    <!-- Core Subjects -->
                                                    <optgroup label="Core Subjects">
                                                        <?php foreach ($all_subjects['core'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- JHS Subjects -->
                                                    <optgroup label="JHS Subjects">
                                                        <?php foreach ($all_subjects['jhs_specific'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Science Subjects -->
                                                    <optgroup label="Science Subjects">
                                                        <?php foreach ($all_subjects['science'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Business Subjects -->
                                                    <optgroup label="Business Subjects">
                                                        <?php foreach ($all_subjects['business'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Humanities Subjects -->
                                                    <optgroup label="Humanities Subjects">
                                                        <?php foreach ($all_subjects['humanities'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Technical & Vocational Subjects -->
                                                    <optgroup label="Technical & Vocational Subjects">
                                                        <?php foreach ($all_subjects['technical'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Visual Arts Subjects -->
                                                    <optgroup label="Visual Arts Subjects">
                                                        <?php foreach ($all_subjects['visual_arts'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Home Economics Subjects -->
                                                    <optgroup label="Home Economics Subjects">
                                                        <?php foreach ($all_subjects['home_economics'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Agricultural Science Subjects -->
                                                    <optgroup label="Agricultural Science Subjects">
                                                        <?php foreach ($all_subjects['agriculture'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                    
                                                    <!-- Study Activities -->
                                                    <optgroup label="Study Activities">
                                                        <?php foreach ($all_subjects['activities'] as $subject_key => $subject_name): ?>
                                                            <option value="<?php echo $subject_key; ?>" <?php echo ($item['subject'] === $subject_key) ? 'selected' : ''; ?>><?php echo htmlspecialchars($subject_name); ?></option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Day</label>
                                                <select class="form-select" name="schedule_items[<?php echo $index; ?>][day]" required>
                                                    <option value="">Select Day</option>
                                                    <?php foreach ($days as $day_key => $day_name): ?>
                                                        <option value="<?php echo $day_key; ?>"
                                                            <?php echo ($item['day_of_week'] === $day_key) ? 'selected' : ''; ?>>
                                                            <?php echo $day_name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Start Time</label>
                                                <input type="time" class="form-control" 
                                                    name="schedule_items[<?php echo $index; ?>][start_time]"
                                                    value="<?php echo $item['start_time']; ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">End Time</label>
                                                <input type="time" class="form-control" 
                                                    name="schedule_items[<?php echo $index; ?>][end_time]"
                                                    value="<?php echo $item['end_time']; ?>" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Notes (Optional)</label>
                                                <textarea class="form-control" 
                                                    name="schedule_items[<?php echo $index; ?>][notes]" 
                                                    rows="2"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-outline-danger btn-sm mt-2 remove-item">
                                            <i class="bi bi-trash me-1"></i> Remove
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mb-3">
                            <button type="button" id="addScheduleItem" class="btn btn-outline-primary">
                                <i class="bi bi-plus-circle me-1"></i> Add Study Session
                            </button>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Tips:</strong>
                            <ul class="mb-0">
                                <li>Create a balanced schedule with time for all subjects</li>
                                <li>Include breaks between study sessions</li>
                                <li>Allocate more time to challenging subjects</li>
                                <li>Be realistic about how much you can study each day</li>
                            </ul>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="save_schedule" class="btn btn-primary">Save Schedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-dismiss success message after 3 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const successAlert = document.querySelector('.alert-success');
                if (successAlert) {
                    const bsAlert = new bootstrap.Alert(successAlert);
                    bsAlert.close();
                }
            }, 3000);
        });
    </script>
    
    <!-- Schedule Management Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('scheduleItemsContainer');
        const addButton = document.getElementById('addScheduleItem');
        const userLevel = '<?php echo $level; ?>';
        
        // Template for new schedule item
        function getTemplate(index) {
            return `
                <div class="schedule-item-form border-bottom pb-3 mb-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Subject</label>
                            <select class="form-select" name="schedule_items[${index}][subject]" required>
                                <option value="">Select Subject</option>
                                
                                <!-- Core Subjects -->
                                <optgroup label="Core Subjects">
                                    <?php foreach ($all_subjects['core'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- JHS Subjects -->
                                <optgroup label="JHS Subjects">
                                    <?php foreach ($all_subjects['jhs_specific'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Science Subjects -->
                                <optgroup label="Science Subjects">
                                    <?php foreach ($all_subjects['science'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Business Subjects -->
                                <optgroup label="Business Subjects">
                                    <?php foreach ($all_subjects['business'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Humanities Subjects -->
                                <optgroup label="Humanities Subjects">
                                    <?php foreach ($all_subjects['humanities'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Technical & Vocational Subjects -->
                                <optgroup label="Technical & Vocational Subjects">
                                    <?php foreach ($all_subjects['technical'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Visual Arts Subjects -->
                                <optgroup label="Visual Arts Subjects">
                                    <?php foreach ($all_subjects['visual_arts'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Home Economics Subjects -->
                                <optgroup label="Home Economics Subjects">
                                    <?php foreach ($all_subjects['home_economics'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Agricultural Science Subjects -->
                                <optgroup label="Agricultural Science Subjects">
                                    <?php foreach ($all_subjects['agriculture'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                
                                <!-- Study Activities -->
                                <optgroup label="Study Activities">
                                    <?php foreach ($all_subjects['activities'] as $subject_key => $subject_name): ?>
                                        <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Day</label>
                            <select class="form-select" name="schedule_items[${index}][day]" required>
                                <option value="">Select Day</option>
                                <?php foreach ($days as $day_key => $day_name): ?>
                                    <option value="<?php echo $day_key; ?>"><?php echo $day_name; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" name="schedule_items[${index}][start_time]" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" name="schedule_items[${index}][end_time]" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="schedule_items[${index}][notes]" rows="2"></textarea>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm mt-2 remove-item">
                        <i class="bi bi-trash me-1"></i> Remove
                    </button>
                </div>
            `;
        }
        
        // Add new schedule item
        addButton.addEventListener('click', function() {
            const index = container.children.length;
            const template = getTemplate(index);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = template;
            container.appendChild(tempDiv.firstElementChild);
        });
        
        // Remove schedule item
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item') || 
                e.target.parentElement.classList.contains('remove-item')) {
                const item = e.target.closest('.schedule-item-form');
                item.remove();
            }
        });
        
        // Add initial item if container is empty
        if (container.children.length === 0) {
            addButton.click();
        }
    });
    </script>
</body>
</html>