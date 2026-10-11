/**
 * Employee HRIS & Leave Management System
 * Landing Page Interactive Script (landing.js)
 */

document.addEventListener('DOMContentLoaded', () => {
    // ======================================================================
    // 1. Mobile Drawer Navigation Toggle
    // ======================================================================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileDrawer = document.getElementById('mobileDrawer');

    if (mobileMenuBtn && mobileDrawer) {
        mobileMenuBtn.addEventListener('click', () => {
            const isOpen = mobileDrawer.classList.toggle('is-open');
            mobileMenuBtn.setAttribute('aria-expanded', isOpen);
            mobileMenuBtn.innerHTML = isOpen ? '✕' : '☰';
        });

        // Close drawer when clicking any link
        mobileDrawer.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileDrawer.classList.remove('is-open');
                mobileMenuBtn.setAttribute('aria-expanded', 'false');
                mobileMenuBtn.innerHTML = '☰';
            });
        });
    }

    // ======================================================================
    // 2. Active Navigation Highlight via Intersection Observer
    // ======================================================================
    const navLinks = document.querySelectorAll('.site-nav-links .nav-link');
    const sections = document.querySelectorAll('section[id]');

    if ('IntersectionObserver' in window && sections.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const currentId = entry.target.getAttribute('id');
                    navLinks.forEach(link => {
                        const href = link.getAttribute('href');
                        if (href === `#${currentId}`) {
                            link.classList.add('active');
                        } else {
                            link.classList.remove('active');
                        }
                    });
                }
            });
        }, {
            threshold: 0.35
        });

        sections.forEach(sec => observer.observe(sec));
    }

    // ======================================================================
    // 3. Smooth Scrolling with Offset for Sticky Header
    // ======================================================================
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#' || targetId === '') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                const headerOffset = 80;
                const elementPosition = targetElement.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    // ======================================================================
    // 4. Illustrative Mockup Interactivity (Clearly Labeled Demonstration)
    // ======================================================================
    const mockNavItems = document.querySelectorAll('.mockup-nav-item');
    mockNavItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            mockNavItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            
            const label = item.textContent.trim();
            const titleEl = document.querySelector('.mockup-title');
            if (titleEl) {
                titleEl.textContent = `HR Portal / ${label} (Demo Preview)`;
            }
        });
    });
});
