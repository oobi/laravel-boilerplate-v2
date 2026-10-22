<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The proxies or load balancers whose X-Forwarded-* headers are believed,
    | so $request->ip() is the visitor's address (rate limits key on it) and
    | isSecure() sees HTTPS. Read by the framework's TrustProxies middleware.
    |
    | Unset (or blank): trust nothing, except on Laravel Cloud, and on Forge
    | and Vapor's own *.on-forge.com / *.on-vapor.com domains, which the
    | framework trusts automatically. On your own domain set it yourself.
    | "*": trust whichever proxy is calling. Only safe when the app can't be
    | reached except through that proxy, or anyone can fake their address.
    | Otherwise a comma-separated list of addresses or CIDR ranges, covering
    | every hop when proxies are chained (e.g. Cloudflare's ranges plus the
    | load balancer's).
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
