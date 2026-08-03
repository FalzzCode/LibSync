import './bootstrap';

const solarGlyphs = {
    'Rp': 'solar:wallet-money-linear', '\u2318': 'solar:code-square-linear', '\u2699': 'solar:settings-linear', '\u2699\uFE0F': 'solar:settings-linear', '\u2190': 'solar:arrow-left-linear', '\u231B': 'solar:clock-circle-linear', '\u21A9': 'solar:alt-arrow-left-linear',
    '⌂': 'solar:home-2-linear', '▤': 'solar:book-2-linear', '▦': 'solar:widget-5-linear',
    '↺': 'solar:refresh-circle-linear', '↻': 'solar:refresh-circle-linear', '!': 'solar:danger-triangle-linear',
    '#': 'solar:hashtag-linear', '♙': 'solar:users-group-rounded-linear', '◇': 'solar:widget-5-linear',
    '↓': 'solar:download-linear', '↑': 'solar:upload-linear', '◉': 'solar:user-circle-linear',
    '☰': 'solar:hamburger-menu-linear', '⌄': 'solar:alt-arrow-down-linear', '→': 'solar:arrow-right-linear',
    '↗': 'solar:eye-linear', '✎': 'solar:pen-new-square-linear', '×': 'solar:close-circle-linear',
    '✓': 'solar:check-circle-linear', '⌕': 'solar:magnifer-linear', '✦': 'solar:magic-stick-3-linear',
    '☷': 'solar:list-linear', '◐': 'solar:moon-stars-linear', '+': 'solar:add-circle-linear',
};

const isIconifyReady = () => Boolean(window.customElements?.get('iconify-icon'));

const createSolarIcon = (icon) => {
    const element = document.createElement('iconify-icon');
    element.className = 'solar-icon';
    element.setAttribute('icon', icon);
    element.setAttribute('aria-hidden', 'true');
    return element;
};

const replaceSolarIcons = (root = document) => {
    // Keep the readable glyph fallback until Iconify has registered its custom element.
    if (!isIconifyReady()) return;

    const selector = '.sidebar__link > span, .sidebar__close, .header__menu-toggle, .header__profile > span:last-child, .theme-toggle > span, .mobile-tabbar span, .stat-card__icon, .stat-card > b, .quick-actions > a > span, .tip-card__icon, .activity-row__icon, .task-item__icon, .student-summary__icon, .empty-state > span, .search-field > span, .icon-button, .view-toggle__btn, .alert > span, .file-field__label > span, .profile-photo-field label > span, .metric-card__icon';
    const explicitSelector = '[data-solar-icon]';
    const explicitElements = [];
    if (root.matches?.(explicitSelector)) explicitElements.push(root);
    root.querySelectorAll(explicitSelector).forEach((element) => explicitElements.push(element));
    explicitElements.forEach((element) => {
        if (element.querySelector('iconify-icon')) return;
        const icon = element.dataset.solarIcon;
        if (!icon) return;
        element.setAttribute('aria-hidden', 'true');
        element.replaceChildren(createSolarIcon(icon));
    });

    const elements = [];
    if (root.matches?.(selector)) elements.push(root);
    root.querySelectorAll(selector).forEach((element) => elements.push(element));
    elements.forEach((element) => {
        const glyph = element.textContent.trim();
        const icon = element.dataset.solarIcon || solarGlyphs[glyph];
        if (!icon || element.querySelector('iconify-icon')) return;
        element.setAttribute('aria-hidden', 'true');
        element.replaceChildren(createSolarIcon(icon));
    });

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const textNodes = [];
    const inlineGlyphPattern = /[→←↗+]/g;
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.parentElement?.closest('iconify-icon, [data-solar-icon]')) continue;
        const value = node.nodeValue;
        const icon = solarGlyphs[value.trim()];
        if (icon) {
            textNodes.push([node, createSolarIcon(icon)]);
            continue;
        }
        inlineGlyphPattern.lastIndex = 0;
        if (!inlineGlyphPattern.test(value)) {
            inlineGlyphPattern.lastIndex = 0;
            continue;
        }
        inlineGlyphPattern.lastIndex = 0;
        const fragment = document.createDocumentFragment();
        let cursor = 0;
        for (const match of value.matchAll(inlineGlyphPattern)) {
            if (match.index > cursor) fragment.append(value.slice(cursor, match.index));
            fragment.append(createSolarIcon(solarGlyphs[match[0]]));
            cursor = match.index + match[0].length;
        }
        if (cursor < value.length) fragment.append(value.slice(cursor));
        textNodes.push([node, fragment]);
    }
    textNodes.forEach(([node, replacement]) => node.replaceWith(replacement));
};

