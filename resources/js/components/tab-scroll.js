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
        this.controls = indicatorRoot?.querySelector('.tab-scroll-controls');

        // Configurable options
        this.options = {
            leftThreshold: 1,
            rightThreshold: 1,
            ...options
        };

        // Bind once and keep the references: add/removeEventListener only match on
        // identical references, so binding again at removal time (as this used to)
        // silently leaves every listener attached.
        this.handleScroll = this.handleScroll.bind(this);
        this.handleResize = this.handleResize.bind(this);
        this.scrollLeft = this.scrollLeft.bind(this);
        this.scrollRight = this.scrollRight.bind(this);

        this.init();
    }

    init() {
        if (!this.nav) return;

        this.element.addEventListener('scroll', this.handleScroll);

        if (this.leftIndicator) {
            this.leftIndicator.addEventListener('click', this.scrollLeft);
        }

        if (this.rightIndicator) {
            this.rightIndicator.addEventListener('click', this.scrollRight);
        }

        this.handleResize();

        // Container changes and font loading can change the available space without a window resize.
        this.resizeObserver = new ResizeObserver(this.handleResize);
        this.resizeObserver.observe(this.element);
        this.resizeObserver.observe(this.nav);

        this.selectionObserver = new MutationObserver(this.handleResize);
        this.selectionObserver.observe(this.nav, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class', 'aria-current', 'aria-selected'],
        });
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
        this.scrollActiveTabIntoView();
        this.updateScrollIndicators();
    }

    updateScrollIndicators() {
        if (!this.nav || !this.controls || !this.leftIndicator || !this.rightIndicator) return;

        const isAtStart = this.element.scrollLeft <= this.options.leftThreshold;
        const isAtEnd = this.element.scrollLeft >= (this.element.scrollWidth - this.element.clientWidth - this.options.rightThreshold);
        const hasOverflow = this.element.scrollWidth > this.element.clientWidth;

        this.controls.style.visibility = hasOverflow ? 'visible' : 'hidden';
        this.leftIndicator.disabled = !hasOverflow || isAtStart;
        this.rightIndicator.disabled = !hasOverflow || isAtEnd;
    }

    scrollActiveTabIntoView() {
        const activeTab = this.nav.querySelector('[role="tab"]:is([aria-current="page"], [aria-current="true"], [aria-selected="true"], .tab-active)');

        if (!activeTab) return;

        const tabBounds = activeTab.getBoundingClientRect();
        const viewportBounds = this.element.getBoundingClientRect();
        const tabStart = tabBounds.left - viewportBounds.left + this.element.scrollLeft;
        const tabEnd = tabStart + tabBounds.width;
        const viewportStart = this.element.scrollLeft;
        const viewportEnd = viewportStart + this.element.clientWidth;

        if (tabStart < viewportStart || tabEnd > viewportEnd) {
            this.element.scrollLeft = Math.max(0, tabStart - Math.max(0, (this.element.clientWidth - tabBounds.width) / 2));
        }
    }

    scrollLeft() {
        if (!this.nav) return;

        const scrollDistance = this.getScrollDistance();

        this.element.scrollTo({
            left: this.element.scrollLeft - scrollDistance,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
        });
    }

    scrollRight() {
        if (!this.nav) return;

        const scrollDistance = this.getScrollDistance();

        this.element.scrollTo({
            left: this.element.scrollLeft + scrollDistance,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
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
        // Release listeners and observers when Livewire replaces the tab strip.
        this.element.removeEventListener('scroll', this.handleScroll);

        if (this.leftIndicator) {
            this.leftIndicator.removeEventListener('click', this.scrollLeft);
        }

        if (this.rightIndicator) {
            this.rightIndicator.removeEventListener('click', this.scrollRight);
        }

        this.resizeObserver?.disconnect();
        this.selectionObserver?.disconnect();
        clearTimeout(this.scrollTimeout);
    }
}

/** Controllers from the current render, tracked so re-init can tear them down. */
let activeControllers = [];

/**
 * Destroy any live controllers. Called before re-initialising so a fresh render
 * (e.g. livewire:navigated) doesn't stack a second set of listeners on top of
 * the old ones and leak the detached nodes their window handlers retain.
 */
export function destroyTabScrollControllers() {
    activeControllers.forEach(controller => controller.destroy());
    activeControllers = [];
}

/**
 * Initialize tab scroll controllers for all tab navigation elements. Idempotent:
 * repeated calls replace the previous controllers rather than accumulate.
 */
export function initTabScrollControllers() {
    destroyTabScrollControllers();

    const tabContainers = document.querySelectorAll('[data-tab-scroll]');

    tabContainers.forEach(container => {
        const options = {};

        // Read configuration from data attributes if needed
        if (container.dataset.leftThreshold) {
            options.leftThreshold = parseInt(container.dataset.leftThreshold, 10);
        }

        if (container.dataset.rightThreshold) {
            options.rightThreshold = parseInt(container.dataset.rightThreshold, 10);
        }

        activeControllers.push(new TabScrollController(container, options));
    });

    return activeControllers;
}
