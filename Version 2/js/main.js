/**
 * Finora Investment Studio — Optimized Static JS
 * Vanilla JS (no jQuery) — all interactions from original Divi export.
 */

document.addEventListener('DOMContentLoaded', () => {
    const locale = window.location.pathname.replace(/\\/g, '/').includes('/en/')
        || document.documentElement.lang.toLowerCase().startsWith('en')
        ? 'en'
        : 'de';
    const copy = {
        de: {
            menuOpen: 'Menü öffnen',
            menuClose: 'Menü schließen',
            more: 'Mehr',
            less: 'Weniger',
            card: 'Karte',
            timelineMore: 'Mehr lesen',
            contactSuccess: 'Vielen Dank für deine Nachricht! Wir melden uns in Kürze bei dir.',
            backToTop: 'Nach oben',
        },
        en: {
            menuOpen: 'Open menu',
            menuClose: 'Close menu',
            more: 'More',
            less: 'Less',
            card: 'Card',
            timelineMore: 'Read more',
            contactSuccess: "Thanks for your message! We'll get back to you shortly.",
            backToTop: 'Back to top',
        },
    };
    const t = copy[locale];
    const wpformsCopy = locale === 'en'
        ? {
            nameLabel: 'Name',
            firstNamePlaceholder: 'First name',
            lastNamePlaceholder: 'Last name',
            emailLabel: 'Email address',
            emailPlaceholder: 'your@email.com',
            messageLabel: 'Your message',
            messagePlaceholder: 'What is it about? What is on your mind right now?',
            submitLabel: 'Send message',
            consentPrefix: 'I have read the ',
            consentLinkLabel: 'privacy policy',
            consentSuffix: ' and agree.',
        }
        : {
            nameLabel: 'Name',
            firstNamePlaceholder: 'Vorname',
            lastNamePlaceholder: 'Nachname',
            emailLabel: 'E-Mail-Adresse',
            emailPlaceholder: 'deine@email.de',
            messageLabel: 'Deine Nachricht',
            messagePlaceholder: 'Worum geht es? Was beschaeftigt dich gerade?',
            submitLabel: 'Nachricht senden',
            consentPrefix: 'Ich habe die ',
            consentLinkLabel: 'Datenschutzbestimmungen',
            consentSuffix: ' gelesen und bin einverstanden.',
        };

    initKontaktWpFormsPresentation();

    function setFieldLabelText(label, text) {
        if (!label || !text) return;

        const nextText = label.querySelector('.wpforms-required-label') ? text + ' ' : text;
        const textNodes = Array.from(label.childNodes).filter((child) => child.nodeType === Node.TEXT_NODE);
        if (textNodes.length) {
            if (textNodes[0].textContent !== nextText) {
                textNodes[0].textContent = nextText;
            }
            textNodes.slice(1).forEach((node) => node.remove());
            return;
        }

        label.insertBefore(document.createTextNode(nextText), label.firstChild);
    }

    function setInlineLinkText(node, prefix, linkText, suffix) {
        if (!node) return;

        const link = node.querySelector('a');
        if (!link) return;

        const clonedLink = link.cloneNode(true);
        if (linkText) {
            clonedLink.textContent = linkText;
        }

        node.replaceChildren(
            document.createTextNode(prefix || ''),
            clonedLink,
            document.createTextNode(suffix || '')
        );
    }

    function initKontaktWpFormsPresentation() {
        const form = document.querySelector('.page-kontakt .wpforms-container .wpforms-form');
        if (!form) return;

        form.querySelectorAll('.wpforms-field').forEach(function (field) {
            const hiddenControl = field.querySelector('input[tabindex="-1"][aria-hidden="true"], textarea[tabindex="-1"][aria-hidden="true"], select[tabindex="-1"][aria-hidden="true"]');
            const hiddenLabel = field.querySelector('.wpforms-field-label[aria-hidden="true"]');
            if (hiddenControl && hiddenLabel) {
                field.hidden = true;
            }
        });

        const nameLabel = form.querySelector('.wpforms-field-name > .wpforms-field-label');
        const firstName = form.querySelector('.wpforms-field-name-first');
        const lastName = form.querySelector('.wpforms-field-name-last');
        const emailLabel = form.querySelector('.wpforms-field-email > .wpforms-field-label');
        const email = form.querySelector('.wpforms-field-email input[type="email"]');
        const messageLabel = form.querySelector('.wpforms-field-textarea > .wpforms-field-label');
        const message = form.querySelector('.wpforms-field-textarea textarea');
        const description = form.querySelector('.wpforms-field-textarea .wpforms-field-description');
        const consentLabel = form.querySelector('.wpforms-field-checkbox .wpforms-field-label-inline');
        const submit = form.querySelector('.wpforms-submit');

        setFieldLabelText(nameLabel, wpformsCopy.nameLabel);
        setFieldLabelText(emailLabel, wpformsCopy.emailLabel);
        setFieldLabelText(messageLabel, wpformsCopy.messageLabel);

        if (firstName && wpformsCopy.firstNamePlaceholder) {
            firstName.setAttribute('placeholder', wpformsCopy.firstNamePlaceholder);
        }
        if (lastName && wpformsCopy.lastNamePlaceholder) {
            lastName.setAttribute('placeholder', wpformsCopy.lastNamePlaceholder);
        }
        if (email && wpformsCopy.emailPlaceholder) {
            email.setAttribute('placeholder', wpformsCopy.emailPlaceholder);
        }
        if (description && wpformsCopy.messagePlaceholder) {
            description.textContent = wpformsCopy.messagePlaceholder;
        }
        if (message && (wpformsCopy.messagePlaceholder || description)) {
            message.setAttribute('placeholder', wpformsCopy.messagePlaceholder || description.textContent.trim());
        }
        if (submit && wpformsCopy.submitLabel) {
            submit.textContent = wpformsCopy.submitLabel;
        }
        setInlineLinkText(
            consentLabel,
            wpformsCopy.consentPrefix,
            wpformsCopy.consentLinkLabel,
            wpformsCopy.consentSuffix
        );
    }

    // =========================================================
    // 0. HERO SLIDER (Startseite - Leistungen-Style, 5s Auto, Pfeile)
    // =========================================================
    const heroSlider = document.getElementById('hero-slider');
    if (heroSlider) {
        const track = heroSlider.querySelector('.hero-slider-track');
        const slides = Array.from(heroSlider.querySelectorAll('.hero-slide'));
        const prevBtn = heroSlider.querySelector('.hero-slider-prev');
        const nextBtn = heroSlider.querySelector('.hero-slider-next');
        const AUTOPLAY_MS = 5000;
        const allowAutoplay = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let currentSlideIndex = 0;
        let autoplayTimer = null;
        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;
        const SWIPE_THRESHOLD = 42;

        function clearAutoplay() {
            if (autoplayTimer) {
                clearTimeout(autoplayTimer);
                autoplayTimer = null;
            }
        }

        function queueAutoplay(delay) {
            clearAutoplay();
            if (!allowAutoplay || slides.length < 2) return;
            autoplayTimer = window.setTimeout(function () {
                nextSlide();
            }, typeof delay === 'number' ? delay : AUTOPLAY_MS);
        }

        function goToSlide(index) {
            const len = slides.length;
            if (len === 0) return;
            currentSlideIndex = (index + len) % len;
            slides.forEach(function (slide, i) {
                slide.classList.toggle('is-active', i === currentSlideIndex);
            });
            queueAutoplay();
        }

        function nextSlide() {
            goToSlide(currentSlideIndex + 1);
        }

        function prevSlide() {
            goToSlide(currentSlideIndex - 1);
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                clearAutoplay();
                prevSlide();
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                clearAutoplay();
                nextSlide();
            });
        }
        if (allowAutoplay) {
            heroSlider.addEventListener('mouseenter', clearAutoplay);
            heroSlider.addEventListener('mouseleave', function () {
                queueAutoplay();
            });
        }

        heroSlider.addEventListener('touchstart', function (event) {
            if (!event.touches || !event.touches.length) return;
            const touch = event.touches[0];
            touchStartX = touch.clientX;
            touchStartY = touch.clientY;
            touchEndX = touch.clientX;
            touchEndY = touch.clientY;
            clearAutoplay();
        }, { passive: true });

        heroSlider.addEventListener('touchmove', function (event) {
            if (!event.touches || !event.touches.length) return;
            const touch = event.touches[0];
            touchEndX = touch.clientX;
            touchEndY = touch.clientY;
        }, { passive: true });

        heroSlider.addEventListener('touchend', function () {
            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;
            if (Math.abs(deltaX) > SWIPE_THRESHOLD && Math.abs(deltaX) > Math.abs(deltaY)) {
                if (deltaX < 0) {
                    nextSlide();
                } else {
                    prevSlide();
                }
                return;
            }
            queueAutoplay(AUTOPLAY_MS + 1000);
        }, { passive: true });

        heroSlider.addEventListener('touchcancel', function () {
            queueAutoplay(AUTOPLAY_MS);
        }, { passive: true });

        if (track) {
            track.style.touchAction = 'pan-y';
        }

        if (slides.length) {
            slides.forEach(function (s) { s.classList.remove('is-active'); });
            slides[0].classList.add('is-active');
        }
        queueAutoplay();
    }

    // =========================================================
    // 1. MOBILE MENU TOGGLE
    // =========================================================
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    if (menuToggle && mobileMenu) {
        const mobileMenuLinks = mobileMenu.querySelectorAll('a');

        function closeMenu() {
            mobileMenu.classList.remove('is-open');
            menuToggle.classList.remove('is-active');
            document.body.classList.remove('has-mobile-menu-open');
            menuToggle.setAttribute('aria-expanded', 'false');
            menuToggle.setAttribute('aria-label', t.menuOpen);
            mobileMenu.setAttribute('aria-hidden', 'true');
        }

        function openMenu() {
            mobileMenu.classList.add('is-open');
            menuToggle.classList.add('is-active');
            document.body.classList.add('has-mobile-menu-open');
            menuToggle.setAttribute('aria-expanded', 'true');
            menuToggle.setAttribute('aria-label', t.menuClose);
            mobileMenu.setAttribute('aria-hidden', 'false');
        }

        function toggleMenu() {
            if (mobileMenu.classList.contains('is-open')) {
                closeMenu();
                return;
            }
            openMenu();
        }

        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', t.menuOpen);
        mobileMenu.setAttribute('aria-hidden', 'true');

        menuToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            toggleMenu();
        });

        mobileMenu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        mobileMenuLinks.forEach((link) => {
            link.addEventListener('click', () => {
                closeMenu();
            });
        });

        document.addEventListener('click', (event) => {
            if (!mobileMenu.classList.contains('is-open')) return;
            if (event.target.closest('.site-header')) return;
            closeMenu();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 980) {
                closeMenu();
            }
        });
    }

    // =========================================================
    // 1A. LANGUAGE SWITCHER DROPDOWN
    // =========================================================
    const langBtn = document.querySelector('.header-lang-btn');
    const langDropdown = document.querySelector('.header-lang-dropdown');
    if (langBtn && langDropdown) {
        langBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !langDropdown.hidden;
            langDropdown.hidden = isOpen;
            langBtn.setAttribute('aria-expanded', !isOpen);
        });
        document.addEventListener('click', () => {
            langDropdown.hidden = true;
            langBtn.setAttribute('aria-expanded', 'false');
        });
        langDropdown.addEventListener('click', (e) => e.stopPropagation());
    }

    // =========================================================
    // 1B. HEADER SCROLL EFFECT + SUBTLE HERO PARALLAX
    // =========================================================
    const header = document.querySelector('.site-header');
    const forceScrolledHeader = header && (header.hasAttribute('data-force-scrolled-header') || document.body.classList.contains('header-scrolled'));
    const heroSection = document.querySelector('.hero');
    const heroVideo = document.querySelector('.hero-video');
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const isCoarsePointer = window.matchMedia('(hover: none), (pointer: coarse)').matches;
    const enableDecorativeMotion = !prefersReducedMotion.matches && !isCoarsePointer;
    const howSection = document.querySelector('.how-it-works');
    if (header) {
        const scrollThreshold = 50;
        let ticking = false;

        function runScrollFrame() {
            const y = window.scrollY || document.documentElement.scrollTop;
            const compact = forceScrolledHeader || y > scrollThreshold;
            const isMobileHeader = window.matchMedia('(max-width: 767px)').matches;
            const isTabletHeader = window.matchMedia('(max-width: 980px)').matches;
            const expandedHeaderHeight = isMobileHeader ? '86px' : (isTabletHeader ? '96px' : '112px');
            const compactHeaderHeight = isMobileHeader ? '74px' : (isTabletHeader ? '84px' : '92px');
            header.classList.toggle('is-scrolled', compact);
            document.documentElement.style.setProperty(
                '--header-bar-height',
                compact ? compactHeaderHeight : expandedHeaderHeight
            );

            if (enableDecorativeMotion && heroSection && y < window.innerHeight) {
                const parallaxY = y * 0.3;
                const parallaxOpacity = 1 - (y / window.innerHeight) * 0.4;
                heroSection.style.setProperty('--parallax-y', parallaxY + 'px');
                const heroText = heroSection.querySelector('.hero-text');
                if (heroText) {
                    heroText.style.transform = 'translateY(' + (parallaxY * 0.5) + 'px)';
                    heroText.style.opacity = Math.max(parallaxOpacity, 0);
                }
            }
            if (enableDecorativeMotion && howSection) {
                const rect = howSection.getBoundingClientRect();
                const parallaxY = rect.top * 0.35;
                howSection.style.setProperty('--how-parallax-y', parallaxY + 'px');
            }
            ticking = false;
        }

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(runScrollFrame);
                ticking = true;
            }
        }, { passive: true });

        window.addEventListener('resize', runScrollFrame);

        runScrollFrame();

        if (enableDecorativeMotion && howSection) {
            const setHowParallax = () => {
                const rect = howSection.getBoundingClientRect();
                howSection.style.setProperty('--how-parallax-y', (rect.top * 0.35) + 'px');
            };
            setHowParallax();
            window.addEventListener('resize', setHowParallax);
        } else {
            if (heroSection) {
                heroSection.style.removeProperty('--parallax-y');
                const heroText = heroSection.querySelector('.hero-text');
                if (heroText) {
                    heroText.style.removeProperty('transform');
                    heroText.style.removeProperty('opacity');
                }
            }
            if (howSection) {
                howSection.style.removeProperty('--how-parallax-y');
            }
        }
    }

    // =========================================================
    // 2. SCROLL ENTRANCE ANIMATIONS (IntersectionObserver)
    // =========================================================
    const allAnim = document.querySelectorAll('.anim');
    const animEls = Array.from(allAnim).filter(el => !(el.classList.contains('pillar-card') && el.closest('.pillars-grid')));

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const delay = entry.target.style.animationDelay;
                    const extraDelay = entry.target.dataset.stagger;
                    if (extraDelay) {
                        entry.target.style.animationDelay = extraDelay + 'ms';
                    }
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        animEls.forEach(el => observer.observe(el));

        // Drei Säulen: kein Observer - Scroll-Effekt über scrollPillarCards()
    } else {
        document.querySelectorAll('.anim').forEach(el => el.classList.add('is-visible'));
    }

    // Stagger für andere Grids (testimonials, how-steps)
    document.querySelectorAll('.testimonials-grid, .how-grid').forEach(grid => {
        const children = grid.children;
        for (let i = 0; i < children.length; i++) {
            if (children[i].classList.contains('anim')) {
                children[i].style.animationDelay = (i * 150) + 'ms';
            }
        }
    });

    // =========================================================
    // 2b. DREI SÄULEN: Scroll-Effekt (0% = übereinander, 50% Scrolltiefe = Endposition)
    // =========================================================
    const PILLAR_STACK_OFFSET = 220;
    let pillarTicking = false;

    function updatePillarCards() {
        const section = document.querySelector('.pillars');
        if (!section) return;
        const grid = section.querySelector('.pillars-grid');
        if (!grid) return;
        const cards = grid.querySelectorAll('.pillar-card');
        if (cards.length < 3) return;

        if (window.innerWidth <= 980) {
            cards.forEach((card) => {
                card.style.transform = 'none';
            });
            return;
        }

        const rect = section.getBoundingClientRect();
        const vh = window.innerHeight;
        const top = rect.top;

        let progress = 0;
        if (top <= vh * 0.2) {
            progress = 1;
        } else if (top < vh) {
            progress = 1 - (top - vh * 0.2) / (vh * 0.8);
        }

        const t = 1 - progress;
        const left = cards[0];
        const center = cards[1];
        const right = cards[2];
        if (left) left.style.transform = `translate(${t * PILLAR_STACK_OFFSET}px, 0)`;
        if (center) center.style.transform = 'translate(0, 0)';
        if (right) right.style.transform = `translate(${-t * PILLAR_STACK_OFFSET}px, 0)`;
    }

    function onPillarScroll() {
        if (pillarTicking) return;
        pillarTicking = true;
        requestAnimationFrame(() => {
            updatePillarCards();
            pillarTicking = false;
        });
    }

    const pillarsSection = document.querySelector('.pillars');
    if (pillarsSection) {
        updatePillarCards();
        window.addEventListener('scroll', onPillarScroll, { passive: true });
        window.addEventListener('resize', onPillarScroll);
    }

    // =========================================================
    // 3. FAQ ACCORDION
    // =========================================================
    const accordionItems = document.querySelectorAll('.accordion-item');
    accordionItems.forEach(item => {
        const title = item.querySelector('.accordion-title');
        if (!title) return;

        title.addEventListener('click', () => {
            const isOpen = item.classList.contains('is-open');

            // Close all others (optional, but typical for accordion)
            accordionItems.forEach(i => i.classList.remove('is-open'));

            // Toggle current
            if (!isOpen) {
                item.classList.add('is-open');
            }
        });
    });

    // =========================================================
    // 4. TESTIMONIAL EXPAND/COLLAPSE (nur die geklickte Kachel öffnen)
    // Inline-Styles setzen, damit garantiert nur eine Karte den vollen Text zeigt.
    // =========================================================
    /* Zugeklappt: 7 Zeilen (11.9em); Aufklappen: Kachel öffnet nach unten, voller Text */
    function setTestimonialTextStyle(el, expanded) {
        var s = el.style;
        if (expanded) {
            s.overflow = 'visible';
            s.maxHeight = (el.scrollHeight + 24) + 'px';
            s.webkitLineClamp = '';
            s.display = 'block';
        } else {
            s.overflow = 'hidden';
            s.maxHeight = '11.9em';
            s.webkitLineClamp = '';
            s.display = 'block';
        }
    }

    function isForcedExpandedTestimonial(card) {
        return !!card && card.getAttribute('data-force-expanded') === 'true';
    }

    function setTestimonialButtonLabel(btn, expanded) {
        if (!btn) return;
        btn.innerHTML = (expanded ? t.less : t.more) + ' <span class="lw-arrow">' + (expanded ? '&uarr;' : '&darr;') + '</span>';
    }

    function syncTestimonialCard(card, expanded) {
        if (!card) return;
        var textEl = card.querySelector('.testimonial-text');
        if (!textEl) return;

        var btn = card.querySelector('.lw-more-btn');
        var shouldExpand = isForcedExpandedTestimonial(card) || expanded;

        if (shouldExpand) {
            card.setAttribute('data-expanded', 'true');
        } else {
            card.removeAttribute('data-expanded');
        }

        setTestimonialTextStyle(textEl, shouldExpand);

        if (!btn) return;
        if (isForcedExpandedTestimonial(card)) {
            btn.hidden = true;
            btn.setAttribute('aria-hidden', 'true');
            return;
        }

        btn.hidden = false;
        btn.removeAttribute('aria-hidden');
        setTestimonialButtonLabel(btn, shouldExpand);
    }

    // Beim Start alle Zitate per Inline-Style zuklappen (einheitlicher Ausgangszustand)
    (function () {
        var cards = document.querySelectorAll('.testimonials .testimonial-card');
        for (var k = 0; k < cards.length; k++) {
            syncTestimonialCard(cards[k], false);
        }
    })();

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest('.testimonials .lw-more-btn');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        var card = btn.closest('.testimonial-card');
        if (!card) return;
        var section = card.closest('.testimonials');
        if (!section) return;

        if (isForcedExpandedTestimonial(card)) return;

        var textEl = card.querySelector('.testimonial-text');
        if (!textEl) return;
        var wasExpanded = card.getAttribute('data-expanded') === 'true';

        // 1. Alle Zitate in dieser Sektion zuklappen (per Inline-Style + data-expanded entfernen)
        var allCards = section.querySelectorAll('.testimonial-card');
        for (var c = 0; c < allCards.length; c++) {
            syncTestimonialCard(allCards[c], false);
        }

        // 2. Nur diese eine Karte aufklappen - Karte wächst mit, Footer bleibt unter dem Text
        if (!wasExpanded) {
            syncTestimonialCard(card, true);
        }
    }, true);

    // =========================================================
    // 4B. TESTIMONIALS CAROUSEL (Drag, Swipe, Autoplay — ohne Punkt-Navigation)
    // =========================================================
    document.querySelectorAll('.testimonials').forEach(function (section) {
        var track = section.querySelector('.testimonials-track');
        if (!track) return;
        var inner = track.querySelector('.testimonials-track-inner');
        if (!inner) return;

        var grids = inner.querySelectorAll('.testimonials-grid');
        for (var g = 1; g < grids.length; g++) grids[g].remove();

        var firstGrid = inner.querySelector('.testimonials-grid');
        if (!firstGrid) return;
        var cards = Array.from(firstGrid.querySelectorAll('.testimonial-card'));
        if (!cards.length) return;

        var dots = [];

        function getActiveIndex() {
            var trackRect = track.getBoundingClientRect();
            var center = trackRect.left + trackRect.width / 2;
            var closest = 0;
            var minDist = Infinity;
            cards.forEach(function (card, i) {
                var r = card.getBoundingClientRect();
                var cardCenter = r.left + r.width / 2;
                var dist = Math.abs(cardCenter - center);
                if (dist < minDist) { minDist = dist; closest = i; }
            });
            return closest;
        }

        function updateDots() {
            var active = getActiveIndex();
            dots.forEach(function (d, i) {
                d.classList.toggle('is-active', i === active);
            });
        }

        function scrollToCard(idx) {
            var card = cards[idx];
            if (!card) return;
            var cardCenter = card.offsetLeft + card.offsetWidth / 2;
            var trackCenter = track.offsetWidth / 2;
            track.scrollTo({ left: cardCenter - trackCenter, behavior: 'smooth' });
        }

        var scrollTimer = null;
        track.addEventListener('scroll', function () {
            if (scrollTimer) cancelAnimationFrame(scrollTimer);
            scrollTimer = requestAnimationFrame(updateDots);
        }, { passive: true });

        var isDragging = false, startX = 0, scrollLeft = 0;

        track.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            isDragging = true;
            startX = e.pageX - track.offsetLeft;
            scrollLeft = track.scrollLeft;
            track.classList.add('is-grabbing');
        });

        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            e.preventDefault();
            var x = e.pageX - track.offsetLeft;
            track.scrollLeft = scrollLeft - (x - startX);
        });

        document.addEventListener('mouseup', function () {
            if (!isDragging) return;
            isDragging = false;
            track.classList.remove('is-grabbing');
            track.style.scrollSnapType = 'x mandatory';
            var idx = getActiveIndex();
            scrollToCard(idx);
        });

        updateDots();
        scrollToCard(0);

        var autoPlayInterval = 5000;
        var autoTimer = null;

        function startAutoPlay() {
            stopAutoPlay();
            autoTimer = setInterval(function () {
                var current = getActiveIndex();
                var next = (current + 1) % cards.length;
                scrollToCard(next);
            }, autoPlayInterval);
        }

        function stopAutoPlay() {
            if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
        }

        var disableAutoPlay = window.matchMedia('(hover: none), (pointer: coarse)').matches
            || window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!disableAutoPlay) {
            track.addEventListener('mouseenter', stopAutoPlay);
            track.addEventListener('mouseleave', startAutoPlay);
            track.addEventListener('touchstart', stopAutoPlay, { passive: true });
            track.addEventListener('touchend', function () {
                setTimeout(startAutoPlay, 3000);
            }, { passive: true });

            startAutoPlay();
        }
    });

    // =========================================================
    // 4C. TIMELINE CARD COLLAPSE / EXPAND
    // =========================================================
    document.querySelectorAll('.timeline.timeline--horizontal .timeline-item__card').forEach(function (card) {
        var ul = card.querySelector('ul');
        if (!ul) return;

        var detailNodes = [];
        var foundUl = false;
        var children = Array.from(card.childNodes);
        children.forEach(function (node) {
            if (node === ul) foundUl = true;
            if (foundUl && node.nodeType === 1) detailNodes.push(node);
        });

        if (!detailNodes.length) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'timeline-card__details';
        card.insertBefore(wrapper, detailNodes[0]);
        detailNodes.forEach(function (n) { wrapper.appendChild(n); });

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'timeline-card__toggle';
        btn.innerHTML = '<span class="timeline-card__toggle-label">' + t.timelineMore + '</span> <span class="timeline-card__toggle-arrow">\u25BC</span>';
        btn.setAttribute('aria-expanded', 'false');
        card.appendChild(btn);

        btn.addEventListener('click', function () {
            var isOpen = card.classList.contains('is-expanded');
            if (isOpen) {
                wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
                requestAnimationFrame(function () {
                    wrapper.style.maxHeight = '0';
                });
                card.classList.remove('is-expanded');
                btn.querySelector('.timeline-card__toggle-label').textContent = t.timelineMore;
                btn.setAttribute('aria-expanded', 'false');
            } else {
                wrapper.style.maxHeight = wrapper.scrollHeight + 'px';
                card.classList.add('is-expanded');
                btn.querySelector('.timeline-card__toggle-label').textContent = t.less;
                btn.setAttribute('aria-expanded', 'true');
                wrapper.addEventListener('transitionend', function handler() {
                    if (card.classList.contains('is-expanded')) {
                        wrapper.style.maxHeight = 'none';
                    }
                    wrapper.removeEventListener('transitionend', handler);
                });
            }
        });
    });

    // =========================================================
    // 5. FINORA SERVICE SLIDER
    // =========================================================
    const root = document.querySelector('[data-finora]');
    if (root) {
        const img = root.querySelector('#fs-img');
        const cardT = root.querySelector('#fs-title');
        const cardB = root.querySelector('#fs-body');
        const items = Array.from(root.querySelectorAll('.fs-item'));
        const dotsWrap = root.querySelector('.fs-dots');
        const prevBtn = root.querySelector('.fs-prev');
        const nextBtn = root.querySelector('.fs-next');
        const currentEl = root.querySelector('#fs-current');
        const totalEl = root.querySelector('#fs-total');

        let currentIndex = 0;
        if (!items.length) return;

        // Preload images
        items.forEach(btn => {
            const u = btn.dataset.img;
            if (u) { const p = new Image(); p.src = u; }
        });

        // Mobile setup
        if (totalEl) totalEl.textContent = items.length.toString();
        if (dotsWrap) {
            items.forEach((btn, idx) => {
                const dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'fs-dot';
                dot.dataset.index = idx.toString();
                dotsWrap.appendChild(dot);
            });
        }

        const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('.fs-dot')) : [];

        function setIndicators(index) {
            if (currentEl) currentEl.textContent = (index + 1).toString();
            if (dots.length) {
                dots.forEach((d, i) => d.classList.toggle('is-active', i === index));
            }
        }

        function activate(btn) {
            items.forEach(b => b.classList.toggle('is-active', b === btn));
            const index = items.indexOf(btn);
            if (index !== -1) currentIndex = index;

            const title = btn.dataset.title || '';
            const body = btn.dataset.body || '';
            const src = btn.dataset.img || '';

            const card = root.querySelector('.fs-card');
            const SWITCH_DURATION = 400;

            if (card) card.classList.add('fs-card--changing');

            window.setTimeout(() => {
                if (src && img) img.src = src;
                if (cardT) cardT.textContent = title;
                if (cardB) cardB.textContent = body;

                if (card) card.classList.remove('fs-card--changing');
                setIndicators(currentIndex);
            }, SWITCH_DURATION);
        }

        function go(delta) {
            const len = items.length;
            currentIndex = (currentIndex + delta + len) % len;
            activate(items[currentIndex]);
        }

        const initial = items.find(b => b.classList.contains('is-active')) || items[0];
        if (initial) activate(initial);

        function restoreActiveFinoraItem() {
            const activeItem = items.find(b => b.classList.contains('is-active')) || items[0];
            if (!activeItem) return;
            if (cardT) cardT.textContent = activeItem.dataset.title || '';
            if (cardB) cardB.textContent = activeItem.dataset.body || '';
            if (img && activeItem.dataset.img) img.src = activeItem.dataset.img;
        }

        /** Section-Höhe = Maximum aller Karten-Inhalte (Desktop), kein Springen beim Wechsel */
        function lockFinoraSwitchMinHeight() {
            if (!cardT || !cardB || !items.length) return;
            const cardEl = root.querySelector('.fs-card');
            if (cardEl) {
                cardEl.classList.remove('fs-card--changing');
                cardEl.style.minHeight = '';
            }
            root.style.minHeight = '';

            let maxCardH = 0;
            let maxH = 0;
            items.forEach(btn => {
                cardT.textContent = btn.dataset.title || '';
                cardB.textContent = btn.dataset.body || '';
                if (img && btn.dataset.img) img.src = btn.dataset.img;
                void root.offsetHeight;
                if (cardEl) {
                    maxCardH = Math.max(maxCardH, cardEl.offsetHeight);
                }
                const h = root.offsetHeight;
                if (h > maxH) maxH = h;
            });

            restoreActiveFinoraItem();

            if (cardEl && maxCardH) {
                cardEl.style.minHeight = maxCardH + 'px';
            }

            if (window.matchMedia('(min-width: 981px)').matches && maxH) {
                root.style.minHeight = maxH + 'px';
            }
        }

        window.addEventListener('load', () => {
            requestAnimationFrame(() => lockFinoraSwitchMinHeight());
        });
        let fsResizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(fsResizeTimer);
            fsResizeTimer = setTimeout(lockFinoraSwitchMinHeight, 200);
        });

        // Desktop: Inhalt bei Hover wechseln
        items.forEach(btn => {
            btn.addEventListener('mouseenter', () => activate(btn));
        });

        // Klick weiterhin für Fokus/Tastatur
        root.addEventListener('click', e => {
            const b = e.target.closest('.fs-item');
            if (!b) return;
            activate(b);
        });

        // Mobile buttons
        if (prevBtn) prevBtn.addEventListener('click', () => go(-1));
        if (nextBtn) nextBtn.addEventListener('click', () => go(1));

        // Mobile dots
        if (dotsWrap) {
            dotsWrap.addEventListener('click', e => {
                const dot = e.target.closest('.fs-dot');
                if (!dot) return;
                const idx = parseInt(dot.dataset.index, 10);
                if (!isNaN(idx)) activate(items[idx]);
            });
        }

        // Mobile swipe
        const swipeArea = root.querySelector('.fs-left');
        if (swipeArea) {
            let touchStartX = 0, touchStartY = 0, touchEndX = 0, touchEndY = 0;
            const SWIPE_THRESHOLD = 40;

            swipeArea.addEventListener('touchstart', function (e) {
                if (!e.touches || !e.touches.length) return;
                const t = e.touches[0];
                touchStartX = t.clientX;
                touchStartY = t.clientY;
                touchEndX = t.clientX;
                touchEndY = t.clientY;
            }, { passive: true });

            swipeArea.addEventListener('touchmove', function (e) {
                if (!e.touches || !e.touches.length) return;
                const t = e.touches[0];
                touchEndX = t.clientX;
                touchEndY = t.clientY;
            }, { passive: true });

            swipeArea.addEventListener('touchend', function () {
                const deltaX = touchEndX - touchStartX;
                const deltaY = touchEndY - touchStartY;
                if (Math.abs(deltaX) < Math.abs(deltaY)) return;
                if (Math.abs(deltaX) < SWIPE_THRESHOLD) return;
                if (deltaX < 0) go(1); else go(-1);
            });
        }
    }

    // =========================================================
    // 6. TABS (Altersvorsorge Favoriten)
    // =========================================================
    const tabNavs = document.querySelectorAll('.tab-nav');
    tabNavs.forEach(nav => {
        const buttons = nav.querySelectorAll('button');
        const panels = nav.parentElement.querySelectorAll('.tab-panel');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.remove('is-active'));
                panels.forEach(p => p.classList.remove('is-active'));
                btn.classList.add('is-active');
                const target = document.getElementById(btn.dataset.tab);
                if (target) target.classList.add('is-active');
            });
        });
    });

    // =========================================================
    // 7. AUDIENCE NAV (Altersvorsorge)
    // =========================================================
    const audienceNav = document.querySelector('.audience-nav');
    if (audienceNav) {
        const links = audienceNav.querySelectorAll('a');
        const blocks = document.querySelectorAll('.audience-block');
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                links.forEach(l => l.classList.remove('is-active'));
                blocks.forEach(b => b.classList.remove('is-active'));
                link.classList.add('is-active');
                const target = document.querySelector(link.getAttribute('href'));
                if (target) target.classList.add('is-active');
            });
        });
    }

    // =========================================================
    // 8. CONTACT FORM VALIDATION (Kontakt)
    // =========================================================
    const contactForm = document.querySelector('.contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const name = contactForm.querySelector('#contact-name');
            const email = contactForm.querySelector('#contact-email');
            const msg = contactForm.querySelector('#contact-message');
            const privacy = contactForm.querySelector('#contact-privacy');
            let valid = true;

            [name, email].forEach(f => {
                if (f && !f.value.trim()) {
                    f.style.borderColor = '#e74c3c';
                    valid = false;
                } else if (f) {
                    f.style.borderColor = '#e5e5e5';
                }
            });

            if (msg) {
                msg.style.borderColor = '#e5e5e5';
            }

            if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
                email.style.borderColor = '#e74c3c';
                valid = false;
            }

            if (privacy && !privacy.checked) {
                privacy.parentElement.style.outline = '2px solid #e74c3c';
                valid = false;
            } else if (privacy) {
                privacy.parentElement.style.outline = 'none';
            }

            if (valid) {
                alert(t.contactSuccess);
                contactForm.reset();
            }
        });
    }

    // =========================================================
    // 6. COUNTER ANIMATION (Calc-V2)
    // =========================================================
    var counterEls = document.querySelectorAll('.immobilien-calc-v2 .calc-v2__value[data-count], .immobilien-calc-v2 .calc-v2__kpi-value[data-count]');
    if (counterEls.length) {
        var counted = new Set();

        function formatNumber(n, decimals) {
            return new Intl.NumberFormat('de-DE', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(n);
        }

        function getCounterFormat(text) {
            var match = text.match(/[+-]?\d[\d.,\s\u00a0\u2009\u202f]*/);
            if (!match) return null;

            var numericPart = match[0].replace(/[\s\u00a0\u2009\u202f]/g, '');
            var decimals = 0;
            if (numericPart.indexOf(',') !== -1) {
                decimals = (numericPart.split(',')[1] || '').replace(/\D/g, '').length;
            }

            return {
                prefix: text.slice(0, match.index),
                suffix: text.slice(match.index + match[0].length),
                showPlus: numericPart.charAt(0) === '+',
                decimals: decimals
            };
        }

        function animateCounter(el) {
            var target = parseFloat(el.getAttribute('data-count'));
            var originalText = el.textContent;
            var format = getCounterFormat(originalText);
            if (!format || !isFinite(target)) return;
            var duration = 2000;
            var start = null;

            function step(timestamp) {
                if (!start) start = timestamp;
                var progress = Math.min((timestamp - start) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                var current = eased * target;
                var rounded = format.decimals > 0 ? Number(current.toFixed(format.decimals)) : Math.round(current);
                var rendered = formatNumber(rounded, format.decimals);

                if (format.showPlus && rounded >= 0) {
                    rendered = '+' + rendered;
                }

                el.textContent = format.prefix + rendered + format.suffix;

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.textContent = originalText;
                }
            }
            requestAnimationFrame(step);
        }

        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && !counted.has(entry.target)) {
                    counted.add(entry.target);
                    animateCounter(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counterEls.forEach(function (el) {
            counterObserver.observe(el);
        });
    }

    // =========================================================
    // Tech Expats: System-Diagramm (kompakt + Lightbox)
    // =========================================================
    const techDiagramOpen = document.querySelector('[data-tech-diagram-open]');
    const techDiagramDialog = document.querySelector('[data-tech-diagram-dialog]');
    const techDiagramDialogBody = document.querySelector('[data-tech-diagram-dialog-body]');
    const techDiagramSource = document.querySelector('[data-tech-diagram-source]');

    if (techDiagramOpen && techDiagramDialog && techDiagramDialogBody && techDiagramSource) {
        let diagramCloned = false;

        function closeTechDiagram() {
            if (techDiagramDialog.open) {
                techDiagramDialog.close();
            }
            document.body.classList.remove('has-tech-diagram-open');
        }

        function openTechDiagram() {
            if (!diagramCloned) {
                const clone = techDiagramSource.cloneNode(true);
                clone.removeAttribute('aria-hidden');
                clone.removeAttribute('data-tech-diagram-source');
                techDiagramDialogBody.appendChild(clone);
                diagramCloned = true;
            }
            techDiagramDialog.showModal();
            document.body.classList.add('has-tech-diagram-open');
            const closeBtn = techDiagramDialog.querySelector('[data-tech-diagram-close]');
            if (closeBtn) closeBtn.focus();
        }

        techDiagramOpen.addEventListener('click', openTechDiagram);

        const techDiagramClose = techDiagramDialog.querySelector('[data-tech-diagram-close]');
        if (techDiagramClose) {
            techDiagramClose.addEventListener('click', closeTechDiagram);
        }

        techDiagramDialog.addEventListener('click', function (e) {
            if (e.target === techDiagramDialog) {
                closeTechDiagram();
            }
        });

        techDiagramDialog.addEventListener('cancel', closeTechDiagram);
    }

    // =========================================================
    // Back to top (sitewide)
    // =========================================================
    const backToTopBtn = document.createElement('button');
    backToTopBtn.type = 'button';
    backToTopBtn.className = 'back-to-top';
    backToTopBtn.setAttribute('aria-label', t.backToTop);
    backToTopBtn.setAttribute('aria-hidden', 'true');
    backToTopBtn.tabIndex = -1;
    backToTopBtn.innerHTML = '<i class="fa-solid fa-chevron-up" aria-hidden="true"></i>';
    document.body.appendChild(backToTopBtn);

    const backToTopThreshold = 320;
    const prefersReducedMotionTop = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function syncBackToTop() {
        const y = window.scrollY || document.documentElement.scrollTop;
        const menuOpen = document.body.classList.contains('has-mobile-menu-open');
        const show = y > backToTopThreshold && !menuOpen;
        backToTopBtn.classList.toggle('is-visible', show);
        backToTopBtn.setAttribute('aria-hidden', show ? 'false' : 'true');
        backToTopBtn.tabIndex = show ? 0 : -1;
    }

    window.addEventListener('scroll', syncBackToTop, { passive: true });
    const backToTopBodyObserver = new MutationObserver(syncBackToTop);
    backToTopBodyObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
    syncBackToTop();

    backToTopBtn.addEventListener('click', function () {
        window.scrollTo({
            top: 0,
            behavior: prefersReducedMotionTop ? 'auto' : 'smooth',
        });
        backToTopBtn.blur();
    });

});
