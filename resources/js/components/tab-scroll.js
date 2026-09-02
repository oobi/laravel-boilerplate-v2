/**
 * Tab Navigation Scroll Controller
 * Handles horizontal scrolling and scroll indicators for tab navigation components
 * with data-tab-scroll attribute
 */
export class TabScrollController {
    constructor(element, options = {}) {
        this.element = element;
        this.nav = element.querySelector('[role="tablist"]');
        const indicatorRoot = element.parentElement;
        this.leftIndicator = indicatorRoot?.querySelector('.tab-scroll-left');
        this.rightIndicator = indicatorRoot?.querySelector('.tab-scroll-right');

        // Configurable options
        this.options = {
            leftThreshold: 40,
            rightThreshold: 10,
            updateDelay: 100,
            ...options
        };

        this.init();
    }

    init() {
        if (!this.nav) return;

        // Bind event listeners
        this.element.addEventListener('scroll', this.handleScroll.bind(this));

        if (this.leftIndicator) {
            this.leftIndicator.addEventListener('click', this.scrollLeft.bind(this));
        }

        if (this.rightIndicator) {
            this.rightIndicator.addEventListener('click', this.scrollRight.bind(this));
        }

        // Wait for the tab layout before revealing the active tab and indicators.
        setTimeout(() => {
            this.scrollActiveTabIntoView();
            this.updateScrollIndicators();
        }, this.options.updateDelay);

        // Update on window resize
        window.addEventListener('resize', this.handleResize.bind(this));
    }

    handleScroll() {
        // Throttle scroll updates
        if (this.scrollTimeout) return;

        this.scrollTimeout = setTimeout(() => {
            this.updateScrollIndicators();
            this.scrollTimeout = null;
        }, 16); // ~60fps
    }

    handleResize() {
        // Debounce resize updates
        if (this.resizeTimeout) {
            clearTimeout(this.resizeTimeout);
        }

        this.resizeTimeout = setTimeout(() => {
            this.updateScrollIndicators();
        }, this.options.updateDelay);
    }

    updateScrollIndicators() {
        if (!this.nav || !this.leftIndicator || !this.rightIndicator) return;

        const isAtStart = this.element.scrollLeft <= this.options.leftThreshold;
        const isAtEnd = this.element.scrollLeft >= (this.element.scrollWidth - this.element.clientWidth - this.options.rightThreshold);
        const hasOverflow = this.element.scrollWidth > this.element.clientWidth;

        // Only show indicators if there's actually overflow
        if (!hasOverflow) {
            this.leftIndicator.style.display = 'none';
            this.rightIndicator.style.display = 'none';
            return;
        }

        // Show/hide indicators based on scroll position and thresholds
        this.leftIndicator.style.display = isAtStart ? 'none' : 'flex';
        this.rightIndicator.style.display = isAtEnd ? 'none' : 'flex';
    }

    scrollActiveTabIntoView() {
        const activeTab = this.nav.querySelector('[role="tab"][aria-current="page"], [role="tab"].tab-active');

        if (!activeTab) return;

        const tabStart = activeTab.offsetLeft;
        const tabEnd = tabStart + activeTab.offsetWidth;
        const viewportStart = this.element.scrollLeft;
        const viewportEnd = viewportStart + this.element.clientWidth;

        if (tabStart < viewportStart || tabEnd > viewportEnd) {
            const scrollLeft = Math.max(0, tabStart - (this.element.clientWidth - activeTab.offsetWidth) / 2);
            const previousBehavior = this.element.style.scrollBehavior;

            this.element.style.scrollBehavior = 'auto';
            this.element.scrollLeft = scrollLeft;
            this.element.style.scrollBehavior = previousBehavior;
        }
    }

    scrollLeft() {
        if (!this.nav) return;

        const scrollDistance = this.getScrollDistance();

        this.element.scrollTo({
            left: this.element.scrollLeft - scrollDistance,
            behavior: 'smooth'
        });
    }

    scrollRight() {
        if (!this.nav) return;

        const scrollDistance = this.getScrollDistance();

        this.element.scrollTo({
            left: this.element.scrollLeft + scrollDistance,
            behavior: 'smooth'
        });
    }

    getScrollDistance() {
        // Calculate scroll distance (approximately one tab width)
        const tab = this.nav.querySelector('[role="tab"]');
        const tabWidth = tab?.offsetWidth || 120;
        const gap = parseInt(getComputedStyle(this.nav).gap, 10) || 16;
        return tabWidth + gap;
    }

    destroy() {
        // Clean up event listeners
        if (this.nav) {
            this.element.removeEventListener('scroll', this.handleScroll.bind(this));
        }

        if (this.leftIndicator) {
            this.leftIndicator.removeEventListener('click', this.scrollLeft.bind(this));
        }

        if (this.rightIndicator) {
            this.rightIndicator.removeEventListener('click', this.scrollRight.bind(this));
        }

        window.removeEventListener('resize', this.handleResize.bind(this));

        if (this.scrollTimeout) {
            clearTimeout(this.scrollTimeout);
        }

        if (this.resizeTimeout) {
            clearTimeout(this.resizeTimeout);
        }
    }
}

/**
 * Initialize tab scroll controllers for all tab navigation elements
 */
export function initTabScrollControllers() {
    const tabContainers = document.querySelectorAll('[data-tab-scroll]');
    const controllers = [];

    tabContainers.forEach(container => {
        const options = {};

        // Read configuration from data attributes if needed
        if (container.dataset.leftThreshold) {
            options.leftThreshold = parseInt(container.dataset.leftThreshold, 10);
        }

        if (container.dataset.rightThreshold) {
            options.rightThreshold = parseInt(container.dataset.rightThreshold, 10);
        }

        const controller = new TabScrollController(container, options);
        controllers.push(controller);
    });

    return controllers;
}
