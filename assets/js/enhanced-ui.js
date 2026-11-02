/**
 * Enhanced UI JavaScript for AcademicEase
 * Implements interactive features for UI improvements
 */

document.addEventListener('DOMContentLoaded', function() {
    // ===== 1. MOBILE EXPERIENCE IMPROVEMENTS =====
    initializeMobileCollapsibles();
    
    // ===== 2. VISUAL HIERARCHY IMPROVEMENTS =====
    initializeDashboardSections();
    
    // ===== 3. ENGAGEMENT ELEMENTS =====
    initializeAnimations();
    setupAchievementBadges();
    
    // ===== 4. PERSONALIZATION =====
    setupThemeSelector();
    setupFontSizeControls();
    initializeWelcomeMessage();
    initializeRecommendations();
});

// ===== 1. MOBILE EXPERIENCE IMPROVEMENTS =====

/**
 * Initialize collapsible sections for mobile view
 */
function initializeMobileCollapsibles() {
    // Only apply on mobile devices
    if (window.innerWidth <= 767.98) {
        const collapsibles = document.querySelectorAll('.mobile-collapsible');
        
        collapsibles.forEach(item => {
            const header = item.querySelector('.card-header');
            if (header) {
                header.addEventListener('click', function() {
                    item.classList.toggle('active');
                });
            }
        });
    }
}

// ===== 2. VISUAL HIERARCHY IMPROVEMENTS =====

/**
 * Initialize dashboard sections with icons and styling
 */
function initializeDashboardSections() {
    const sections = [
        { selector: '.progress-section', icon: 'bi-graph-up', title: 'Your Progress' },
        { selector: '.recent-activity-section', icon: 'bi-clock-history', title: 'Recent Activity' },
        { selector: '.goals-section', icon: 'bi-flag', title: 'Study Goals' },
        { selector: '.recommendations-section', icon: 'bi-lightbulb', title: 'Recommendations' }
    ];
    
    sections.forEach(section => {
        const element = document.querySelector(section.selector);
        if (element && !element.classList.contains('dashboard-section')) {
            element.classList.add('dashboard-section');
            
            // Add section title with icon if it doesn't exist
            if (!element.querySelector('.dashboard-section-title')) {
                const titleDiv = document.createElement('div');
                titleDiv.className = 'dashboard-section-title';
                titleDiv.innerHTML = `<i class="${section.icon}"></i>${section.title}`;
                element.prepend(titleDiv);
            }
        }
    });
}

// ===== 3. ENGAGEMENT ELEMENTS =====

/**
 * Initialize animations for progress bars and cards
 */
function initializeAnimations() {
    // Add hover-lift effect to cards
    document.querySelectorAll('.card:not(.no-animation)').forEach(card => {
        card.classList.add('hover-lift');
    });
    
    // Animate progress bars when they come into view
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width || bar.getAttribute('aria-valuenow') + '%';
        bar.style.setProperty('--final-width', width);
        bar.style.width = '0';
        bar.classList.add('progress-bar-animated');
    });
}

/**
 * Setup achievement badges
 */
function setupAchievementBadges() {
    // Get user achievements from data attribute or API
    const userAchievements = getUserAchievements();
    
    // Update badges based on earned achievements
    document.querySelectorAll('.achievement-badge').forEach(badge => {
        const achievementId = badge.getAttribute('data-achievement-id');
        if (userAchievements.includes(achievementId)) {
            badge.classList.add('earned');
            
            // Add tooltip with achievement name
            const achievementName = badge.getAttribute('data-achievement-name');
            if (achievementName) {
                badge.setAttribute('title', achievementName);
                badge.setAttribute('data-bs-toggle', 'tooltip');
                badge.setAttribute('data-bs-placement', 'top');
            }
        }
    });
    
    // Initialize Bootstrap tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
    }
}

/**
 * Get user achievements (placeholder - would be replaced with actual data)
 */
function getUserAchievements() {
    // This would normally fetch from an API or data attribute
    // For now, return some sample achievements
    return ['streak-7', 'quiz-perfect', 'study-10hrs'];
}

/**
 * Show celebration effect (confetti) for achievements
 */
function showCelebration() {
    const celebration = document.querySelector('.celebration');
    if (!celebration) {
        const celebrationDiv = document.createElement('div');
        celebrationDiv.className = 'celebration';
        document.body.appendChild(celebrationDiv);
        
        // Create confetti pieces
        for (let i = 0; i < 100; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
            confetti.style.backgroundColor = getRandomColor();
            celebrationDiv.appendChild(confetti);
        }
        
        celebrationDiv.style.display = 'block';
        
        // Remove after animation completes
        setTimeout(() => {
            celebrationDiv.remove();
        }, 5000);
    }
}

/**
 * Get random color for confetti
 */
function getRandomColor() {
    const colors = ['#4285F4', '#34A853', '#FBBC05', '#EA4335', '#5F6368'];
    return colors[Math.floor(Math.random() * colors.length)];
}

// ===== 4. PERSONALIZATION =====

/**
 * Setup theme selector
 */
function setupThemeSelector() {
    const themeSelector = document.getElementById('theme-selector');
    if (themeSelector) {
        // Set initial theme from localStorage
        const savedTheme = localStorage.getItem('academic-ease-theme');
        if (savedTheme) {
            document.body.className = document.body.className.replace(/theme-\w+/g, '').trim();
            document.body.classList.add('custom-theme');
            document.body.classList.add(savedTheme);
            themeSelector.value = savedTheme;
        }
        
        // Listen for theme changes
        themeSelector.addEventListener('change', function() {
            const selectedTheme = this.value;
            document.body.className = document.body.className.replace(/theme-\w+/g, '').trim();
            
            if (selectedTheme !== 'default') {
                document.body.classList.add('custom-theme');
                document.body.classList.add(selectedTheme);
                localStorage.setItem('academic-ease-theme', selectedTheme);
            } else {
                document.body.classList.remove('custom-theme');
                localStorage.removeItem('academic-ease-theme');
            }
        });
    }
}

