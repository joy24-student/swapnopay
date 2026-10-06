/**
 * ShopNext Ultra-Fast SPA Navigation & Shimmer Skeleton Engine
 * - Zero-reload client-side routing across all shop pages & product cards
 * - Instant product card click delegation (zero reload on image, title, price, card body)
 * - Speculative prefetching on hover & touchstart (0ms perceived latency)
 * - Modern View Transitions API cross-fade animation with fallback
 * - High-polish contextual Shimmer Skeletons (Product, Grid, Cart, Generic)
 * - Dynamic stylesheet synchronization & safe script execution
 * - History pushState & popstate Back/Forward synchronization
 */

(function () {
    'use strict';

    // ------------------------------------------------------------
    // 1. CONFIGURATION & STATE
    // ------------------------------------------------------------
    const CACHE_TTL_MS = 60000; // 60 seconds memory cache for lightning-fast 0ms navigation
    const pageCache = new Map();
    let isNavigating = false;
    let abortController = null;
    let progressTimer = null;

    function isCacheable(url) {
        if (!url) return false;
        const lower = url.toLowerCase();
        if (lower.includes('checkout') || lower.includes('cart.php') || lower.includes('order') || lower.includes('dashboard') || lower.includes('login') || lower.includes('register') || lower.includes('customer-') || lower.includes('payment')) {
            return false;
        }
        return true;
    }

    // Elements
    let progressBar = null;
    let pageContainer = null;

    // ------------------------------------------------------------
    // 2. INITIALIZATION
    // ------------------------------------------------------------
    function init() {
        // Ensure top progress bar exists
        progressBar = document.getElementById('sn-spa-progress');
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.id = 'sn-spa-progress';
            document.body.prepend(progressBar);
        }

        // Cache current page container
        pageContainer = document.getElementById('sn-page-container') || document.querySelector('.content-wrapper-main');

        // Prime the cache with current page's HTML
        if (pageContainer) {
            const currentClean = cleanUrl(window.location.href);
            pageCache.set(currentClean, {
                html: document.documentElement.outerHTML,
                finalUrl: window.location.href,
                time: Date.now()
            });
        }

        // Intercept all link clicks and product card clicks (capture phase to take priority)
        document.addEventListener('click', handleLinkClick, false);

        // Speculative prefetching on hover and touch
        document.addEventListener('pointerenter', handleLinkHover, { passive: true, capture: true });
        document.addEventListener('touchstart', handleLinkHover, { passive: true, capture: true });

        // History popstate navigation (Back / Forward)
        window.addEventListener('popstate', handlePopState, false);

        // Listen for custom cart updates to invalidate stale cart/checkout cache
        window.addEventListener('shopnext:cart-updated', clearCartCache, false);
    }

    // ------------------------------------------------------------
    // 3. PROGRESS BAR CONTROLLER
    // ------------------------------------------------------------
    function startProgress() {
        if (!progressBar) return;
        clearTimeout(progressTimer);
        progressBar.className = 'active';
        progressBar.style.width = '25%';

        progressTimer = setTimeout(() => {
            if (progressBar) progressBar.style.width = '70%';
            progressTimer = setTimeout(() => {
                if (progressBar) progressBar.style.width = '88%';
            }, 200);
        }, 60);
    }

    function finishProgress() {
        if (!progressBar) return;
        clearTimeout(progressTimer);
        progressBar.className = 'active finished';
        progressBar.style.width = '100%';

        setTimeout(() => {
            if (progressBar) {
                progressBar.className = '';
                progressBar.style.width = '0%';
            }
        }, 300);
    }

    // ------------------------------------------------------------
    // 4. CONTEXTUAL SHIMMER SKELETON GENERATOR
    // ------------------------------------------------------------
    function getSkeletonHtml(url) {
        const u = url.toLowerCase();

        // 1. PRODUCT DETAIL SCREEN SKELETON
        if (u.includes('product.php') || u.includes('/product/')) {
            return `
            <div id="sn-spa-skeleton" class="active">
                <div class="sn-skel-product-wrap">
                    <div class="sn-skel-gallery-col">
                        <div class="sn-skel-shimmer sn-skel-main-img"></div>
                        <div class="sn-skel-thumbs-row">
                            <div class="sn-skel-shimmer sn-skel-thumb"></div>
                            <div class="sn-skel-shimmer sn-skel-thumb"></div>
                            <div class="sn-skel-shimmer sn-skel-thumb"></div>
                            <div class="sn-skel-shimmer sn-skel-thumb"></div>
                        </div>
                    </div>
                    <div class="sn-skel-info-col">
                        <div class="sn-skel-shimmer sn-skel-title-1"></div>
                        <div class="sn-skel-shimmer sn-skel-title-2"></div>
                        <div class="sn-skel-shimmer sn-skel-rating"></div>
                        <div class="sn-skel-shimmer sn-skel-price-box"></div>
                        <div class="sn-skel-variant-row">
                            <div class="sn-skel-shimmer sn-skel-chip"></div>
                            <div class="sn-skel-shimmer sn-skel-chip"></div>
                            <div class="sn-skel-shimmer sn-skel-chip"></div>
                        </div>
                        <div class="sn-skel-actions-row">
                            <div class="sn-skel-shimmer sn-skel-btn"></div>
                            <div class="sn-skel-shimmer sn-skel-btn"></div>
                        </div>
                        <div class="sn-skel-shimmer sn-skel-tab-box"></div>
                    </div>
                </div>
            </div>`;
        }

        // 2. CATEGORY / GRID / DEALS SKELETON
        if (u.includes('product-category.php') || u.includes('categories.php') || u.includes('deals.php') || u.includes('search-result.php') || u.includes('/category/')) {
            const cardSkel = `
                <div class="sn-skel-card">
                    <div class="sn-skel-shimmer sn-skel-card-img"></div>
                    <div class="sn-skel-shimmer sn-skel-card-title"></div>
                    <div class="sn-skel-shimmer sn-skel-card-sub"></div>
                    <div class="sn-skel-shimmer sn-skel-card-price"></div>
                    <div class="sn-skel-shimmer sn-skel-card-btn"></div>
                </div>`;
            return `
            <div id="sn-spa-skeleton" class="active">
                <div class="sn-skel-grid-wrap">
                    <div class="sn-skel-shimmer sn-skel-banner"></div>
                    <div class="sn-skel-filter-bar">
                        <div class="sn-skel-shimmer sn-skel-filter-pill"></div>
                        <div class="sn-skel-shimmer sn-skel-filter-pill"></div>
                        <div class="sn-skel-shimmer sn-skel-filter-pill"></div>
                        <div class="sn-skel-shimmer sn-skel-filter-pill"></div>
                    </div>
                    <div class="sn-skel-cards-grid">
                        ${cardSkel}${cardSkel}${cardSkel}${cardSkel}${cardSkel}${cardSkel}${cardSkel}${cardSkel}
                    </div>
                </div>
            </div>`;
        }

        // 3. CART / CHECKOUT SKELETON
        if (u.includes('cart.php') || u.includes('checkout.php')) {
            return `
            <div id="sn-spa-skeleton" class="active">
                <div class="sn-skel-cart-wrap">
                    <div>
                        <div class="sn-skel-cart-item">
                            <div class="sn-skel-shimmer sn-skel-cart-thumb"></div>
                            <div class="sn-skel-cart-info">
                                <div class="sn-skel-shimmer sn-skel-title-1"></div>
                                <div class="sn-skel-shimmer sn-skel-rating"></div>
                            </div>
                        </div>
                        <div class="sn-skel-cart-item">
                            <div class="sn-skel-shimmer sn-skel-cart-thumb"></div>
                            <div class="sn-skel-cart-info">
                                <div class="sn-skel-shimmer sn-skel-title-1"></div>
                                <div class="sn-skel-shimmer sn-skel-rating"></div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="sn-skel-shimmer sn-skel-tab-box" style="height: 240px; margin-top:0;"></div>
                    </div>
                </div>
            </div>`;
        }

        // 4. GENERIC / FALLBACK SKELETON
        return `
        <div id="sn-spa-skeleton" class="active">
            <div class="sn-skel-generic-wrap">
                <div class="sn-skel-shimmer sn-skel-generic-hero"></div>
                <div class="sn-skel-generic-body">
                    <div class="sn-skel-shimmer sn-skel-title-1"></div>
                    <div class="sn-skel-shimmer sn-skel-line" style="width: 90%;"></div>
                    <div class="sn-skel-shimmer sn-skel-line" style="width: 75%;"></div>
                    <div class="sn-skel-shimmer sn-skel-line" style="width: 80%;"></div>
                </div>
            </div>
        </div>`;
    }

    // ------------------------------------------------------------
    // 5. URL HELPERS & VALIDATION
    // ------------------------------------------------------------
    function cleanUrl(href) {
        try {
            const parsed = new URL(href, window.location.href);
            parsed.hash = '';
            // Ensure protocol and port always match current window origin
            if (parsed.hostname === window.location.hostname) {
                parsed.protocol = window.location.protocol;
                parsed.port = window.location.port;
            }
            return parsed.href;
        } catch (e) {
            return href;
        }
    }

    function isEligibleLink(anchor) {
        if (!anchor) return false;
        const href = anchor.getAttribute('href');
        if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:')) {
            return false;
        }
        if (anchor.target && anchor.target !== '_self') return false;
        if (anchor.hasAttribute('download')) return false;
        if (anchor.getAttribute('data-no-spa') === 'true') return false;

        try {
            const parsed = new URL(anchor.href, window.location.href);
            // Same domain / hostname check
            if (parsed.hostname !== window.location.hostname) return false;

            // Skip admin and external payment redirects
            const path = parsed.pathname.toLowerCase();
            if (path.includes('/admin/') || path.includes('/payment/') || path.includes('logout.php') || path.includes('login.php')) {
                return false;
            }

            return true;
        } catch (e) {
            return false;
        }
    }

    // ------------------------------------------------------------
    // 6. SPECULATIVE PREFETCH ENGINE
    // ------------------------------------------------------------
    function prefetchUrl(targetUrl) {
        if (!targetUrl) return;
        const cleaned = cleanUrl(targetUrl);
        if (!isCacheable(cleaned)) return;
        const cached = pageCache.get(cleaned);
        if (cached && (Date.now() - cached.time < CACHE_TTL_MS)) {
            return; // Already cached and fresh
        }

        fetch(cleaned, {
            headers: { 'X-Requested-With': 'ShopNext-SPA' }
        })
        .then(res => {
            if (res.ok) {
                const finalUrl = res.url || cleaned;
                return res.text().then(html => ({ html, finalUrl }));
            }
            throw new Error('Prefetch status: ' + res.status);
        })
        .then(({ html, finalUrl }) => {
            pageCache.set(cleaned, {
                html: html,
                finalUrl: finalUrl,
                time: Date.now()
            });
            if (finalUrl !== cleaned) {
                pageCache.set(cleanUrl(finalUrl), {
                    html: html,
                    finalUrl: finalUrl,
                    time: Date.now()
                });
            }
        })
        .catch(() => {
            // Silently ignore prefetch failures
        });
    }

    function handleLinkHover(e) {
        const targetEl = e.target instanceof Element ? e.target : (e.target && e.target.parentElement instanceof Element ? e.target.parentElement : null);
        if (!targetEl || typeof targetEl.closest !== 'function') return;

        // Ignore hover over action buttons or inputs
        if (targetEl.closest('button, input, select, textarea, .sn-btn-cart, .sn-btn-mob-cart, .sn-flash-wishlist, .sn-rel-wishlist, .sn-add-cart-btn, .sn-rel-add-btn')) {
            return;
        }

        let anchor = targetEl.closest('a');
        if (!anchor) {
            // Check if hovering over a product card or item card
            const card = targetEl.closest(
                '.sn-product-card, .sn-flash-card, .sn-shira-card, .sn-rel-card, ' +
                '.sn-card, .sn-deal-card, .srp-card, .sn-recom-card, .sn-cat-prod-card, ' +
                '.sn-subcat-card, [data-href]'
            );
            if (card) {
                const dataHref = card.getAttribute('data-href');
                if (dataHref) {
                    prefetchUrl(dataHref);
                    return;
                }
                anchor = card.querySelector('a[href*="product"], a.sn-details-link, a.sn-rel-title, a.sn-deal-title, a.sn-product-card-link, a[href]');
            }
        }
        if (anchor && isEligibleLink(anchor)) {
            prefetchUrl(anchor.href);
        }
    }

    // ------------------------------------------------------------
    // 7. CORE SPA NAVIGATION CONTROLLER (ZERO RELOAD)
    // ------------------------------------------------------------
    async function navigateTo(targetUrl, options = { pushState: true }) {
        if (!targetUrl) return;

        const targetClean = cleanUrl(targetUrl);
        const currentClean = cleanUrl(window.location.href);

        // If clicking exact current page without hash, smoothly scroll to top
        if (targetClean === currentClean && !targetUrl.includes('#')) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // Abort any ongoing navigation
        if (isNavigating && abortController) {
            abortController.abort();
        }

        isNavigating = true;
        abortController = new AbortController();
        startProgress();

        // Clear any running timers from previous page (e.g. hero slider autoplay)
        if (window.__snHeroTimer) {
            clearInterval(window.__snHeroTimer);
            window.__snHeroTimer = null;
        }

        // Dismiss any active promo welcome popup on page transition
        if (typeof window.closeWelcomePopup === 'function') {
            window.closeWelcomePopup(false);
        } else {
            const activePopup = document.getElementById('promoPopup');
            if (activePopup) {
                activePopup.classList.remove('sn-popup-active');
                activePopup.style.display = 'none';
                document.body.classList.remove('sn-popup-open');
            }
        }
        try { sessionStorage.setItem('sn_popup_shown_session', 'true'); } catch (e) {}
        document.body.classList.remove('sn-feed-sticky-active');

        let skeletonTimeout = null;

        // Check in-memory cache
        const isUrlCacheable = isCacheable(targetClean);
        const cached = isUrlCacheable ? pageCache.get(targetClean) : null;
        const hasFreshCache = isUrlCacheable && cached && (Date.now() - cached.time < CACHE_TTL_MS);

        if (!hasFreshCache) {
            // Render contextual shimmer skeleton within 30ms for instant visual feedback
            skeletonTimeout = setTimeout(() => {
                const container = document.getElementById('sn-page-container') || document.querySelector('.content-wrapper-main');
                if (container) {
                    container.innerHTML = getSkeletonHtml(targetClean);
                    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
                }
            }, 30);
        }

        try {
            let htmlText = '';
            let finalUrl = targetUrl;

            if (hasFreshCache) {
                htmlText = cached.html;
                finalUrl = cached.finalUrl || targetUrl;
            } else {
                const response = await fetch(targetClean, {
                    signal: abortController.signal,
                    headers: { 'X-Requested-With': 'ShopNext-SPA' }
                });

                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }

                if (response.url) {
                    finalUrl = response.url;
                }

                htmlText = await response.text();
                if (isUrlCacheable) {
                    pageCache.set(targetClean, {
                        html: htmlText,
                        finalUrl: finalUrl,
                        time: Date.now()
                    });
                    if (finalUrl !== targetClean) {
                        pageCache.set(cleanUrl(finalUrl), {
                            html: htmlText,
                            finalUrl: finalUrl,
                            time: Date.now()
                        });
                    }
                }
            }

            clearTimeout(skeletonTimeout);

            // Parse incoming document
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(htmlText, 'text/html');

            const newContainer = newDoc.getElementById('sn-page-container') || newDoc.querySelector('.content-wrapper-main');

            if (!newContainer) {
                // If target page lacks the SPA container, fall back to browser navigation
                window.location.href = finalUrl;
                return;
            }

            // Execute DOM swap with View Transition if supported
            const performDomSwap = () => {
                // 1. Update Title & Meta
                if (newDoc.title) {
                    document.title = newDoc.title;
                }

                // 2. Update Body Classes
                if (newDoc.body) {
                    document.body.className = newDoc.body.className;
                }

                // 3. Dynamically sync new stylesheets
                syncPageStylesheets(newDoc, newContainer);

                // 4. Swap Container Content
                const currentContainer = document.getElementById('sn-page-container') || document.querySelector('.content-wrapper-main');
                if (currentContainer && newContainer) {
                    currentContainer.innerHTML = newContainer.innerHTML;
                }

                // 5. Update Bottom Dock and Header active links
                updateActiveStates(cleanUrl(finalUrl));

                // 6. Update Cart Badge Count
                syncCartBadges(newDoc);

                // 7. Scroll to top instantly
                window.scrollTo({ top: 0, left: 0, behavior: 'instant' });

                // 8. Safely evaluate page scripts
                executePageScripts(newContainer || currentContainer);
            };

            if (document.startViewTransition) {
                document.startViewTransition(performDomSwap);
            } else {
                performDomSwap();
            }

            // Update Browser History
            if (options.pushState) {
                history.pushState({ url: finalUrl }, '', finalUrl);
            }

            // Finish Progress Bar
            finishProgress();

        } catch (err) {
            clearTimeout(skeletonTimeout);
            if (err.name !== 'AbortError') {
                console.warn('[ShopNext SPA] Navigation error:', err);
                window.location.href = targetUrl;
            }
        } finally {
            isNavigating = false;
        }
    }

    // ------------------------------------------------------------
    // 8. STYLESHEET & SCRIPT EXECUTION ENGINE
    // ------------------------------------------------------------
    function syncPageStylesheets(newDoc, newContainer) {
        try {
            const existingLinks = new Set(
                Array.from(document.querySelectorAll('link[rel="stylesheet"]')).map(l => cleanUrl(l.href))
            );
            const incomingLinks = [
                ...Array.from(newDoc.querySelectorAll('link[rel="stylesheet"]')),
                ...(newContainer ? Array.from(newContainer.querySelectorAll('link[rel="stylesheet"]')) : [])
            ];
            incomingLinks.forEach(link => {
                const cleanHref = cleanUrl(link.href);
                if (cleanHref && !existingLinks.has(cleanHref)) {
                    const newLink = document.createElement('link');
                    newLink.rel = 'stylesheet';
                    newLink.href = link.href;
                    document.head.appendChild(newLink);
                    existingLinks.add(cleanHref);
                }
            });
        } catch (e) {
            console.warn('[ShopNext SPA] Stylesheet sync error:', e);
        }
    }

    function executePageScripts(container) {
        if (!container) return;

        const scripts = container.querySelectorAll('script');
        scripts.forEach(oldScript => {
            if (oldScript.src) {
                const newScript = document.createElement('script');
                Array.from(oldScript.attributes).forEach(attr => {
                    newScript.setAttribute(attr.name, attr.value);
                });
                document.body.appendChild(newScript);
                setTimeout(() => newScript.remove(), 150);
            } else if (oldScript.textContent.trim()) {
                try {
                    const newScript = document.createElement('script');
                    newScript.textContent = oldScript.textContent;
                    document.body.appendChild(newScript);
                    newScript.remove();
                } catch (e) {
                    console.error('[ShopNext SPA Script Evaluation Error]', e);
                }
            }
        });

        // Re-dispatch DOM events so page-specific listeners attach cleanly
        requestAnimationFrame(() => {
            try {
                document.dispatchEvent(new Event('DOMContentLoaded'));
                window.dispatchEvent(new Event('load'));
                document.dispatchEvent(new CustomEvent('shopnext:page-loaded', { detail: { url: window.location.href } }));
                if (window.jQuery) {
                    window.jQuery(document).trigger('ready');
                }
            } catch (e) {
                console.error('[ShopNext SPA] Event dispatch error:', e);
            }
        });
    }

    // ------------------------------------------------------------
    // 9. ACTIVE STATES & BADGE SYNCHRONIZATION
    // ------------------------------------------------------------
    function updateActiveStates(url) {
        const u = url.toLowerCase();

        // 1. Mobile Bottom Dock
        const dockItems = document.querySelectorAll('.sn-dock-item');
        dockItems.forEach(item => {
            item.classList.remove('active');
            const svg = item.querySelector('svg');
            if (svg) {
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
            }
        });

        let activeTarget = null;
        if (u.includes('deals.php')) {
            activeTarget = document.querySelector('.sn-dock-item[href*="deals.php"]');
        } else if (u.includes('messages.php')) {
            activeTarget = document.querySelector('.sn-dock-item[href*="messages.php"]');
        } else if (u.includes('cart.php')) {
            activeTarget = document.querySelector('.sn-dock-item[href*="cart.php"]');
        } else if (u.includes('dashboard.php') || u.includes('customer-') || u.includes('profile')) {
            activeTarget = document.querySelector('.sn-dock-item[href*="dashboard.php"]') || document.querySelector('.sn-dock-item[href*="login.php"]');
        } else {
            activeTarget = document.querySelector('.sn-dock-item[href$="index.php"]') || document.querySelector('.sn-dock-item:first-child');
        }

        if (activeTarget) {
            activeTarget.classList.add('active');
            const svg = activeTarget.querySelector('svg');
            if (svg) {
                svg.setAttribute('fill', '#fab802');
                svg.setAttribute('stroke', '#fab802');
            }
        }

        // 2. Desktop Navigation Links
        const desktopLinks = document.querySelectorAll('.sn-nav-link, .main-nav a');
        desktopLinks.forEach(link => {
            const linkHref = cleanUrl(link.href).toLowerCase();
            if (linkHref === u) {
                link.classList.add('active');
            } else {
                link.classList.remove('active');
            }
        });
    }

    function syncCartBadges(newDoc) {
        const newBadge = newDoc.getElementById('sn-cart-badge-count');
        const currentBadge = document.getElementById('sn-cart-badge-count');
        if (newBadge && currentBadge) {
            currentBadge.textContent = newBadge.textContent;
            currentBadge.style.display = newBadge.style.display;
        }

        const newDockBadge = newDoc.getElementById('sn-dock-cart-count');
        const currentDockBadge = document.getElementById('sn-dock-cart-count');
        if (newDockBadge && currentDockBadge) {
            currentDockBadge.textContent = newDockBadge.textContent;
            currentDockBadge.style.display = newDockBadge.style.display;
        }
    }

    function clearCartCache() {
        for (const key of pageCache.keys()) {
            if (key.includes('cart.php') || key.includes('checkout.php')) {
                pageCache.delete(key);
            }
        }
    }

    // ------------------------------------------------------------
    // 10. PRODUCT CARD CLICK DELEGATION & LINK INTERCEPTION
    // ------------------------------------------------------------
    function handleLinkClick(e) {
        if (e.defaultPrevented) return;
        // Allow middle clicks / new-tab clicks (Ctrl, Cmd, Shift, Alt)
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        const targetEl = e.target instanceof Element ? e.target : (e.target && e.target.parentElement instanceof Element ? e.target.parentElement : null);
        if (!targetEl || typeof targetEl.closest !== 'function') return;

        // Do NOT intercept clicks on explicit interactive buttons or form controls
        if (targetEl.closest('button, input, select, textarea, label, .sn-btn-cart, .sn-btn-mob-cart, .sn-flash-wishlist, .sn-rel-wishlist, .sn-rel-add-btn, .sn-add-cart-btn, .sn-deal-wishlist-btn, .sn-deal-coupon-pill, .sn-recom-add-btn')) {
            return;
        }

        // 1. Check if user clicked directly on an anchor
        let anchor = targetEl.closest('a');

        // 2. If not directly on an anchor, check if clicked anywhere on a product card or item card
        if (!anchor) {
            const card = targetEl.closest(
                '.sn-product-card, .sn-flash-card, .sn-shira-card, .sn-rel-card, ' +
                '.sn-card, .sn-deal-card, .srp-card, .sn-recom-card, .sn-cat-prod-card, ' +
                '.sn-subcat-card, [data-href]'
            );
            if (card) {
                // If the card has a direct data-href attribute
                const dataHref = card.getAttribute('data-href');
                if (dataHref) {
                    const tempAnchor = document.createElement('a');
                    tempAnchor.href = dataHref;
                    if (isEligibleLink(tempAnchor)) {
                        e.preventDefault();
                        navigateTo(tempAnchor.href, { pushState: true });
                        return;
                    }
                }
                // Otherwise find the inner product / destination link
                anchor = card.querySelector('a[href*="product"], a.sn-details-link, a.sn-rel-title, a.sn-deal-title, a.sn-product-card-link, a[href]');
            }
        }

        if (!anchor || !isEligibleLink(anchor)) return;

        e.preventDefault();
        navigateTo(anchor.href, { pushState: true });
    }

    function handlePopState(e) {
        navigateTo(window.location.href, { pushState: false });
    }

    // Expose programmatic navigation globally
    window.ShopNextSPA = {
        navigate: navigateTo,
        prefetch: prefetchUrl,
        clearCache: () => pageCache.clear()
    };

    // Auto-init on DOM readiness
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
