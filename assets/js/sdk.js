/**
 * Advanced LLM Tracker - JavaScript SDK
 * Behavioral tracking for AI/LLM bot detection
 * 
 * @version 1.0.0
 * @requires WordPress 6.6+
 * @license GPL-2.0+
 */

(function(window, document) {
    'use strict';

    // SDK Configuration
    const config = window.ALLMT_SDK_CONFIG || {
        endpoint: '/wp-json/allmt/v1/track',
        nonce: '',
        sessionId: '',
        samplingRate: 100,
        detailedTrackingRate: 10,
        batchSize: 100,
        batchInterval: 50,
        enableMouseTracking: true,
        enableScrollTracking: true,
        enableViewportTracking: true,
        enableDOMTracking: true,
        differentialPrivacy: true,
        dpEpsilon: 1.0,
        consentRequired: true,
        maxEventsPerSession: 1000,
        debug: false
    };

    // Internal state
    const state = {
        initialized: false,
        consentGiven: false,
        eventQueue: [],
        eventCount: 0,
        sessionStartTime: Date.now(),
        lastMouseX: 0,
        lastMouseY: 0,
        lastScrollY: 0,
        lastEventTime: 0,
        viewportWidth: window.innerWidth,
        viewportHeight: window.innerHeight,
        batchTimer: null,
        isSending: false,
        mouseMoveBuffer: [],
        scrollBuffer: [],
        pageStartTime: Date.now(),
        currentPage: window.location.href,
        elementsObserved: new Set()
    };

    // Utility functions
    const utils = {
        /**
         * Generate UUID v4
         */
        uuid: function() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                const r = Math.random() * 16 | 0;
                const v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        /**
         * Add Laplace noise for differential privacy
         */
        addNoise: function(value, epsilon) {
            if (!config.differentialPrivacy || !epsilon) return value;
            const scale = 1 / epsilon;
            const u = Math.random() - 0.5;
            const noise = -scale * Math.sign(u) * Math.log(1 - 2 * Math.abs(u));
            return Math.round(value + noise);
        },

        /**
         * Throttle function execution
         */
        throttle: function(func, limit) {
            let inThrottle;
            return function(...args) {
                if (!inThrottle) {
                    func.apply(this, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        /**
         * Debounce function execution
         */
        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        /**
         * Get element path (simplified)
         */
        getElementPath: function(element) {
            if (!element || element === document.body) return '';
            const path = [];
            let current = element;
            while (current && current !== document.body && path.length < 5) {
                let selector = current.tagName.toLowerCase();
                if (current.id) {
                    selector += '#' + current.id;
                    path.unshift(selector);
                    break;
                }
                if (current.className) {
                    selector += '.' + current.className.split(' ').slice(0, 2).join('.');
                }
                path.unshift(selector);
                current = current.parentElement;
            }
            return path.join(' > ');
        },

        /**
         * Get element visibility info
         */
        getVisibilityInfo: function(element) {
            const rect = element.getBoundingClientRect();
            return {
                visible: rect.top < state.viewportHeight && rect.bottom > 0 &&
                        rect.left < state.viewportWidth && rect.right > 0,
                percentVisible: Math.round(
                    Math.max(0, Math.min(rect.bottom, state.viewportHeight) - Math.max(rect.top, 0)) *
                    Math.max(0, Math.min(rect.right, state.viewportWidth) - Math.max(rect.left, 0)) /
                    (rect.width * rect.height) * 100
                ),
                viewportX: Math.round(rect.left),
                viewportY: Math.round(rect.top)
            };
        },

        /**
         * Log debug messages
         */
        debug: function(...args) {
            if (config.debug && window.console) {
                console.log('[ALLMT]', ...args);
            }
        },

        /**
         * Compress event data (simple RLE for coordinates)
         */
        compressEvents: function(events) {
            if (!events.length) return events;
            
            const compressed = [];
            let lastEvent = null;
            
            for (const event of events) {
                if (lastEvent && 
                    event.type === lastEvent.type && 
                    event.type === 'mousemove') {
                    // Combine consecutive mouse moves
                    lastEvent.x2 = event.x;
                    lastEvent.y2 = event.y;
                    lastEvent.t2 = event.t;
                } else {
                    if (lastEvent) compressed.push(lastEvent);
                    lastEvent = { ...event };
                }
            }
            if (lastEvent) compressed.push(lastEvent);
            
            return compressed;
        }
    };

    // Event handlers
    const handlers = {
        /**
         * Handle mouse movement
         */
        mouseMove: utils.throttle(function(e) {
            if (!state.consentGiven || state.eventCount >= config.maxEventsPerSession) return;
            
            const now = Date.now();
            const x = config.differentialPrivacy ? 
                utils.addNoise(e.clientX, config.dpEpsilon) : e.clientX;
            const y = config.differentialPrivacy ? 
                utils.addNoise(e.clientY, config.dpEpsilon) : e.clientY;
            
            // Skip if position hasn't changed significantly
            if (Math.abs(x - state.lastMouseX) < 5 && Math.abs(y - state.lastMouseY) < 5) {
                return;
            }

            state.mouseMoveBuffer.push({
                type: 'mousemove',
                x: x,
                y: y,
                t: now - state.pageStartTime,
                vx: Math.round(x - state.lastMouseX),
                vy: Math.round(y - state.lastMouseY)
            });

            state.lastMouseX = x;
            state.lastMouseY = y;

            // Flush buffer if it gets too large
            if (state.mouseMoveBuffer.length >= 10) {
                handlers.flushMouseBuffer();
            }
        }, 16), // ~60fps max

        /**
         * Flush mouse movement buffer
         */
        flushMouseBuffer: function() {
            if (state.mouseMoveBuffer.length === 0) return;

            // Calculate trajectory features
            const moves = state.mouseMoveBuffer;
            let totalDistance = 0;
            let totalTime = 0;
            let directionChanges = 0;
            let lastDirection = null;

            for (let i = 1; i < moves.length; i++) {
                const dx = moves[i].x - moves[i-1].x;
                const dy = moves[i].y - moves[i-1].y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const time = moves[i].t - moves[i-1].t;
                
                totalDistance += distance;
                totalTime += time;

                const direction = Math.atan2(dy, dx);
                if (lastDirection !== null) {
                    const directionChange = Math.abs(direction - lastDirection);
                    if (directionChange > 0.5) directionChanges++;
                }
                lastDirection = direction;
            }

            const avgVelocity = totalTime > 0 ? totalDistance / totalTime : 0;
            const acceleration = moves.length > 2 ? 
                (moves[moves.length-1].vy - moves[0].vy) / (moves[moves.length-1].t - moves[0].t + 1) : 0;

            sdk.queueEvent({
                type: 'mouse_trajectory',
                data: {
                    points: moves.length,
                    distance: Math.round(totalDistance),
                    duration: totalTime,
                    avgVelocity: Math.round(avgVelocity * 100) / 100,
                    acceleration: Math.round(acceleration * 100) / 100,
                    directionChanges: directionChanges,
                    startX: moves[0].x,
                    startY: moves[0].y,
                    endX: moves[moves.length-1].x,
                    endY: moves[moves.length-1].y
                }
            });

            state.mouseMoveBuffer = [];
        },

        /**
         * Handle scroll events
         */
        scroll: utils.throttle(function() {
            if (!state.consentGiven || state.eventCount >= config.maxEventsPerSession) return;

            const now = Date.now();
            const scrollY = window.scrollY || window.pageYOffset;
            const scrollX = window.scrollX || window.pageXOffset;
            const maxScroll = document.documentElement.scrollHeight - state.viewportHeight;
            const scrollDepth = maxScroll > 0 ? Math.round((scrollY / maxScroll) * 100) : 0;
            const velocity = Math.abs(scrollY - state.lastScrollY);
            const direction = scrollY > state.lastScrollY ? 'down' : (scrollY < state.lastScrollY ? 'up' : 'none');

            state.scrollBuffer.push({
                y: scrollY,
                depth: scrollDepth,
                velocity: velocity,
                direction: direction,
                time: now - state.pageStartTime
            });

            state.lastScrollY = scrollY;

            // Flush scroll buffer periodically
            if (state.scrollBuffer.length >= 5) {
                handlers.flushScrollBuffer();
            }

            // Track scroll depth milestones
            const milestones = [25, 50, 75, 90, 100];
            for (const milestone of milestones) {
                const key = 'scroll_milestone_' + milestone;
                if (scrollDepth >= milestone && !state.elementsObserved.has(key)) {
                    state.elementsObserved.add(key);
                    sdk.queueEvent({
                        type: 'scroll_milestone',
                        data: { depth: milestone, timeToReach: now - state.pageStartTime }
                    });
                }
            }
        }, 100),

        /**
         * Flush scroll buffer
         */
        flushScrollBuffer: function() {
            if (state.scrollBuffer.length === 0) return;

            const scrolls = state.scrollBuffer;
            const velocities = scrolls.map(s => s.velocity);
            const avgVelocity = velocities.reduce((a, b) => a + b, 0) / velocities.length;
            const maxVelocity = Math.max(...velocities);
            const directionChanges = scrolls.filter((s, i) => 
                i > 0 && s.direction !== scrolls[i-1].direction && s.direction !== 'none'
            ).length;

            sdk.queueEvent({
                type: 'scroll_behavior',
                data: {
                    events: scrolls.length,
                    avgVelocity: Math.round(avgVelocity),
                    maxVelocity: maxVelocity,
                    directionChanges: directionChanges,
                    finalDepth: scrolls[scrolls.length-1].depth,
                    totalDistance: Math.abs(scrolls[scrolls.length-1].y - scrolls[0].y)
                }
            });

            state.scrollBuffer = [];
        },

        /**
         * Handle click events
         */
        click: function(e) {
            if (!state.consentGiven || state.eventCount >= config.maxEventsPerSession) return;

            const element = e.target;
            const rect = element.getBoundingClientRect();
            
            sdk.queueEvent({
                type: 'click',
                data: {
                    tag: element.tagName,
                    id: element.id || null,
                    class: element.className || null,
                    text: element.textContent ? element.textContent.substring(0, 100) : null,
                    href: element.href || null,
                    x: Math.round(e.clientX),
                    y: Math.round(e.clientY),
                    elementX: Math.round(e.clientX - rect.left),
                    elementY: Math.round(e.clientY - rect.top),
                    path: utils.getElementPath(element)
                }
            });
        },

        /**
         * Handle form interactions
         */
        formInteraction: function(e) {
            if (!state.consentGiven || state.eventCount >= config.maxEventsPerSession) return;
            if (e.target.type === 'password') return; // Never track passwords

            const element = e.target;
            
            sdk.queueEvent({
                type: 'form_interaction',
                data: {
                    action: e.type,
                    tag: element.tagName,
                    type: element.type || null,
                    name: element.name || null,
                    id: element.id || null,
                    hasValue: element.value && element.value.length > 0
                }
            });
        },

        /**
         * Handle visibility change
         */
        visibilityChange: function() {
            sdk.queueEvent({
                type: 'visibility_change',
                data: {
                    hidden: document.hidden,
                    timeOnPage: Date.now() - state.pageStartTime
                }
            });
        },

        /**
         * Handle page unload
         */
        pageUnload: function() {
            handlers.flushMouseBuffer();
            handlers.flushScrollBuffer();

            sdk.queueEvent({
                type: 'page_exit',
                data: {
                    timeOnPage: Date.now() - state.pageStartTime,
                    scrollDepth: Math.round((window.scrollY / (document.documentElement.scrollHeight - state.viewportHeight)) * 100) || 0,
                    totalEvents: state.eventCount
                }
            });

            // Try to send remaining events
            if (state.eventQueue.length > 0) {
                sdk.sendEvents(true);
            }
        },

        /**
         * Handle resize events
         */
        resize: utils.debounce(function() {
            state.viewportWidth = window.innerWidth;
            state.viewportHeight = window.innerHeight;
            
            sdk.queueEvent({
                type: 'viewport_resize',
                data: {
                    width: state.viewportWidth,
                    height: state.viewportHeight
                }
            });
        }, 250)
    };

    // Main SDK object
    const sdk = {
        /**
         * Initialize the SDK
         */
        init: function() {
            if (state.initialized) return;

            utils.debug('Initializing Advanced LLM Tracker SDK');

            // Check for known bot user agents (don't track these)
            if (sdk.isKnownBot()) {
                utils.debug('Known bot detected, skipping SDK initialization');
                return;
            }

            // Check consent
            if (config.consentRequired) {
                state.consentGiven = sdk.checkConsent();
                if (!state.consentGiven) {
                    utils.debug('Consent not given, waiting for consent');
                    sdk.waitForConsent();
                    return;
                }
            } else {
                state.consentGiven = true;
            }

            sdk.setupEventListeners();
            sdk.startBatchTimer();
            
            state.initialized = true;

            // Send initial page view
            sdk.queueEvent({
                type: 'page_view',
                data: {
                    url: window.location.href,
                    path: window.location.pathname,
                    referrer: document.referrer,
                    title: document.title,
                    viewport: {
                        width: state.viewportWidth,
                        height: state.viewportHeight
                    },
                    screen: {
                        width: window.screen.width,
                        height: window.screen.height
                    },
                    timestamp: Date.now()
                }
            });

            utils.debug('SDK initialized successfully');
        },

        /**
         * Check if current user agent is a known bot
         */
        isKnownBot: function() {
            const botPatterns = [
                /bot/i, /crawler/i, /spider/i, /scraper/i, /curl/i,
                /wget/i, /python/i, /java\//i, /httpclient/i,
                /gptbot/i, /claudebot/i, /googlebot/i, /bingbot/i
            ];
            const ua = navigator.userAgent;
            return botPatterns.some(pattern => pattern.test(ua));
        },

        /**
         * Check if user has given consent
         */
        checkConsent: function() {
            // Check for WordPress consent API
            if (window.wpConsentApi && window.wpConsentApi.consent) {
                return window.wpConsentApi.consent('statistics');
            }
            
            // Check for CookieYes
            if (window.cookieyes && window.cookieyes._ckyConsent) {
                return window.cookieyes._ckyConsent.statistics === 'yes';
            }
            
            // Check for custom consent cookie
            const match = document.cookie.match(/allmt_consent=([^;]+)/);
            if (match) {
                return match[1] === 'granted';
            }

            return false;
        },

        /**
         * Wait for consent to be given
         */
        waitForConsent: function() {
            // Listen for consent changes
            document.addEventListener('wp_consent_api_changed', function() {
                state.consentGiven = sdk.checkConsent();
                if (state.consentGiven && !state.initialized) {
                    sdk.init();
                }
            });

            // Check periodically
            const checkInterval = setInterval(function() {
                state.consentGiven = sdk.checkConsent();
                if (state.consentGiven) {
                    clearInterval(checkInterval);
                    if (!state.initialized) {
                        sdk.init();
                    }
                }
            }, 1000);

            // Stop checking after 30 seconds
            setTimeout(function() {
                clearInterval(checkInterval);
            }, 30000);
        },

        /**
         * Setup event listeners
         */
        setupEventListeners: function() {
            // Mouse tracking (only if enabled and sampled)
            if (config.enableMouseTracking && Math.random() * 100 < config.detailedTrackingRate) {
                document.addEventListener('mousemove', handlers.mouseMove, { passive: true });
                document.addEventListener('mouseenter', handlers.mouseMove, { passive: true });
            }

            // Scroll tracking
            if (config.enableScrollTracking) {
                window.addEventListener('scroll', handlers.scroll, { passive: true });
            }

            // Click tracking
            document.addEventListener('click', handlers.click, { passive: true });

            // Form interactions
            document.addEventListener('focus', handlers.formInteraction, { passive: true });
            document.addEventListener('blur', handlers.formInteraction, { passive: true });
            document.addEventListener('change', handlers.formInteraction, { passive: true });

            // Visibility
            document.addEventListener('visibilitychange', handlers.visibilityChange);

            // Page unload
            window.addEventListener('beforeunload', handlers.pageUnload);
            window.addEventListener('pagehide', handlers.pageUnload);

            // Resize
            window.addEventListener('resize', handlers.resize, { passive: true });

            // Intersection Observer for viewport tracking
            if (config.enableViewportTracking && 'IntersectionObserver' in window) {
                sdk.setupIntersectionObserver();
            }
        },

        /**
         * Setup Intersection Observer for element visibility
         */
        setupIntersectionObserver: function() {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: [0, 0.1, 0.25, 0.5, 0.75, 1.0]
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    const element = entry.target;
                    const key = element.tagName + (element.id ? '#' + element.id : '');
                    
                    if (entry.isIntersecting && !state.elementsObserved.has(key)) {
                        state.elementsObserved.add(key);
                        
                        sdk.queueEvent({
                            type: 'element_visible',
                            data: {
                                tag: element.tagName,
                                id: element.id || null,
                                class: element.className || null,
                                intersectionRatio: Math.round(entry.intersectionRatio * 100) / 100,
                                timeToVisible: Date.now() - state.pageStartTime
                            }
                        });
                    }
                });
            }, observerOptions);

            // Observe key elements
            const elementsToObserve = document.querySelectorAll(
                'h1, h2, h3, article, main, [data-track-visible]'
            );
            elementsToObserve.forEach(function(el) {
                observer.observe(el);
            });
        },

        /**
         * Queue an event for batching
         */
        queueEvent: function(event) {
            if (state.eventCount >= config.maxEventsPerSession) return;

            event.sessionId = config.sessionId;
            event.pageUrl = state.currentPage;
            event.timestamp = Date.now();

            state.eventQueue.push(event);
            state.eventCount++;

            // Send immediately if batch is full
            if (state.eventQueue.length >= config.batchSize) {
                sdk.sendEvents();
            }
        },

        /**
         * Start the batch timer
         */
        startBatchTimer: function() {
            state.batchTimer = setInterval(function() {
                if (state.eventQueue.length > 0) {
                    sdk.sendEvents();
                }
            }, config.batchInterval);
        },

        /**
         * Send queued events to server
         */
        sendEvents: function(sync = false) {
            if (state.isSending || state.eventQueue.length === 0) return;

            state.isSending = true;
            const events = state.eventQueue.splice(0, config.batchSize);

            const payload = {
                session_id: config.sessionId,
                events: events,
                batch_time: Date.now()
            };

            const fetchOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': config.nonce,
                    'X-ALLMT-Session': config.sessionId
                },
                body: JSON.stringify(payload),
                keepalive: true
            };

            if (sync) {
                // Use sendBeacon for synchronous send
                const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                navigator.sendBeacon(config.endpoint, blob);
                state.isSending = false;
            } else {
                fetch(config.endpoint, fetchOptions)
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('HTTP ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function(data) {
                        utils.debug('Events sent successfully:', data);
                    })
                    .catch(function(error) {
                        utils.debug('Error sending events:', error);
                        // Re-queue events on failure (limited retries)
                        if (events.length < config.batchSize) {
                            state.eventQueue.unshift(...events);
                        }
                    })
                    .finally(function() {
                        state.isSending = false;
                    });
            }
        },

        /**
         * Grant consent manually
         */
        grantConsent: function() {
            state.consentGiven = true;
            document.cookie = 'allmt_consent=granted; path=/; max-age=31536000; SameSite=Lax';
            if (!state.initialized) {
                sdk.init();
            }
        },

        /**
         * Revoke consent
         */
        revokeConsent: function() {
            state.consentGiven = false;
            document.cookie = 'allmt_consent=denied; path=/; max-age=31536000; SameSite=Lax';
        },

        /**
         * Get current session stats
         */
        getStats: function() {
            return {
                eventCount: state.eventCount,
                timeOnPage: Date.now() - state.pageStartTime,
                scrollDepth: Math.round((window.scrollY / (document.documentElement.scrollHeight - state.viewportHeight)) * 100) || 0,
                viewport: {
                    width: state.viewportWidth,
                    height: state.viewportHeight
                },
                consentGiven: state.consentGiven,
                initialized: state.initialized
            };
        },

        /**
         * Destroy the SDK
         */
        destroy: function() {
            if (state.batchTimer) {
                clearInterval(state.batchTimer);
            }
            handlers.pageUnload();
            state.initialized = false;
        }
    };

    // Expose SDK to global scope
    window.ALLMT_SDK = sdk;

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Delay initialization to not block critical rendering
            if ('requestIdleCallback' in window) {
                requestIdleCallback(function() {
                    sdk.init();
                }, { timeout: 2000 });
            } else {
                setTimeout(sdk.init, 100);
            }
        });
    } else {
        setTimeout(sdk.init, 100);
    }

})(window, document);