/**
 * Setup font size controls
 */
function setupFontSizeControls() {
    const fontControls = document.querySelectorAll('[data-font-size]');
    
    // Set initial font size from localStorage
    const savedFontSize = localStorage.getItem('academic-ease-font-size');
    if (savedFontSize) {
        document.body.className = document.body.className.replace(/font-size-\w+/g, '').trim();
        document.body.classList.add(savedFontSize);
        
        // Update active state on buttons
        fontControls.forEach(control => {
            if (control.getAttribute('data-font-size') === savedFontSize) {
                control.classList.add('active');
            } else {
                control.classList.remove('active');
            }
        });
    }
    
    // Listen for font size changes
    fontControls.forEach(control => {
        control.addEventListener('click', function() {
            const fontSize = this.getAttribute('data-font-size');
            document.body.className = document.body.className.replace(/font-size-\w+/g, '').trim();
            document.body.classList.add(fontSize);
            localStorage.setItem('academic-ease-font-size', fontSize);
            
            // Update active state on buttons
            fontControls.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

/**
 * Initialize personalized welcome message
 */
function initializeWelcomeMessage() {
    const welcomeContainer = document.querySelector('.welcome-container');
    if (welcomeContainer) {
        const userName = getUserName();
        const userInitial = userName.charAt(0).toUpperCase();
        const timeOfDay = getTimeOfDay();
        const studyRecommendation = getStudyRecommendation();
        
        const welcomeHTML = `
            <div class="welcome-message">
                <div class="user-avatar">${userInitial}</div>
                <div class="message-content">
                    <h4>Good ${timeOfDay}, ${userName}!</h4>
                    <p>${studyRecommendation}</p>
                </div>
            </div>
        `;
        
        welcomeContainer.innerHTML = welcomeHTML;
    }
}

/**
 * Get user's name (placeholder - would be replaced with actual data)
 */
function getUserName() {
    // This would normally fetch from session data
    const nameElement = document.querySelector('[data-user-name]');
    return nameElement ? nameElement.getAttribute('data-user-name') : 'Student';
}

/**
 * Get appropriate greeting based on time of day
 */
function getTimeOfDay() {
    const hour = new Date().getHours();
    if (hour < 12) return 'morning';
    if (hour < 17) return 'afternoon';
    return 'evening';
}

/**
 * Get personalized study recommendation (placeholder)
 */
function getStudyRecommendation() {
    // This would normally be based on user activity and progress
    const recommendations = [
        "Based on your recent activity, it's a good time to review Mathematics.",
        "You haven't studied Science in a few days. Consider a quick review session.",
        "You're making great progress in English! Keep up the good work.",
        "Your quiz scores show you might need more practice with Social Studies.",
        "You're on a 3-day study streak! Try to maintain it for the rest of the week."
    ];
    
    return recommendations[Math.floor(Math.random() * recommendations.length)];
}

/**
 * Initialize study recommendations
 */
function initializeRecommendations() {
    const recommendationsContainer = document.querySelector('.recommendations-container');
    if (recommendationsContainer) {
        const recommendations = getPersonalizedRecommendations();
        
        let recommendationsHTML = '';
        recommendations.forEach(rec => {
            recommendationsHTML += `
                <div class="card recommendation-card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>${rec.subject}</span>
                        <span class="badge bg-primary">${rec.type}</span>
                    </div>
                    <div class="card-body">
                        <p class="card-text">${rec.message}</p>
                        <a href="${rec.link}" class="btn btn-sm btn-outline-primary">Start Now</a>
                    </div>
                </div>
            `;
        });
        
        recommendationsContainer.innerHTML = recommendationsHTML;
    }
}

/**
 * Get personalized recommendations (placeholder)
 */
function getPersonalizedRecommendations() {
    // This would normally be based on user data
    return [
        {
            subject: 'Mathematics',
            type: 'Quiz',
            message: 'We recommend taking this quiz to test your knowledge of Algebra.',
            link: 'quiz.php?subject=mathematics&topic=algebra'
        },
        {
            subject: 'Science',
            type: 'Study',
            message: 'Review these study materials on Photosynthesis to improve your understanding.',
            link: 'study.php?subject=science&topic=photosynthesis'
        },
        {
            subject: 'English',
            type: 'Practice',
            message: 'Practice your grammar skills with these exercises.',
            link: 'study.php?subject=english&topic=grammar'
        }
    ];
}

/**
 * Display streak information
 */
function displayStreakInfo() {
    const streakContainer = document.querySelector('.streak-container');
    if (streakContainer) {
        const streakDays = getUserStreak();
        
        if (streakDays > 0) {
            streakContainer.innerHTML = `
                <div class="streak-counter">
                    <i class="bi bi-fire"></i>
                    <span>${streakDays}-day streak!</span>
                </div>
            `;
        }
    }
}

/**
 * Get user's current streak (placeholder)
 */
function getUserStreak() {
    // This would normally fetch from session data or API
    const streakElement = document.querySelector('[data-user-streak]');
    return streakElement ? parseInt(streakElement.getAttribute('data-user-streak')) : 0;
}

// Initialize responsive behavior
window.addEventListener('resize', function() {
    initializeMobileCollapsibles();
});
