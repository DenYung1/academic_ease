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
