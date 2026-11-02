/**
 * Navigation Fix for AcademicEase
 * 
 * This script improves the navigation experience by:
 * 1. Ensuring smooth transitions between pages without loading states
 * 2. Preventing the navigation bar from disappearing during page loads
 * 3. Maintaining the user's scroll position between page navigations
 */

document.addEventListener('DOMContentLoaded', function() {
    // Add styles to ensure navbar visibility and smooth transitions
    const style = document.createElement('style');
    style.textContent = `
        /* Ensure the navbar stays visible during page transitions */
        .navbar {
            z-index: 1030;
            position: relative;
        }
        
        /* Smooth transition for page content */
        body {
            transition: opacity 0.1s ease;
        }
        
        /* Fix for dropdown menus */
        .dropdown-menu {
            z-index: 1031;
        }
        
        /* Ensure user account dropdown stays above other elements */
        .user-account-dropdown {
            z-index: 1032;
        }
    `;
    
    document.head.appendChild(style);
    
    // Get all navigation links
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link, .dropdown-item');
    
    // Fix dropdown toggle links to prevent page reload
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        if (toggle.getAttribute('href') === '#') {
            toggle.setAttribute('href', 'javascript:void(0);');
        }
    });
    
    // Add click event listeners to navigation links
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Don't handle special links
            if (this.getAttribute('href').startsWith('#') || 
                this.getAttribute('href').startsWith('javascript:') ||
                this.getAttribute('target') === '_blank') {
                return;
            }
            
            // Store the current scroll position
            sessionStorage.setItem('scrollPosition', window.scrollY);
            
            // Continue with normal navigation without delay
            // The browser will handle the page transition
        });
    });
    
    // When page is loaded, restore scroll position if available
    window.addEventListener('load', function() {
        const scrollPosition = sessionStorage.getItem('scrollPosition');
        if (scrollPosition) {
            window.scrollTo(0, parseInt(scrollPosition));
            sessionStorage.removeItem('scrollPosition');
        }
    });
    
    // Add active class to current page in navigation
    const currentPage = window.location.pathname.split('/').pop();
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
});
