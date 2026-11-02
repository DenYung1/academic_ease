/**
 * Performance optimization script for Academic Ease
 * Improves navigation speed and mobile experience
 * Version 2.0 - Simplified for maximum reliability
 */

document.addEventListener('DOMContentLoaded', function() {
    // DISABLE ALL PAGE TRANSITIONS - they were causing navigation issues
    // Instead, focus on performance optimizations that don't affect navigation
    
    // 1. LINK PREFETCHING - Speed up navigation without transitions
    const links = document.querySelectorAll('a');
    links.forEach(link => {
        // Only prefetch internal links
        if (link.href && link.href.startsWith(window.location.origin)) {
            link.addEventListener('mouseenter', () => {
                // Create a prefetch hint for the browser
                const prefetchLink = document.createElement('link');
                prefetchLink.rel = 'prefetch';
                prefetchLink.href = link.href;
                document.head.appendChild(prefetchLink);
            });
        }
    });
    
    // 2. FIX DROPDOWN MENUS - Ensure they work correctly on all devices
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        // Ensure dropdowns work on mobile
        toggle.addEventListener('click', (e) => {
            // Make sure the dropdown is properly shown
            const dropdown = toggle.nextElementSibling;
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                // Force dropdown to be visible and positioned correctly
                dropdown.style.display = 'block';
                dropdown.style.position = 'absolute';
                
                // Position the dropdown correctly
                if (window.innerWidth < 992) { // Mobile view
                    dropdown.style.left = '0';
                    dropdown.style.width = '100%';
                } else { // Desktop view
                    dropdown.style.left = 'auto';
                    dropdown.style.right = '0';
                }
                
                // Hide dropdown when clicking outside
                const hideDropdown = () => {
                    dropdown.style.display = '';
                    document.removeEventListener('click', hideDropdownListener);
                };
                
                const hideDropdownListener = (event) => {
                    if (!dropdown.contains(event.target) && event.target !== toggle) {
                        hideDropdown();
                    }
                };
                
                // Add listener after a short delay to avoid immediate closing
                setTimeout(() => {
                    document.addEventListener('click', hideDropdownListener);
                }, 100);
            }
        });
    });
    
    // 3. OPTIMIZE IMAGES - Use lazy loading for better performance
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        if (!img.hasAttribute('loading')) {
            img.setAttribute('loading', 'lazy');
        }
    });
    
    // 4. IMPROVE MOBILE MENU - Fix issues with the mobile navigation
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        // Ensure the mobile menu works correctly
        navbarToggler.addEventListener('click', () => {
            // Force the navbar to be visible after toggling
            setTimeout(() => {
                if (navbarCollapse.classList.contains('show')) {
                    navbarCollapse.style.display = 'block';
                }
            }, 50);
        });
    }
    
    // 5. HIGHLIGHT CURRENT PAGE - Mark the current page in the navigation
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkPath = new URL(link.href).pathname;
        if (linkPath === currentPath) {
            link.classList.add('active');
        }
    });
});

// Improve mobile touch response by removing 300ms delay
document.addEventListener('touchend', function() {}, false);
