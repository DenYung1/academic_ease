/**
 * Device Tracking JavaScript
 * Collects device information for fingerprinting
 */

document.addEventListener('DOMContentLoaded', function() {
    // Collect screen information
    const screenWidth = window.screen.width;
    const screenHeight = window.screen.height;
    const colorDepth = window.screen.colorDepth;
    
    // Get timezone offset
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    
    // Get browser capabilities
    const hasLocalStorage = !!window.localStorage;
    const hasSessionStorage = !!window.sessionStorage;
    const hasCookies = navigator.cookieEnabled;
    
    // Get platform information
    const platform = navigator.platform;
    
    // Create a data object with all device information
    const deviceData = {
        screen_width: screenWidth,
        screen_height: screenHeight,
        color_depth: colorDepth,
        timezone: timezone,
        has_local_storage: hasLocalStorage,
        has_session_storage: hasSessionStorage,
        has_cookies: hasCookies,
        platform: platform
    };
    
    // Store data in session storage
    if (hasSessionStorage) {
        // Store each piece of data individually in the session
        for (const [key, value] of Object.entries(deviceData)) {
            sessionStorage.setItem(key, value);
        }
    }
    
    // Send data to server via fetch API
    fetch('device-info.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(deviceData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Device information recorded');
    })
    .catch(error => {
        console.error('Error recording device information:', error);
    });
});
