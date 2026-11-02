/**
 * Settings Panel for AcademicEase
 * Provides UI customization options for users
 */

document.addEventListener('DOMContentLoaded', function() {
    // Create settings panel if it doesn't exist
    if (!document.getElementById('settings-panel')) {
        createSettingsPanel();
    }
    
    // Initialize settings from localStorage
    initializeSettings();
    
    // Add event listener to toggle button
    document.getElementById('settings-toggle').addEventListener('click', function() {
        toggleSettingsPanel();
    });
    
    // Add event listeners to settings controls
    setupEventListeners();
});

/**
 * Create the settings panel HTML
 */
function createSettingsPanel() {
    const settingsHTML = `
        <div id="settings-panel" class="settings-panel">
            <div class="settings-header">
                <h5>Customize Your Experience</h5>
                <button id="settings-close" class="settings-close">&times;</button>
            </div>
            <div class="settings-body">
                <div class="settings-section">
                    <h6>Theme</h6>
                    <select id="theme-selector" class="form-select">
                        <option value="default">Default</option>
                        <option value="theme-light">Light</option>
                        <option value="theme-dark">Dark</option>
                        <option value="theme-sepia">Sepia</option>
                    </select>
                </div>
                <div class="settings-section">
                    <h6>Font Size</h6>
                    <div class="btn-group w-100">
                        <button data-font-size="font-size-small" class="btn btn-outline-secondary">Small</button>
                        <button data-font-size="font-size-medium" class="btn btn-outline-secondary active">Medium</button>
                        <button data-font-size="font-size-large" class="btn btn-outline-secondary">Large</button>
                    </div>
                </div>
                <div class="settings-section">
                    <h6>Animations</h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="animations-toggle" checked>
                        <label class="form-check-label" for="animations-toggle">Enable animations</label>
                    </div>
                </div>
            </div>
        </div>
        <button id="settings-toggle" class="settings-toggle">
            <i class="bi bi-gear-fill"></i>
        </button>
    `;
    
    // Create container for settings
    const settingsContainer = document.createElement('div');
    settingsContainer.innerHTML = settingsHTML;
    document.body.appendChild(settingsContainer);
    
    // Add event listener to close button
    document.getElementById('settings-close').addEventListener('click', function() {
        toggleSettingsPanel(false);
    });
    
    // Add CSS for settings panel
    addSettingsPanelStyles();
}

/**
 * Add CSS styles for settings panel
 */
function addSettingsPanelStyles() {
    if (!document.getElementById('settings-panel-styles')) {
        const styleElement = document.createElement('style');
        styleElement.id = 'settings-panel-styles';
        styleElement.textContent = `
            .settings-panel {
                position: fixed;
                top: 0;
                right: -300px;
                width: 300px;
                height: 100%;
                background-color: #fff;
                box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
                z-index: 9999;
                transition: right 0.3s ease;
                overflow-y: auto;
            }
            
            .settings-panel.active {
                right: 0;
            }
            
            .settings-header {
                padding: 15px;
                border-bottom: 1px solid #dee2e6;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .settings-header h5 {
                margin: 0;
                font-weight: 600;
            }
            
            .settings-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                line-height: 1;
                cursor: pointer;
                color: #6c757d;
            }
            
            .settings-body {
                padding: 15px;
            }
            
            .settings-section {
                margin-bottom: 20px;
            }
            
            .settings-section h6 {
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .settings-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background-color: #4285F4;
                color: white;
                border: none;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
                z-index: 9998;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: transform 0.2s ease;
            }
            
            .settings-toggle:hover {
                transform: scale(1.1);
            }
            
            .settings-toggle i {
                font-size: 1.5rem;
            }
            
            body.theme-dark .settings-panel {
                background-color: #343a40;
                color: #f8f9fa;
            }
            
            body.theme-dark .settings-header {
                border-color: #495057;
            }
            
            body.theme-dark .settings-close {
                color: #adb5bd;
            }
            
            body.theme-dark .form-select,
            body.theme-dark .btn-outline-secondary {
                background-color: #495057;
                color: #f8f9fa;
                border-color: #6c757d;
            }
            
            body.theme-sepia .settings-panel {
                background-color: #f8f0e3;
                color: #5b4636;
            }
            
            body.theme-sepia .settings-header {
                border-color: #d3c6b6;
            }
        `;
        document.head.appendChild(styleElement);
    }
}

/**
 * Toggle settings panel visibility
 */
function toggleSettingsPanel(show) {
    const panel = document.getElementById('settings-panel');
    if (show === undefined) {
        panel.classList.toggle('active');
    } else {
        if (show) {
            panel.classList.add('active');
        } else {
            panel.classList.remove('active');
        }
    }
}

/**
 * Initialize settings from localStorage
 */
function initializeSettings() {
    // Set theme
    const savedTheme = localStorage.getItem('academic-ease-theme');
    if (savedTheme) {
        document.body.className = document.body.className.replace(/theme-\w+/g, '').trim();
        document.body.classList.add('custom-theme');
        document.body.classList.add(savedTheme);
        
        const themeSelector = document.getElementById('theme-selector');
        if (themeSelector) {
            themeSelector.value = savedTheme;
        }
    }
    
    // Set font size
    const savedFontSize = localStorage.getItem('academic-ease-font-size');
    if (savedFontSize) {
        document.body.className = document.body.className.replace(/font-size-\w+/g, '').trim();
        document.body.classList.add(savedFontSize);
        
        const fontButtons = document.querySelectorAll('[data-font-size]');
        fontButtons.forEach(button => {
            button.classList.remove('active');
            if (button.getAttribute('data-font-size') === savedFontSize) {
                button.classList.add('active');
            }
        });
    }
    
    // Set animations
    const animationsEnabled = localStorage.getItem('academic-ease-animations');
    if (animationsEnabled === 'false') {
        document.body.classList.add('no-animations');
        
        const animationsToggle = document.getElementById('animations-toggle');
        if (animationsToggle) {
            animationsToggle.checked = false;
        }
    }
}

/**
 * Setup event listeners for settings controls
 */
function setupEventListeners() {
    // Theme selector
    const themeSelector = document.getElementById('theme-selector');
    if (themeSelector) {
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
    
    // Font size buttons
    const fontButtons = document.querySelectorAll('[data-font-size]');
    fontButtons.forEach(button => {
        button.addEventListener('click', function() {
            const fontSize = this.getAttribute('data-font-size');
            document.body.className = document.body.className.replace(/font-size-\w+/g, '').trim();
            document.body.classList.add(fontSize);
            localStorage.setItem('academic-ease-font-size', fontSize);
            
            // Update active state on buttons
            fontButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Animations toggle
    const animationsToggle = document.getElementById('animations-toggle');
    if (animationsToggle) {
        animationsToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.remove('no-animations');
                localStorage.setItem('academic-ease-animations', 'true');
            } else {
                document.body.classList.add('no-animations');
                localStorage.setItem('academic-ease-animations', 'false');
            }
        });
    }
}
