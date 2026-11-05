/**
 * Keyboard focus below lg, where the document scrolls under the sticky top bar:
 * WebKit ignores scroll-margin when it reveals a focused control, so one reached
 * by moving up the page can end up hidden behind the bar. Waits for the browser's
 * own scroll, then moves the page just enough to show it.
 */
const GAP = 16;

const revealIfUnderTopBar = (control) => {
    const main = control.closest('.ui-app main');

    if (! main || getComputedStyle(main).overflowY !== 'visible' || document.activeElement !== control) {
        return;
    }

    const barBottom = document.querySelector('.ui-app-inner header')?.getBoundingClientRect().bottom ?? 0;
    const top = control.getBoundingClientRect().top;

    if (top < barBottom) {
        window.scrollBy({ top: top - barBottom - GAP });
    }
};

export const initKeepFocusClearOfTopBar = () => {
    document.addEventListener('focusin', ({ target }) => {
        if (target.matches?.(':focus-visible')) {
            setTimeout(() => revealIfUnderTopBar(target), 50);
        }
    });
};
