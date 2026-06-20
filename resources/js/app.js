import './bootstrap';

// Landia template libs — installed via npm (KINGS5 approach), not vendor files.
import AOS from 'aos';
import 'aos/dist/aos.css';
import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.min.css';
import Swiper from 'swiper/bundle';
import 'swiper/css/bundle';
import PureCounter from '@srexi/purecounterjs';

/**
 * Landia (BootstrapMade) behaviors — adapted from the template's main.js.
 * Null-guarded so pages missing a given element don't throw.
 */
(function () {
    'use strict';

    // Apply .scrolled class to the body as the page is scrolled down
    function toggleScrolled() {
        const body = document.querySelector('body');
        const header = document.querySelector('#header');
        if (!header) return;
        if (!header.classList.contains('scroll-up-sticky') && !header.classList.contains('sticky-top') && !header.classList.contains('fixed-top')) return;
        window.scrollY > 100 ? body.classList.add('scrolled') : body.classList.remove('scrolled');
    }
    document.addEventListener('scroll', toggleScrolled);
    window.addEventListener('load', toggleScrolled);

    // Mobile nav toggle
    const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');

    function mobileNavToogle() {
        document.querySelector('body').classList.toggle('mobile-nav-active');
        mobileNavToggleBtn.classList.toggle('bi-list');
        mobileNavToggleBtn.classList.toggle('bi-x');
    }
    if (mobileNavToggleBtn) {
        mobileNavToggleBtn.addEventListener('click', mobileNavToogle);
    }

    // Hide mobile nav on same-page/hash links
    document.querySelectorAll('#navmenu a').forEach((navmenu) => {
        navmenu.addEventListener('click', () => {
            if (document.querySelector('.mobile-nav-active') && !navmenu.classList.contains('toggle-dropdown')) {
                mobileNavToogle();
            }
        });
    });

    // Toggle mobile nav dropdowns
    document.querySelectorAll('.navmenu .toggle-dropdown').forEach((navmenu) => {
        navmenu.addEventListener('click', function (e) {
            e.preventDefault();
            this.parentNode.classList.toggle('active');
            this.parentNode.nextElementSibling.classList.toggle('dropdown-active');
            e.stopImmediatePropagation();
        });
    });

    // Scroll top button
    const scrollTop = document.querySelector('.scroll-top');

    function toggleScrollTop() {
        if (scrollTop) {
            window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
        }
    }
    if (scrollTop) {
        scrollTop.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
    window.addEventListener('load', toggleScrollTop);
    document.addEventListener('scroll', toggleScrollTop);

    // Animation on scroll
    function aosInit() {
        AOS.init({ duration: 600, easing: 'ease-in-out', once: true, mirror: false });
    }
    window.addEventListener('load', aosInit);

    // GLightbox
    GLightbox({ selector: '.glightbox' });

    // Pure Counter
    new PureCounter();

    // Pricing toggle
    document.querySelectorAll('.pricing-toggle-container').forEach(function (container) {
        const pricingSwitch = container.querySelector('.pricing-toggle input[type="checkbox"]');
        const monthlyText = container.querySelector('.monthly');
        const yearlyText = container.querySelector('.yearly');
        if (!pricingSwitch) return;

        pricingSwitch.addEventListener('change', function () {
            const pricingItems = container.querySelectorAll('.pricing-item');
            if (this.checked) {
                monthlyText.classList.remove('active');
                yearlyText.classList.add('active');
                pricingItems.forEach((item) => item.classList.add('yearly-active'));
            } else {
                monthlyText.classList.add('active');
                yearlyText.classList.remove('active');
                pricingItems.forEach((item) => item.classList.remove('yearly-active'));
            }
        });
    });

    // FAQ toggle
    document.querySelectorAll('.faq-item h3, .faq-item .faq-toggle, .faq-item .faq-header').forEach((faqItem) => {
        faqItem.addEventListener('click', () => {
            faqItem.parentNode.classList.toggle('faq-active');
        });
    });

    // Swiper sliders
    function initSwiper() {
        document.querySelectorAll('.init-swiper').forEach(function (swiperElement) {
            const cfgEl = swiperElement.querySelector('.swiper-config');
            if (!cfgEl) return;
            const config = JSON.parse(cfgEl.innerHTML.trim());
            new Swiper(swiperElement, config);
        });
    }
    window.addEventListener('load', initSwiper);

    // Correct scroll position for hash links on load
    window.addEventListener('load', function () {
        if (window.location.hash) {
            const section = document.querySelector(window.location.hash);
            if (section) {
                setTimeout(() => {
                    const scrollMarginTop = getComputedStyle(section).scrollMarginTop;
                    window.scrollTo({ top: section.offsetTop - parseInt(scrollMarginTop), behavior: 'smooth' });
                }, 100);
            }
        }
    });

    // Navmenu scrollspy
    const navmenulinks = document.querySelectorAll('.navmenu a');

    function navmenuScrollspy() {
        navmenulinks.forEach((navmenulink) => {
            if (!navmenulink.hash) return;
            const section = document.querySelector(navmenulink.hash);
            if (!section) return;
            const position = window.scrollY + 200;
            if (position >= section.offsetTop && position <= section.offsetTop + section.offsetHeight) {
                document.querySelectorAll('.navmenu a.active').forEach((link) => link.classList.remove('active'));
                navmenulink.classList.add('active');
            } else {
                navmenulink.classList.remove('active');
            }
        });
    }
    window.addEventListener('load', navmenuScrollspy);
    document.addEventListener('scroll', navmenuScrollspy);
})();
