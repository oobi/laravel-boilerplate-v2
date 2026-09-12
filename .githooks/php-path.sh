#!/bin/sh
# Sourced by every hook in this directory. Do not run directly.
#
# A GUI/IDE git client can launch a hook with a minimal PATH that omits the
# usual PHP locations, so `php` — and thus `vendor/bin/pint` — is not found and
# the hook aborts with a misleading error. We prepend the common install paths
# and keep the caller's $PATH intact so hooks work however git was triggered,
# on any OS:
#   - macOS: GUI apps inherit a stripped launchd PATH without Homebrew/Herd.
#   - Windows: hooks run under Git Bash, which inherits the Windows system PATH
#     where php normally lives; the *nix paths below simply don't exist and are
#     skipped. Add your php dir here (MSYS form, e.g. /c/herd/bin) if it isn't
#     already on that PATH.
export PATH="/opt/homebrew/bin:/usr/local/bin:$HOME/Library/Application Support/Herd/bin:$HOME/.config/herd/bin:$PATH"

require_php() {
    if ! command -v php >/dev/null 2>&1; then
        echo "Aborted: php was not found on PATH."
        echo "Run git from a terminal where php is available, or add its location to .githooks/php-path.sh."
        exit 1
    fi
}
