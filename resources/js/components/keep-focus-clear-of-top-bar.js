/**
 * Keyboard focus in <main>: WebKit ignores scroll-margin when it reveals a focused
 * control, and no browser knows how tall a sticky heading is, so a control reached
 * by moving up the page can end up hidden. Waits for the browser's own scroll, then
 * moves the page just enough to show it, clear of the top bar, of a table's stuck
 * column header and of any sticky heading marked data-keep-focus-clear at every width.
 *
 * A tap or click is left alone: text fields match :focus-visible however they get
 * focus, and the field shouldn't move out from under the pointer. So is anything in
 * a modal, as the page behind it is locked.
 */
const GAP = 16;

// Chromium's date and time inputs don't match :focus-visible when tabbed to.
const KEYBOARD_FOCUS = ':focus-visible, [type=time], [type=date], [type=datetime-local], [type=month], [type=week]';

let focusIsFromPointer = false;
let lastFocused = null;

const coveredDownTo = (control, main) => {
    const edge = document.querySelector('.ui-app-inner header')?.getBoundingClientRect().bottom ?? 0;

    // Only a heading earlier in the page can be stuck over the control. A later one is just further down.
    const headingsAbove = [...main.querySelectorAll('[data-keep-focus-clear]')]
        .filter((heading) => ! heading.contains(control) && heading.compareDocumentPosition(control) & Node.DOCUMENT_POSITION_FOLLOWING);

    // A row's table header follows the page from lg up. Its cells move, not the thead, so measure a cell.
    const tableHeader = control.closest('tbody')?.closest('table')?.querySelector(':scope > thead th');

    return Math.max(edge, tableHeader?.getBoundingClientRect().bottom ?? 0, ...headingsAbove.map((heading) => heading.getBoundingClientRect().bottom));
};

const revealIfCovered = (control) => {
    const main = control.closest('.ui-app main');

    if (! main || document.activeElement !== control) {
        return;
    }

    const coveredTo = coveredDownTo(control, main);
    const top = control.getBoundingClientRect().top;

    if (top < coveredTo) {
        window.scrollBy({ top: top - coveredTo - GAP });
    }
};

export const initKeepFocusClearOfTopBar = () => {
    // A press's focus lands before its click, which ends it. iOS's previous and next keys fire nothing, so they aren't mistaken for a press.
    document.addEventListener('pointerdown', () => { focusIsFromPointer = true; }, true);
    ['click', 'pointercancel', 'keydown'].forEach((type) => {
        document.addEventListener(type, () => { focusIsFromPointer = false; }, true);
    });

    document.addEventListener('focusin', ({ target }) => {
        // The same control again is the window coming back to the front, not a move.
        const isReturning = target === lastFocused;
        lastFocused = target;

        if (! isReturning && ! focusIsFromPointer && target.matches?.(KEYBOARD_FOCUS) && ! target.closest('dialog:modal, .fi-modal')) {
            setTimeout(() => revealIfCovered(target), 50);
        }
    });
};
