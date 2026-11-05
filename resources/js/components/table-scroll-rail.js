/**
 * Table scroll rail: the state behind filament/tables/scroll-rail, for a Filament
 * table or a hand-built one in <x-table-scroll>.
 *
 * Follows the table's own scroller (`.fi-ta-content-ctn`, `.ui-table-scroller`),
 * which stays the only thing that scrolls. Livewire can replace the scroller or
 * its contents on any re-render, so a mutation observer re-checks them. While
 * the table overflows the scroller is a named, focusable region, so a keyboard
 * can scroll a table with nothing focusable in it.
 *
 * Also measures the sticky column header's range on the page for the CSS
 * animation that moves it from lg up (filament-table.css), and tells stuck from resting
 * for whichever of the footer and the rail is sticking at the bottom.
 */
export const tableScrollRail = (regionLabel) => {
    // Not on the component: Alpine writes a key the data object doesn't declare
    // to the outermost scope, the app shell's, which every table would then share.
    let main;
    let page;
    let scroller;
    let content;
    let footer;
    let frame;
    let scheduleSync;
    let settleTimer;
    let syncAfterResize;
    let resizeObserver;
    let mutationObserver;
    let stuckObserver;
    let stuckTarget;

    // Not scroll-padding: it counts the frozen cell as hidden too, so tabbing through the row menus scrolls to the table's end.
    const keepFocusClearOfFrozenColumn = ({ target }) => {
        // Keyboard focus only: focus from a mouse arrives on press, and scrolling then would make the click miss.
        if (! target.matches(':focus-visible')) {
            return;
        }

        const frozenCell = target.closest('tr')?.lastElementChild;

        if (! frozenCell || frozenCell.contains(target) || getComputedStyle(frozenCell).position !== 'sticky') {
            return;
        }

        const overlap = target.getBoundingClientRect().right - frozenCell.getBoundingClientRect().left;

        if (overlap > 0) {
            scroller.scrollLeft += overlap + 8;
        }
    };

    return {
        overflows: false,
        atStart: true,
        atEnd: false,
        stuck: false,
        thumbWidth: 100,
        thumbLeft: 0,
        contentTop: 0,
        contentHeight: 0,
        footerHeight: 0,

        init() {
            main = this.$el.closest('.fi-ta-main, .ui-table-main');

            if (! main) {
                return;
            }

            page = this.$el.closest('.fi-modal, dialog') ? null : this.$el.closest('main');

            scheduleSync = () => {
                cancelAnimationFrame(frame);
                frame = requestAnimationFrame(() => this.sync());
            };
            resizeObserver = new ResizeObserver(scheduleSync);
            mutationObserver = new MutationObserver(scheduleSync);
            mutationObserver.observe(main, { childList: true, subtree: true });

            // Rotating a device can leave the header's range stale: the page reflows in
            // stages, so measure again once it has settled, and on any later page scroll.
            syncAfterResize = () => {
                scheduleSync();
                clearTimeout(settleTimer);
                settleTimer = setTimeout(scheduleSync, 400);
            };
            window.addEventListener('resize', syncAfterResize);
            page?.addEventListener('scroll', scheduleSync, { passive: true });

            this.sync();
        },

        destroy() {
            window.removeEventListener('resize', syncAfterResize);
            page?.removeEventListener('scroll', scheduleSync);
            clearTimeout(settleTimer);
            scroller?.removeEventListener('scroll', scheduleSync);
            scroller?.removeEventListener('focusin', keepFocusClearOfFrozenColumn);
            resizeObserver?.disconnect();
            mutationObserver?.disconnect();
            stuckObserver?.disconnect();
            cancelAnimationFrame(frame);
        },

        /** Point the observers at the current scroller and footer, which a re-render may have replaced. */
        bind() {
            const currentScroller = main.querySelector(':scope > .fi-ta-content-ctn, :scope > .ui-table-scroller');
            const currentFooter = main.querySelector(':scope > .fi-pagination, :scope > .daisy-table__pagination');
            const currentContent = currentScroller?.querySelector('.fi-ta-table, .fi-ta-content, .table') ?? null;

            if (currentScroller !== scroller || currentFooter !== footer || currentContent !== content) {
                scroller?.removeEventListener('scroll', scheduleSync);
                scroller?.removeEventListener('focusin', keepFocusClearOfFrozenColumn);
                currentScroller?.addEventListener('scroll', scheduleSync, { passive: true });
                currentScroller?.addEventListener('focusin', keepFocusClearOfFrozenColumn);

                resizeObserver.disconnect();
                // The page's contents too: anything above the table moves the header's range.
                [currentScroller, currentContent, currentFooter, page?.firstElementChild].filter(Boolean)
                    .forEach((element) => resizeObserver.observe(element, { box: 'border-box' }));

                scroller = currentScroller;
                content = currentContent;
                footer = currentFooter;
            }

            this.observeStuck();
        },

        /**
         * It sits 1px past the scroller's edge when stuck, so whatever clips it cuts off its bottom.
         * Two thresholds: with only 1, a footer that's 98% in view doesn't count as intersecting.
         */
        observeStuck() {
            const footerSticks = footer && getComputedStyle(footer).position === 'sticky';
            const target = footerSticks ? footer : this.$refs.rail;

            // The rail's ref only exists once its children have initialised; a later sync gets here again.
            if (! target || target === stuckTarget) {
                return;
            }

            stuckObserver?.disconnect();
            stuckTarget = target;
            this.stuck = false;

            stuckObserver = new IntersectionObserver(([entry]) => {
                this.stuck = entry.isIntersecting && entry.intersectionRect.bottom < entry.boundingClientRect.bottom - 0.5;
            }, { threshold: [0.5, 1] });
            stuckObserver.observe(target);
        },

        sync() {
            this.bind();

            if (! scroller) {
                this.overflows = false;

                return;
            }

            const { scrollLeft, scrollWidth, clientWidth, offsetTop, offsetHeight } = scroller;
            const maxScroll = scrollWidth - clientWidth;

            this.overflows = maxScroll > 1;
            this.atStart = scrollLeft <= 1;
            this.atEnd = scrollLeft >= maxScroll - 1;
            this.thumbWidth = (clientWidth / scrollWidth) * 100;
            this.thumbLeft = (scrollLeft / scrollWidth) * 100;
            this.contentTop = offsetTop;
            this.contentHeight = offsetHeight;
            this.footerHeight = footer?.offsetHeight ?? 0;

            this.syncRegion();
            this.syncHeaderRange();
        },

        syncRegion() {
            if (! this.overflows) {
                if (scroller.hasAttribute('tabindex')) {
                    ['tabindex', 'role', 'aria-label'].forEach((name) => scroller.removeAttribute(name));
                }

                return;
            }

            if (! scroller.hasAttribute('tabindex')) {
                scroller.setAttribute('role', 'region');
                scroller.setAttribute('aria-label', regionLabel);
                scroller.setAttribute('tabindex', '0');
            }
        },

        /** Where the header row starts on the page, and how far it can travel before it reaches the table's end. */
        syncHeaderRange() {
            const header = scroller.querySelector('.fi-ta-table > thead, .table > thead');

            // No box means a hidden table: it measures 0, which would follow the page's scroll.
            // Below lg <main> doesn't scroll, and there's no animation to measure for.
            if (! page || ! header?.offsetHeight || getComputedStyle(page).overflowY === 'visible') {
                return;
            }

            const start = header.getBoundingClientRect().top - page.getBoundingClientRect().top + page.scrollTop;
            const travel = `${Math.max(0, header.parentElement.offsetHeight - header.offsetHeight)}px`;
            const currentStart = parseFloat(main.style.getPropertyValue('--ui-table-header-start'));

            // With a tolerance: at a fractional zoom the measured start wanders as the page scrolls, and each restart recalculates the whole table.
            if (Math.abs(currentStart - start) < 2 && main.style.getPropertyValue('--ui-table-header-travel') === travel) {
                return;
            }

            main.style.setProperty('--ui-table-header-start', `${start}px`);
            main.style.setProperty('--ui-table-header-travel', travel);

            // Restart it, without a paint in between, so a running animation can't keep the old range.
            main.style.setProperty('--ui-table-header-animation', 'none');
            getComputedStyle(header.querySelector('th') ?? header).animationName;
            main.style.removeProperty('--ui-table-header-animation');
        },

        scrollByPage(direction) {
            if (! scroller) {
                return;
            }

            scroller.scrollBy({
                left: direction * scroller.clientWidth * 0.8,
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
            });
        },

        /** Press on the track to jump there, then drag: either way the thumb's middle follows the pointer. */
        startDrag(event) {
            if (! scroller || event.button !== 0) {
                return;
            }

            const track = this.$refs.track;
            const moveTo = (clientX) => {
                const { left, width } = track.getBoundingClientRect();
                const thumbRatio = scroller.clientWidth / scroller.scrollWidth;
                const position = (clientX - left) / width - thumbRatio / 2;

                scroller.scrollLeft = position * scroller.scrollWidth;
            };
            const move = (moveEvent) => moveTo(moveEvent.clientX);
            const stop = () => {
                track.removeEventListener('pointermove', move);
                track.removeEventListener('pointerup', stop);
                track.removeEventListener('pointercancel', stop);
            };

            track.setPointerCapture(event.pointerId);
            track.addEventListener('pointermove', move);
            track.addEventListener('pointerup', stop);
            track.addEventListener('pointercancel', stop);
            moveTo(event.clientX);
        },
    };
};

/**
 * Carry what the component sets on Filament's elements through a re-render,
 * which would otherwise strip it: the header's range, and the scroller's region
 * attributes. Restoring the range afterwards is too late: a style flush in
 * between restarts the running animation from a start of 0, and it stays there.
 */
export const keepTableScrollStateThroughRenders = () => {
    window.Livewire.hook('morph.updating', ({ el, toEl }) => {
        if (el.matches?.('.fi-ta-main, .ui-table-main')) {
            ['--ui-table-header-start', '--ui-table-header-travel'].forEach((name) => {
                const value = el.style.getPropertyValue(name);

                if (value) {
                    toEl.style.setProperty(name, value);
                }
            });
        }

        if (el.matches?.('.fi-ta-content-ctn, .ui-table-scroller') && toEl.matches?.('.fi-ta-content-ctn, .ui-table-scroller')) {
            ['tabindex', 'role', 'aria-label'].forEach((name) => {
                if (el.hasAttribute(name)) {
                    toEl.setAttribute(name, el.getAttribute(name));
                }
            });
        }
    });
};
