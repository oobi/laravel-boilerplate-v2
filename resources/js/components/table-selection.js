/**
 * Filament table selection — back/forward navigation guard
 *
 * Filament keeps row selection in Alpine-local state (`selectedRecords`), which
 * re-initialises empty on every fresh page load. Browsers, however, restore the
 * `checked` state of form controls when you return to a page via the back/forward
 * button. The two desync: the checkbox looks checked, but Filament counts it as
 * unselected — so the bulk-actions bar is missing and the next selection reports
 * the wrong count.
 *
 * Setting `autocomplete="off"` on the selection checkboxes opts them out of that
 * browser state restoration, so on back-navigation they come back unchecked —
 * matching Filament's empty selection. Applied to whatever selection checkboxes
 * exist now and re-applied as Livewire morphs new rows in (pagination, filters,
 * search) so freshly rendered checkboxes are covered too.
 *
 * As a backstop for the other restore variant — a bfcache "frozen page" restore
 * where the DOM (checked boxes) is preserved but Filament's Alpine state
 * re-initialises empty, which `autocomplete` cannot influence — a `pageshow`
 * handler resyncs: if Filament shows nothing selected (its selection indicator
 * is hidden) yet checkboxes are checked, it clears them.
 */

const SELECTION_CHECKBOX_SELECTOR =
    '.fi-ta-selection-cell input[type="checkbox"], .fi-ta-group-selection-cell input[type="checkbox"]';

const CHECKED_SELECTION_CHECKBOX_SELECTOR =
    '.fi-ta-selection-cell input[type="checkbox"]:checked, .fi-ta-group-selection-cell input[type="checkbox"]:checked';

const disableStateRestoration = (root = document) => {
    root.querySelectorAll(SELECTION_CHECKBOX_SELECTOR).forEach((checkbox) => {
        if (checkbox.autocomplete !== 'off') {
            checkbox.autocomplete = 'off';
        }
    });
};

/**
 * Uncheck restored checkboxes on any table whose selection Filament considers
 * empty (its `.fi-ta-selection-indicator` is hidden). A table with a genuinely
 * preserved selection keeps its indicator visible and is left untouched.
 */
const resyncCheckboxesToSelectionState = () => {
    document.querySelectorAll('.fi-ta').forEach((table) => {
        const indicator = table.querySelector('.fi-ta-selection-indicator');
        const selectionIsEmpty = !indicator || indicator.offsetParent === null;

        if (! selectionIsEmpty) {
            return;
        }

        table
            .querySelectorAll(CHECKED_SELECTION_CHECKBOX_SELECTOR)
            .forEach((checkbox) => {
                checkbox.checked = false;
            });
    });
};

export const initTableSelectionGuard = () => {
    disableStateRestoration();

    // Cover checkboxes Livewire renders after the initial load. One document-level
    // observer handles every table on the page; guarded so repeated init calls
    // (e.g. livewire:navigated) don't stack observers.
    if (window.__tableSelectionGuardObserver) {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        const hasNewNodes = mutations.some((mutation) => mutation.addedNodes.length > 0);

        if (hasNewNodes) {
            disableStateRestoration();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    window.__tableSelectionGuardObserver = observer;

    // On a bfcache restore Filament's Alpine state settles a tick after pageshow;
    // resync once it has, so we read the real (empty) selection, not a stale frame.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            requestAnimationFrame(resyncCheckboxesToSelectionState);
        }
    });
};