const waitForIconify = () => {
    const whenDefined = window.customElements?.whenDefined;
    if (typeof whenDefined !== 'function') return;
    whenDefined.call(window.customElements, 'iconify-icon').then(() => replaceSolarIcons());
};

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
const memory = navigator.deviceMemory || 8;
const cores = navigator.hardwareConcurrency || 8;
const lowPowerDevice = connection?.saveData || memory <= 4 || cores <= 4 || window.innerWidth < 768;
const canEnhance = !reduceMotion && !lowPowerDevice;

document.addEventListener('DOMContentLoaded', () => {
    replaceSolarIcons();
    waitForIconify();
    document.querySelectorAll('[data-search-clear]').forEach((clearButton) => {
        const wrapper = clearButton.closest('.search-field, .student-catalog-search');
        const input = wrapper?.querySelector('input[type="search"]');
        const form = input?.form;
        if (!input) return;

        const syncClearButton = () => {
            clearButton.hidden = input.value.length === 0;
        };

        input.addEventListener('input', syncClearButton);
        input.addEventListener('search', syncClearButton);
        clearButton.addEventListener('click', () => {
            input.value = '';
            syncClearButton();
            input.focus();
            if (form?.requestSubmit) form.requestSubmit();
            else form?.submit();
        });
        syncClearButton();
    });
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === Node.ELEMENT_NODE) replaceSolarIcons(node);
    }))).observe(document.body, { childList: true, subtree: true });
    if (window.location.pathname === '/members') document.querySelector('.table-card')?.classList.add('members-table-card');
    if (window.location.pathname === '/borrowings') document.querySelector('.table-card')?.classList.add('borrowings-table-card');
    document.querySelector('[data-mobile-menu]')?.addEventListener('click', () => document.getElementById('menuToggle')?.click());
    const loader = document.getElementById('pageLoader');
    const pendingNavigation = document.documentElement.classList.contains('is-navigating');
    const minimumLoaderMs = 420;
    let navigationLocked = false;
    const readNavigationStart = () => {
        try { return Number(sessionStorage.getItem('ruang-baca:navigation-pending')) || 0; } catch (_) { return 0; }
    };
    const showLoader = () => {
        if (window.matchMedia('(max-width: 760px)').matches) {
            document.documentElement.classList.add('mobile-is-loading');
            document.getElementById('appSidebar')?.setAttribute('aria-hidden', 'true');
        }
        if (loader && loader.hidden) loader.hidden = false;
        try { sessionStorage.setItem('ruang-baca:navigation-pending', String(Date.now())); } catch (_) {}
    };
    const loaderTypeFor = (pathname) => {
        if (/^\/books\/\d+$/.test(pathname) || /^\/borrowings\/\d+$/.test(pathname)) return 'detail';
        const routes = {
            '/dashboard': 'dashboard', '/books': 'books', '/book-copies': 'copies', '/members': 'members',
            '/categories': 'categories', '/borrowings': 'borrowings', '/warnings': 'warnings', '/fines': 'fines',
            '/imports': 'imports', '/users': 'users', '/developer': 'developer', '/student': 'dashboard', '/student/catalog': 'catalog',
        };
        return routes[pathname] || (pathname.endsWith('/create') || pathname.endsWith('/edit') ? 'form' : 'table');
    };

    if (pendingNavigation && loader) {
        loader.hidden = false;
        document.documentElement.classList.remove('is-navigating');
        const remaining = Math.max(0, minimumLoaderMs - (Date.now() - readNavigationStart()));
        window.setTimeout(() => {
            loader.hidden = true;
            document.documentElement.classList.remove('mobile-is-loading');
            document.getElementById('appSidebar')?.removeAttribute('aria-hidden');
            try { sessionStorage.removeItem('ruang-baca:navigation-pending'); } catch (_) {}
        }, remaining);
    }

    window.addEventListener('pageshow', () => {
        if (loader) loader.hidden = true;
        document.documentElement.classList.remove('is-navigating');
        document.documentElement.classList.remove('mobile-is-loading');
        document.getElementById('appSidebar')?.removeAttribute('aria-hidden');
        try { sessionStorage.removeItem('ruang-baca:navigation-pending'); } catch (_) {}
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank' || link.hasAttribute('download')) return;
        const destination = new URL(link.href, window.location.href);
        if (destination.origin !== window.location.origin || destination.pathname === window.location.pathname && destination.hash || destination.pathname.includes('/reports/') || destination.pathname.includes('/backups/')) return;
        event.preventDefault();
        if (navigationLocked) return;
        navigationLocked = true;
        if (loader) loader.className = `page-loader page-loader--${loaderTypeFor(destination.pathname)}`;
        showLoader();
        window.setTimeout(() => window.location.assign(destination.href), 180);
    });

    document.querySelectorAll('form:not([data-no-loader]):not(.js-confirm-delete)').forEach((form) => form.addEventListener('submit', (event) => {
        if (!event.defaultPrevented) showLoader();
    }));

    if (!canEnhance) return;

    const startEnhancements = () => {
        document.documentElement.classList.add('enhanced-imagery');
        import('motion').then(({ animate, hover, inView, press, stagger }) => {
            document.querySelectorAll('.hero-card').forEach((hero) => {
                if (hero.querySelector('.book-orbit')) return;
                const shelf = document.createElement('span');
                shelf.className = 'book-orbit';
                shelf.setAttribute('aria-hidden', 'true');
                shelf.innerHTML = '<i></i><i></i><i></i><i></i>';
                hero.appendChild(shelf);
            });

            const revealTargets = document.querySelectorAll('.page .hero-card, .page .student-hero, .page .stat-card, .page .transaction-stats article, .page .quick-actions a, .page .insight-card, .page .recent-panel, .page .table-card, .page .form-card, .page .detail-card, .page .catalog-card');
            inView([...revealTargets], (element) => animate(element, { opacity: [0, 1], y: [14, 0] }, { duration: 0.32, ease: [0.22, 1, 0.36, 1] }), { amount: 0.12, once: true });

            const studentSummary = document.querySelectorAll('.student-summary__card, .student-next-card, .student-loan-row, .student-notification');
            if (studentSummary.length) {
                inView([...studentSummary], (element) => animate(element, { opacity: [0, 1], y: [10, 0], scale: [0.985, 1] }, { duration: 0.26, ease: [0.22, 1, 0.36, 1] }), { amount: 0.1, once: true });
                hover('.student-summary__card, .student-panel__catalog-link', (element) => {
                    animate(element, { y: -2 }, { type: 'spring', stiffness: 520, damping: 32 });
                    return () => animate(element, { y: 0 }, { type: 'spring', stiffness: 520, damping: 32 });
                });
            }

            hover('.sidebar__link, .stat-card, .catalog-card, .quick-actions a', (element) => {
                animate(element, { x: 3 }, { type: 'spring', stiffness: 520, damping: 32 });
                return () => animate(element, { x: 0 }, { type: 'spring', stiffness: 520, damping: 32 });
            });

            press('.btn, .icon-button', (element) => {
                const down = animate(element, { scale: 0.97 }, { duration: 0.1 });
                return () => { down.stop(); animate(element, { scale: 1 }, { type: 'spring', stiffness: 620, damping: 30 }); };
            });

            const heroBooks = document.querySelectorAll('.book-orbit i');
            if (heroBooks.length) animate(heroBooks, { y: [12, 0], opacity: [0, 1] }, { delay: stagger(0.08), duration: 0.4, ease: [0.22, 1, 0.36, 1] });
        }).catch(() => {});
    };

    if ('requestIdleCallback' in window) window.requestIdleCallback(startEnhancements, { timeout: 1200 });
    else window.setTimeout(startEnhancements, 500);
});
