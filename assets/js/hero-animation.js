/**
 * Hero Animation for AcademicEase
 * Creates an interactive and engaging hero section for the homepage
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the hero animation if the hero container exists
    const heroContainer = document.getElementById('hero-animation-container');
    if (heroContainer) {
        initHeroAnimation(heroContainer);
    }
});

/**
 * Initialize the hero animation with floating elements
 * @param {HTMLElement} container - The container element for the hero animation
 */
function initHeroAnimation(container) {
    // Create floating elements
    const floatingElements = document.createElement('div');
    floatingElements.className = 'floating-elements';
    
    // Add educational elements
    addFloatingElement(floatingElements, 'book');
    addFloatingElement(floatingElements, 'pencil');
    addFloatingElement(floatingElements, 'calculator');
    addFloatingElement(floatingElements, 'globe');
    addFloatingElement(floatingElements, 'atom');
    addFloatingElement(floatingElements, 'ghana-flag');
    addFloatingElement(floatingElements, 'student');
    
    // Add student silhouettes
    const studentsGroup = document.createElement('div');
    studentsGroup.className = 'students-group';
    
    for (let i = 0; i < 4; i++) {
        const studentSilhouette = document.createElement('div');
        studentSilhouette.className = 'student-silhouette';
        studentsGroup.appendChild(studentSilhouette);
    }
    
    // Add text elements
    const heroText = document.createElement('div');
    heroText.className = 'hero-text';
    heroText.textContent = 'Learn. Grow. Succeed.';
    
    const heroBadge = document.createElement('div');
    heroBadge.className = 'hero-badge';
    heroBadge.textContent = 'AI-Powered';
    
    // Append all elements to container
    container.appendChild(floatingElements);
    container.appendChild(studentsGroup);
    container.appendChild(heroText);
    container.appendChild(heroBadge);
    
    // Add interactivity - elements follow cursor slightly
    container.addEventListener('mousemove', function(e) {
        const mouseX = e.clientX / window.innerWidth;
        const mouseY = e.clientY / window.innerHeight;
        
        const elements = container.querySelectorAll('.floating-element');
        elements.forEach(element => {
            const offsetX = (mouseX - 0.5) * 20;
            const offsetY = (mouseY - 0.5) * 20;
            const depth = parseFloat(element.getAttribute('data-depth') || 1);
            
            element.style.transform = `translate(${offsetX * depth}px, ${offsetY * depth}px)`;
        });
    });
}

/**
 * Add a floating element to the container
 * @param {HTMLElement} container - The container to add the element to
 * @param {string} className - The class name for the element
 */
function addFloatingElement(container, className) {
    const element = document.createElement('div');
    element.className = `floating-element ${className}`;
    
    // Add random depth for parallax effect
    const depth = (Math.random() * 0.5) + 0.5;
    element.setAttribute('data-depth', depth.toString());
    
    container.appendChild(element);
}
