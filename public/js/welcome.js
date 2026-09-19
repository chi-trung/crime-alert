// Issue #416: honour the OS-level reduced-motion preference. The CSS guard in
// layouts/app.blade.php collapses animation durations, but the three effects
// below are driven from JS and the CSS guard cannot reach them: createParticles
// spawns the drifting dots, handleParallax moves layers on scroll, and the
// ripple below is built per click. All three are motion, so all three are
// skipped. The count-up stats are unaffected — a number reaching its final
// value is content, not motion, and CSS cannot animate textContent anyway.
function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

// Create floating particles
function createParticles() {
    const particlesContainer = document.getElementById('particles');
    const particleCount = 60;

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        const size = Math.random() * 5 + 2;
        const startX = Math.random() * window.innerWidth;
        const delay = Math.random() * 25;
        
        particle.style.width = size + 'px';
        particle.style.height = size + 'px';
        particle.style.left = startX + 'px';
        particle.style.animationDelay = delay + 's';
        
        particlesContainer.appendChild(particle);
    }
}

// Animate stats numbers
function animateStats() {
    const statNumbers = document.querySelectorAll('.stat-number');
    
    statNumbers.forEach(stat => {
        const text = stat.textContent;
        // Issue #351: the guard below used to be text.includes('+'), which
        // matches "1000+" (fine) but also a hypothetical "24/7+" or any copy
        // carrying a plus that is not a count. parseInt then yields NaN, the
        // interval counts NaN forever and the stat displays "NaN+". Require
        // an actual integer; anything else (the "24/7" monitoring badge
        // alongside) renders verbatim, which is what it already does today.
        const parsed = parseInt(text, 10);
        if (text.includes('+') && Number.isInteger(parsed)) {
            const finalNumber = parsed;
            let currentNumber = 0;
            const increment = finalNumber / 80;

            const timer = setInterval(() => {
                currentNumber += increment;
                if (currentNumber >= finalNumber) {
                    stat.textContent = finalNumber + '+';
                    clearInterval(timer);
                } else {
                    stat.textContent = Math.floor(currentNumber) + '+';
                }
            }, 30);
        }
    });
}

// Enhanced parallax effect.
// Issue #359: the listener used to be registered *inside* the scroll
// handler, so every scroll event attached another scroll handler and the
// transform work compounded. Registered once here instead.
function handleParallax() {
    const background = document.querySelector('.background');
    const particles = document.querySelector('.particles');

    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const rate = scrolled * -0.3;

        background.style.transform = `translateY(${rate}px)`;
        particles.style.transform = `translateY(${rate * 0.5}px)`;
    });
}

// Intersection Observer for animations
function setupIntersectionObserver() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                if (entry.target.classList.contains('stats')) {
                    animateStats();
                }
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '50px'
    });

    // Observe elements
    document.querySelectorAll('.feature-card, .stats').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(50px)';
        el.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
        observer.observe(el);
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    // Issue #416: the particle field, the scroll parallax and the click ripple
    // are all generated here, so the CSS guard alone cannot suppress them.
    if (prefersReducedMotion()) return;

    createParticles();
    setupIntersectionObserver();
    handleParallax();

    // Add click effects to buttons
    document.querySelectorAll('.cta-button, .nav-link').forEach(button => {
        button.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.4);
                transform: scale(0);
                animation: ripple 0.6s linear;
                left: ${x}px;
                top: ${y}px;
                width: ${size}px;
                height: ${size}px;
                pointer-events: none;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
});

// Add ripple animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
