/**
 * DVE System — Scroll To Top Floating Button
 * Auto-injects a responsive, elegant scroll-to-top button on all pages.
 */
(function() {
    'use strict';

    function initScrollToTop() {
        if (document.getElementById('btn-scroll-to-top')) return;

        // Create Scroll Button Element
        const btn = document.createElement('button');
        btn.id = 'btn-scroll-to-top';
        btn.type = 'button';
        btn.setAttribute('aria-label', 'เลื่อนขึ้นบนสุด');
        btn.setAttribute('title', 'กลับสู่ด้านบน');
        btn.innerHTML = '<i class="bi bi-arrow-up-short" style="font-size: 1.85rem; line-height: 1; display: block;"></i>';

        document.body.appendChild(btn);

        // Toggle visibility on scroll
        const toggleVisibility = () => {
            if (window.scrollY > 220) {
                btn.classList.add('show');
            } else {
                btn.classList.remove('show');
            }
        };

        window.addEventListener('scroll', toggleVisibility, { passive: true });
        toggleVisibility();

        // Smooth Scroll to Top on Click
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initScrollToTop);
    } else {
        initScrollToTop();
    }
})();
