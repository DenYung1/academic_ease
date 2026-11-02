<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db_connect.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user data
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
    
    // Check subscription status
    $subscription_status = $user['subscription_status'];
    $subscription_end_date = strtotime($user['subscription_end_date']);
    $current_time = time();
    
    if (($subscription_status === 'trial' && $subscription_end_date < $current_time) || 
        $subscription_status === 'expired') {
        // Subscription has expired, redirect to subscription page
        header('Location: subscribe-direct.php?expired=1');
        exit;
    }
    
    // Get user's notes
    $endpoint = 'notes?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=updated_at.desc';
    $notes = supabaseRequest($endpoint, 'GET', null, true);
    
    // If notes is not an array, initialize it as an empty array
    if (!is_array($notes)) {
        $notes = [];
    }
    
    // Process form submission to create/edit a note
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_note'])) {
            // Create a new note
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
            
            $errors = [];
            
            // Validate inputs
            if (empty($title)) {
                $errors[] = 'Note title is required.';
            }
            
            // If no errors, create the note
            if (empty($errors)) {
                $note_data = [
                    'user_id' => $_SESSION['user_id'],
                    'title' => $title,
                    'content' => $content,
                    'subject' => $subject,
                    'created_at' => date('c'),
                    'updated_at' => date('c')
                ];
                
                $result = supabaseRequest('notes', 'POST', $note_data, true);
                
                if ($result && !isset($result['error'])) {
                    $success_message = 'Note created successfully!';
                    
                    // Refresh the notes list
                    $endpoint = 'notes?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=updated_at.desc';
                    $notes = supabaseRequest($endpoint, 'GET', null, true);
                } else {
                    $errors[] = 'Failed to create note. Please try again.';
                }
            }
        } else if (isset($_POST['edit_note'])) {
            // Edit an existing note
            $note_id = isset($_POST['note_id']) ? trim($_POST['note_id']) : '';
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $content = isset($_POST['content']) ? trim($_POST['content']) : '';
            $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
            
            $errors = [];
            
            // Validate inputs
            if (empty($title)) {
                $errors[] = 'Note title is required.';
            }
            
            if (empty($note_id)) {
                $errors[] = 'Note ID is missing.';
            }
            
            // If no errors, update the note
            if (empty($errors)) {
                $note_data = [
                    'title' => $title,
                    'content' => $content,
                    'subject' => $subject,
                    'updated_at' => date('c')
                ];
                
                $endpoint = 'notes?note_id=eq.' . urlencode($note_id);
                $result = supabaseRequest($endpoint, 'PATCH', $note_data, true);
                
                if ($result && !isset($result['error'])) {
                    $success_message = 'Note updated successfully!';
                    
                    // Refresh the notes list
                    $endpoint = 'notes?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=updated_at.desc';
                    $notes = supabaseRequest($endpoint, 'GET', null, true);
                } else {
                    $errors[] = 'Failed to update note. Please try again.';
                }
            }
        } else if (isset($_POST['delete_note'])) {
            // Delete a note
            $note_id = isset($_POST['note_id']) ? trim($_POST['note_id']) : '';
            
            if (!empty($note_id)) {
                $endpoint = 'notes?note_id=eq.' . urlencode($note_id);
                $result = supabaseRequest($endpoint, 'DELETE', null, true);
                
                if ($result && !isset($result['error'])) {
                    $success_message = 'Note deleted successfully!';
                    
                    // Refresh the notes list
                    $endpoint = 'notes?user_id=eq.' . urlencode($_SESSION['user_id']) . '&order=updated_at.desc';
                    $notes = supabaseRequest($endpoint, 'GET', null, true);
                } else {
                    $errors[] = 'Failed to delete note. Please try again.';
                }
            } else {
                $errors[] = 'Note ID is missing.';
            }
        }
    }
    
    // Define subjects based on level
    $level = isset($user['school_level']) ? $user['school_level'] : 'jhs';
    
    $subjects = [
        'jhs' => [
            'mathematics' => 'Mathematics',
            'english' => 'English',
            'science' => 'Science',
            'social_studies' => 'Social Studies',
            'ict' => 'ICT',
            'integrated_science' => 'Integrated Science'
        ],
        'shs' => [
            'mathematics' => 'Mathematics',
            'english' => 'English',
            'science' => 'Science',
            'social_studies' => 'Social Studies',
            'chemistry' => 'Chemistry',
            'physics' => 'Physics',
            'biology' => 'Biology',
            'government' => 'Government',
            'economics' => 'Economics',
            'literature' => 'Literature'
        ]
    ];
    
} catch (Exception $e) {
    error_log('Notes Error: ' . $e->getMessage());
    $error = 'An error occurred while loading your notes. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes - Academic Ease</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    
    <style>
        .note-card {
            transition: transform 0.2s;
        }
        
        .note-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .note-preview {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .subject-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        
        .note-date {
            font-size: 0.8rem;
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
                        <a class="nav-link" href="ai-assistant.php">AI Assistant</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="study.php">Study</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="notes.php">Notes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="schedule.php">Schedule</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo isset($user['full_name']) ? htmlspecialchars($user['full_name']) : 'User'; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Notes Content -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="mb-0">My Notes</h2>
                <p class="text-muted">Create and manage your study notes</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNoteModal">
                    <i class="bi bi-plus-circle me-1"></i> Create New Note
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Notes List -->
        <div class="row mb-5">
            <?php if (empty($notes)): ?>
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-journal-text text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No Notes Yet</h4>
                            <p class="text-muted">Create your first note to get started!</p>
                            <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createNoteModal">
                                <i class="bi bi-plus-circle me-1"></i> Create New Note
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card note-card h-100">
                            <?php 
                            $subject_key = isset($note['subject']) ? $note['subject'] : '';
                            $subject_name = isset($subjects[$level][$subject_key]) ? $subjects[$level][$subject_key] : $subject_key;
                            ?>
                            <?php if (!empty($subject_name)): ?>
                                <span class="badge bg-primary subject-badge"><?php echo htmlspecialchars($subject_name); ?></span>
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($note['title']); ?></h5>
                                <p class="note-date">
                                    <i class="bi bi-clock me-1"></i> 
                                    <?php echo isset($note['updated_at']) ? date('M j, Y g:i A', strtotime($note['updated_at'])) : ''; ?>
                                </p>
                                <div class="note-preview">
                                    <?php echo nl2br(htmlspecialchars($note['content'])); ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <div class="d-flex justify-content-between">
                                    <button class="btn btn-sm btn-outline-primary" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#viewNoteModal" 
                                            data-note-id="<?php echo $note['note_id']; ?>"
                                            data-note-title="<?php echo htmlspecialchars($note['title']); ?>"
                                            data-note-content="<?php echo htmlspecialchars($note['content']); ?>"
                                            data-note-subject="<?php echo htmlspecialchars($subject_key); ?>">
                                        <i class="bi bi-eye me-1"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteNoteModal"
                                            data-note-id="<?php echo $note['note_id']; ?>"
                                            data-note-title="<?php echo htmlspecialchars($note['title']); ?>">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Note Taking Tips -->
        <div class="row mb-5">
            <div class="col-md-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Effective Note-Taking Tips</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Use the Cornell Method</h5>
                                <p>Divide your page into three sections: a narrow left column for cues, a wide right column for notes, and a bottom section for summary. This helps organize information and facilitates review.</p>
                                
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Focus on Key Concepts</h5>
                                <p>Instead of trying to write down everything, focus on main ideas, key terms, and important relationships. Use your own words to enhance understanding.</p>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Use Visual Elements</h5>
                                <p>Incorporate diagrams, mind maps, charts, and other visual aids to represent complex information and relationships between concepts.</p>
                                
                                <h5><i class="bi bi-check-circle-fill text-success me-2"></i> Review and Revise</h5>
                                <p>Set aside time to review and revise your notes within 24 hours of taking them. This reinforces learning and helps identify gaps in understanding.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI Assistant Promotion -->
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-light border-0">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4>Need help organizing your notes?</h4>
                                <p class="mb-0">Our AI Assistant can help summarize content, suggest study strategies, and create practice questions from your notes.</p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <a href="ai-assistant.php" class="btn btn-primary">
                                    <i class="bi bi-robot me-2"></i> Ask AI Assistant
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Note Modal -->
    <div class="modal fade" id="createNoteModal" tabindex="-1" aria-labelledby="createNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createNoteModalLabel">Create New Note</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title*</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <select class="form-select" id="subject" name="subject">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects[$level] as $subject_key => $subject_name): ?>
                                    <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Content*</label>
                            <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="create_note" class="btn btn-primary">Save Note</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View/Edit Note Modal -->
    <div class="modal fade" id="viewNoteModal" tabindex="-1" aria-labelledby="viewNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewNoteModalLabel">View Note</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" id="edit_note_id" name="note_id">
                        
                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title*</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_subject" class="form-label">Subject</label>
                            <select class="form-select" id="edit_subject" name="subject">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects[$level] as $subject_key => $subject_name): ?>
                                    <option value="<?php echo $subject_key; ?>"><?php echo htmlspecialchars($subject_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_content" class="form-label">Content*</label>
                            <textarea class="form-control" id="edit_content" name="content" rows="10" required></textarea>
                        </div>
                        
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="edit_note" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Note Modal -->
    <div class="modal fade" id="deleteNoteModal" tabindex="-1" aria-labelledby="deleteNoteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteNoteModalLabel">Delete Note</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the note "<span id="delete_note_title"></span>"? This action cannot be undone.</p>
                    <form method="post" action="">
                        <input type="hidden" id="delete_note_id" name="note_id">
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_note" class="btn btn-danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer py-4 bg-light mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 Academic Ease. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted me-3">Terms of Service</a>
                    <a href="support.php" class="text-muted">Support</a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle view/edit note modal
        document.addEventListener('DOMContentLoaded', function() {
            const viewNoteModal = document.getElementById('viewNoteModal');
            if (viewNoteModal) {
                viewNoteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const noteId = button.getAttribute('data-note-id');
                    const noteTitle = button.getAttribute('data-note-title');
                    const noteContent = button.getAttribute('data-note-content');
                    const noteSubject = button.getAttribute('data-note-subject');
                    
                    const modal = this;
                    modal.querySelector('#edit_note_id').value = noteId;
                    modal.querySelector('#edit_title').value = noteTitle;
                    modal.querySelector('#edit_content').value = noteContent;
                    
                    const subjectSelect = modal.querySelector('#edit_subject');
                    if (subjectSelect) {
                        for (let i = 0; i < subjectSelect.options.length; i++) {
                            if (subjectSelect.options[i].value === noteSubject) {
                                subjectSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                });
            }
            
            // Handle delete note modal
            const deleteNoteModal = document.getElementById('deleteNoteModal');
            if (deleteNoteModal) {
                deleteNoteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const noteId = button.getAttribute('data-note-id');
                    const noteTitle = button.getAttribute('data-note-title');
                    
                    const modal = this;
                    modal.querySelector('#delete_note_id').value = noteId;
                    modal.querySelector('#delete_note_title').textContent = noteTitle;
                });
            }
        });
    </script>
</body>
</html>
