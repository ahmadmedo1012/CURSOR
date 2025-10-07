/**
 * GameBox UX Enhancements
 * Mobile & Performance Improvements
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initUXEnhancements();
    });

    function initUXEnhancements() {
        // Initialize loading states
        initLoadingStates();
        
        // Initialize mobile optimizations
        initMobileOptimizations();
        
        // Initialize accessibility improvements
        initAccessibilityImprovements();
        
        // Initialize performance optimizations
        initPerformanceOptimizations();
        
        // Initialize error handling
        initErrorHandling();
    }

    function initLoadingStates() {
        // Add loading class to body during page transitions
        const links = document.querySelectorAll('a[href]');
        links.forEach(link => {
            link.addEventListener('click', function(e) {
                // Only for internal links
                if (this.hostname === window.location.hostname) {
                    document.body.classList.add('loading');
                }
            });
        });

        // Remove loading class when page is fully loaded
        window.addEventListener('load', function() {
            document.body.classList.remove('loading');
        });
    }

    function initMobileOptimizations() {
        // Prevent zoom on input focus (iOS)
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                if (window.innerWidth <= 768) {
                    this.style.fontSize = '16px';
                }
            });
        });

        // Improve touch targets
        const touchElements = document.querySelectorAll('.btn, .button, .nav-link, .dropdown-item');
        touchElements.forEach(element => {
            if (element.offsetHeight < 44) {
                element.style.minHeight = '44px';
                element.style.display = 'flex';
                element.style.alignItems = 'center';
                element.style.justifyContent = 'center';
            }
        });

        // Handle viewport changes
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                // Recalculate touch targets on resize
                initMobileOptimizations();
            }, 250);
        });
    }

    function initAccessibilityImprovements() {
        // Add focus indicators
        const focusableElements = document.querySelectorAll('a, button, input, select, textarea, [tabindex]');
        focusableElements.forEach(element => {
            element.addEventListener('focus', function() {
                this.classList.add('focused');
            });
            
            element.addEventListener('blur', function() {
                this.classList.remove('focused');
            });
        });

        // Improve form labels
        const inputs = document.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (!input.getAttribute('aria-label') && !input.getAttribute('aria-labelledby')) {
                const label = document.querySelector(`label[for="${input.id}"]`);
                if (label) {
                    input.setAttribute('aria-labelledby', label.id || 'label-' + input.id);
                    if (!label.id) {
                        label.id = 'label-' + input.id;
                    }
                }
            }
        });

        // Add skip links
        if (!document.querySelector('.skip-link')) {
            const skipLink = document.createElement('a');
            skipLink.href = '#main-content';
            skipLink.className = 'skip-link';
            skipLink.textContent = 'Skip to main content';
            skipLink.style.cssText = `
                position: absolute;
                top: -40px;
                left: 6px;
                background: var(--accent-color, #C9A227);
                color: white;
                padding: 8px;
                text-decoration: none;
                border-radius: 4px;
                z-index: 1000;
                transition: top 0.3s;
            `;
            
            skipLink.addEventListener('focus', function() {
                this.style.top = '6px';
            });
            
            skipLink.addEventListener('blur', function() {
                this.style.top = '-40px';
            });
            
            document.body.insertBefore(skipLink, document.body.firstChild);
        }
    }

    function initPerformanceOptimizations() {
        // Lazy load images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    }
                });
            });

            const lazyImages = document.querySelectorAll('img[data-src]');
            lazyImages.forEach(img => imageObserver.observe(img));
        }

        // Debounce scroll events
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                // Handle scroll-based animations or effects
                handleScrollEffects();
            }, 16); // ~60fps
        });

        // Preload critical resources
        const criticalLinks = document.querySelectorAll('link[rel="preload"]');
        criticalLinks.forEach(link => {
            if (link.href && !link.href.startsWith('data:')) {
                const preloadLink = document.createElement('link');
                preloadLink.rel = 'preload';
                preloadLink.href = link.href;
                preloadLink.as = link.as || 'style';
                document.head.appendChild(preloadLink);
            }
        });
    }

    function handleScrollEffects() {
        // Add scroll-based effects here
        const scrollY = window.scrollY;
        
        // Parallax effect for hero sections
        const heroElements = document.querySelectorAll('.hero, .page-header');
        heroElements.forEach(hero => {
            const speed = 0.5;
            hero.style.transform = `translateY(${scrollY * speed}px)`;
        });

        // Show/hide scroll to top button
        const scrollTopBtn = document.querySelector('.scroll-to-top');
        if (scrollTopBtn) {
            if (scrollY > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        }
    }

    function initErrorHandling() {
        // Global error handler
        window.addEventListener('error', function(e) {
            console.error('Global error:', e.error);
            
            // Show user-friendly error message
            showErrorMessage('حدث خطأ غير متوقع. يرجى إعادة تحميل الصفحة.');
        });

        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled promise rejection:', e.reason);
            showErrorMessage('حدث خطأ في الشبكة. يرجى المحاولة مرة أخرى.');
        });

        // Handle network errors
        window.addEventListener('online', function() {
            hideErrorMessage();
            showSuccessMessage('تم استعادة الاتصال بالإنترنت.');
        });

        window.addEventListener('offline', function() {
            showErrorMessage('لا يوجد اتصال بالإنترنت. يرجى التحقق من الشبكة.');
        });
    }

    function showErrorMessage(message) {
        const existingError = document.querySelector('.error-message');
        if (existingError) {
            existingError.remove();
        }

        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.innerHTML = `
            <div class="error-content">
                <span class="error-icon">⚠️</span>
                <span class="error-text">${message}</span>
                <button class="error-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f44336;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(errorDiv);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentElement) {
                errorDiv.remove();
            }
        }, 5000);
    }

    function showSuccessMessage(message) {
        const successDiv = document.createElement('div');
        successDiv.className = 'success-message';
        successDiv.innerHTML = `
            <div class="success-content">
                <span class="success-icon">✅</span>
                <span class="success-text">${message}</span>
            </div>
        `;
        
        successDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4caf50;
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            max-width: 300px;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(successDiv);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (successDiv.parentElement) {
                successDiv.remove();
            }
        }, 3000);
    }

    function hideErrorMessage() {
        const errorMessage = document.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loading::after {
            content: 'جاري التحميل...';
            font-size: 18px;
            color: var(--accent-color, #C9A227);
        }

        .focused {
            outline: 2px solid var(--accent-color, #C9A227) !important;
            outline-offset: 2px !important;
        }

        .error-content, .success-content {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-close {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            margin-left: auto;
        }

        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--accent-color, #C9A227);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .scroll-to-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-to-top:hover {
            transform: scale(1.1);
        }

        @media (max-width: 768px) {
            .error-message, .success-message {
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }
    `;
    
    document.head.appendChild(style);

    // Add scroll to top button
    if (!document.querySelector('.scroll-to-top')) {
        const scrollTopBtn = document.createElement('button');
        scrollTopBtn.className = 'scroll-to-top';
        scrollTopBtn.innerHTML = '↑';
        scrollTopBtn.setAttribute('aria-label', 'Scroll to top');
        
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        document.body.appendChild(scrollTopBtn);
    }

})();