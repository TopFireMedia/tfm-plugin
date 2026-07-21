/**
 * TFM Video Defer
 * Handles video container detection and deferring for Elementor and Divi
 */

(function($) {
    'use strict';

    class TFMVideoDefer {
        constructor(settings) {
            // Merge default settings with provided settings
            this.settings = {
                enabled: true,
                elementor_enabled: true,
                divi_enabled: true,
                performance: {
                    root_margin: '50px',
                    threshold: 0.1
                },
                debug: {
                    console_logging: false
                },
                ...settings
            };

            // Set default selectors if not provided
            this.settings.selectors = this.settings.selectors || {
                elementor: [
                    '.elementor-widget-video',
                    '.elementor-widget-container'
                ],
                divi: [
                    '.et_pb_video',
                    '.et_pb_module'
                ]
            };

            this.containers = new Map();
            this.observer = null;
            this.mutationObserver = null;
            this.performanceMetrics = {
                containersFound: 0,
                containersDeferred: 0,
                loadTimes: []
            };

            this.init();
        }

        init() {
            if (!this.settings.enabled) {
                this.log('Video defer is disabled');
                return;
            }

            if (!this.checkBrowserSupport()) {
                this.log('Browser does not support required features');
                return;
            }

            this.setupIntersectionObserver();
            this.setupMutationObserver();
            this.findInitialContainers();
        }

        checkBrowserSupport() {
            return 'IntersectionObserver' in window && 'MutationObserver' in window;
        }

        setupIntersectionObserver() {
            const options = {
                root: null,
                rootMargin: this.settings.performance.root_margin || '50px',
                threshold: this.settings.performance.threshold || 0.1
            };

            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadContainer(entry.target);
                    }
                });
            }, options);
        }

        setupMutationObserver() {
            // Coalesce mutations: collect added element nodes and process them
            // once per idle cycle instead of running checkNode synchronously on
            // every single DOM change (which thrashed on builder/animation pages).
            let pending = [];
            let scheduled = false;
            const flush = () => {
                scheduled = false;
                const nodes = pending;
                pending = [];
                nodes.forEach(node => this.checkNode(node));
            };
            const schedule = window.requestIdleCallback
                || window.requestAnimationFrame
                || ((cb) => setTimeout(cb, 200));

            this.mutationObserver = new MutationObserver((mutations) => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === 1) { // Element node
                            pending.push(node);
                        }
                    });
                });
                if (pending.length && !scheduled) {
                    scheduled = true;
                    schedule(flush);
                }
            });

            this.mutationObserver.observe(document.body, {
                childList: true,
                subtree: true
            });
        }

        findInitialContainers() {
            const startTime = performance.now();
            
            if (!this.settings.selectors) {
                this.log('No selectors defined');
                return;
            }
            
            Object.entries(this.settings.selectors).forEach(([builder, selectors]) => {
                if (!this.settings[`${builder}_enabled`]) return;

                selectors.forEach(selector => {
                    const containers = document.querySelectorAll(selector);
                    containers.forEach(container => {
                        if (this.isVideoContainer(container)) {
                            this.addContainer(container);
                        }
                    });
                });
            });

            const endTime = performance.now();
            this.performanceMetrics.initialScanTime = endTime - startTime;
            this.log(`Initial scan completed in ${this.performanceMetrics.initialScanTime.toFixed(2)}ms`);
        }

        checkNode(node) {
            if (node.matches) {
                Object.entries(this.settings.selectors).forEach(([builder, selectors]) => {
                    if (!this.settings[`${builder}_enabled`]) return;

                    selectors.forEach(selector => {
                        if (node.matches(selector) && this.isVideoContainer(node)) {
                            this.addContainer(node);
                        }
                    });
                });
            }

            // Check child nodes
            if (node.querySelectorAll) {
                Object.entries(this.settings.selectors).forEach(([builder, selectors]) => {
                    if (!this.settings[`${builder}_enabled`]) return;

                    selectors.forEach(selector => {
                        const containers = node.querySelectorAll(selector);
                        containers.forEach(container => {
                            if (this.isVideoContainer(container)) {
                                this.addContainer(container);
                            }
                        });
                    });
                });
            }
        }

        isVideoContainer(container) {
            // Check for YouTube iframe
            const hasYouTubeIframe = container.querySelector('iframe[src*="youtube.com"]');
            if (hasYouTubeIframe) return true;

            // Check for YouTube embed
            const hasYouTubeEmbed = container.querySelector('div[data-youtube-id]');
            if (hasYouTubeEmbed) return true;

            // Check for video element
            const hasVideo = container.querySelector('video');
            if (hasVideo) return true;

            return false;
        }

        addContainer(container) {
            if (this.containers.has(container)) return;

            this.containers.set(container, {
                loaded: false,
                addedAt: performance.now()
            });

            this.performanceMetrics.containersFound++;
            this.observer.observe(container);
            this.deferContainer(container);
        }

        deferContainer(container) {
            // Store original content
            const originalContent = container.innerHTML;
            container.setAttribute('data-tfm-original-content', originalContent);

            // Create placeholder
            const placeholder = document.createElement('div');
            placeholder.className = 'tfm-video-placeholder';
            placeholder.innerHTML = '<div class="tfm-video-placeholder-content">Loading video...</div>';

            // Clear container and add placeholder
            container.innerHTML = '';
            container.appendChild(placeholder);

            this.performanceMetrics.containersDeferred++;
        }

        loadContainer(container) {
            if (!this.containers.has(container) || this.containers.get(container).loaded) return;

            const startTime = performance.now();
            const originalContent = container.getAttribute('data-tfm-original-content');

            if (originalContent) {
                container.innerHTML = originalContent;
                this.containers.get(container).loaded = true;
                this.containers.get(container).loadedAt = performance.now();

                const loadTime = this.containers.get(container).loadedAt - startTime;
                this.performanceMetrics.loadTimes.push(loadTime);

                this.log(`Container loaded in ${loadTime.toFixed(2)}ms`);
            }
        }

        log(message) {
            if (this.settings.debug.console_logging) {
                console.log(`[TFM Video Defer] ${message}`);
            }
        }

        getPerformanceMetrics() {
            return {
                ...this.performanceMetrics,
                averageLoadTime: this.performanceMetrics.loadTimes.length > 0
                    ? this.performanceMetrics.loadTimes.reduce((a, b) => a + b) / this.performanceMetrics.loadTimes.length
                    : 0
            };
        }
    }

    // Initialize when DOM is ready
    $(document).ready(() => {
        if (typeof tfmVideoDefer !== 'undefined') {
            window.tfmVideoDeferInstance = new TFMVideoDefer(tfmVideoDefer.settings);
            if (tfmVideoDefer.settings && tfmVideoDefer.settings.debug && tfmVideoDefer.settings.debug.console_logging) {
                console.log('TFM Video Defer initialized');
            }
        }
    });

})(jQuery); 