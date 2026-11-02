/**
 * Performance optimization script for Academic Ease
 * Improves navigation speed and mobile experience
 */

// Enable link prefetching for faster navigation
document.addEventListener('DOMContentLoaded', function() {
    // Prefetch links when user hovers over them
    const links = document.querySelectorAll('a');
    links.forEach(link => {
        if (link.href && link.href.startsWith(window.location.origin)) {
            link.addEventListener('mouseenter', () => {
                const prefetchLink = document.createElement('link');
                prefetchLink.rel = 'prefetch';
                prefetchLink.href = link.href;
                document.head.appendChild(prefetchLink);
            });
        }
    });
    
    // Add smooth transitions between pages
    const mainContent = document.querySelector('main') || document.querySelector('.container');
    if (mainContent) {
        mainContent.style.transition = 'opacity 0.3s ease';
        
        // Fade out content when navigating away
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a');
            if (target && target.href && target.href.startsWith(window.location.origin) && !target.getAttribute('download')) {
                e.preventDefault();
                mainContent.style.opacity = '0';
                
                setTimeout(() => {
                    window.location.href = target.href;
                }, 300);
            }
        });
        
        // Fade in content when page loads
        window.addEventListener('pageshow', function() {
            mainContent.style.opacity = '0';
            setTimeout(() => {
                mainContent.style.opacity = '1';
            }, 10);
        });
    }
    
    // Optimize image loading with lazy loading
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        if (!img.hasAttribute('loading')) {
            img.setAttribute('loading', 'lazy');
        }
    });
});

// Improve mobile touch response by removing 300ms delay
document.addEventListener('touchend', function() {}, false);
