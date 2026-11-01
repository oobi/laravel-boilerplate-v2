/**
 * Livewire updates refused for lost access: reload instead of the error overlay
 *
 * Every Livewire update re-checks the page's access (persistent middleware and
 * each page's `booted()`), so someone removed from a team, whose role lost the
 * page or whose team was deactivated gets a 403 on their next click. Livewire
 * would show it as a full-screen overlay of the error page
 * (GitHub concise-dot-digital/laravel-boilerplate-v2#5).
 *
 * Instead the page reloads and the server decides what they see: the 403 page
 * with a way home, or the page itself if only that one action was refused.
 * Sign-outs don't come through here: Livewire follows the redirect to login
 * itself, and a deleted session is a 419, which keeps Livewire's own
 * "refresh?" prompt so unsaved text can be copied first. Other errors keep
 * Livewire's overlay. 401 is included for an API-style auth failure, though
 * the app's own middleware redirects instead.
 *
 * A request that fires by itself (polling, a lazy component, a deferred table)
 * and is refused while the page still loads would reload forever, so a second
 * refusal of the same page within a few seconds shows the overlay instead.
 */

const ACCESS_LOST = [401, 403];
const STORAGE_KEY = 'livewire-access-reload';
const LOOP_WINDOW_MS = 10000;

// sessionStorage can be unavailable (private mode, blocked storage): fail open.
const lastReload = () => {
    try {
        return JSON.parse(window.sessionStorage.getItem(STORAGE_KEY) ?? 'null');
    } catch {
        return null;
    }
};

const rememberReload = (reload) => {
    try {
        window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(reload));
    } catch {
        // Without storage there's no loop guard; the reload still happens.
    }
};

const reloadWhenAccessIsLost = () => {
    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (!ACCESS_LOST.includes(response.status)) {
                return;
            }

            const reload = lastReload();

            // Refused again straight after reloading this page: let Livewire's overlay show once.
            if (reload !== null && reload.url === window.location.href && Date.now() - reload.at < LOOP_WINDOW_MS) {
                rememberReload(null);

                return;
            }

            preventDefault();
            rememberReload({ url: window.location.href, at: Date.now() });
            window.location.reload();
        });
    });
};

export const initLivewireAccessHandling = () => {
    // app.js is a deferred module, so Livewire is normally already running by the time it loads.
    if (window.Livewire) {
        reloadWhenAccessIsLost();
    } else {
        document.addEventListener('livewire:init', reloadWhenAccessIsLost, { once: true });
    }
};
