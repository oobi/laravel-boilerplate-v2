<?php

return [

    /**
     * The session key used to store the original user id.
     */
    'session_key' => 'impersonated_by',

    /**
     * The session key used to stored the original user guard.
     */
    'session_guard' => 'impersonator_guard',

    /**
     * The session key used to stored what guard is impersonator using.
     */
    'session_guard_using' => 'impersonator_guard_using',

    /**
     * The default impersonator guard used.
     */
    'default_impersonator_guard' => 'web',

    /**
     * The URI to redirect after taking an impersonation.
     *
     * A route name (resolved to the right URL in either routing mode). The trigger
     * that starts impersonation decides where to land (its successRedirectUrl, see
     * App\Actions\Impersonation\StartImpersonation), so this is only a fallback.
     */
    'take_redirect_to' => 'dashboard',

    /**
     * The URI to redirect after leaving an impersonation.
     *
     * A route name. The ultimate fallback: ImpersonationController::leave() prefers
     * the origin StartImpersonation records in the session; this is used only when
     * that's absent.
     */
    'leave_redirect_to' => 'users.index',

];
