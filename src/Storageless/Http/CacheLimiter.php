<?php

declare(strict_types=1);

namespace PSR7Sessions\Storageless\Http;

/**
 * Caching headers sent with responses using the session, mirroring PHP's `session.cache_limiter`
 *
 * Headers already set by the application are never overwritten.
 *
 * @see https://www.php.net/manual/en/function.session-cache-limiter.php
 */
enum CacheLimiter: string
{
    /**
     * Disallows caching:
     * `Expires: Thu, 19 Nov 1981 08:52:00 GMT`,
     * `Cache-Control: no-store, no-cache, must-revalidate`,
     * `Pragma: no-cache`
     */
    case NoCache = 'nocache';

    /**
     * Allows caching by the client only:
     * `Expires: Thu, 19 Nov 1981 08:52:00 GMT`,
     * `Cache-Control: private, max-age=<cache expire>`
     */
    case Private = 'private';

    /**
     * Allows caching by shared caches (CDNs, reverse proxies, etc.) too:
     * `Expires: <now + cache expire>`,
     * `Cache-Control: public, max-age=<cache expire>`
     *
     * Warning: shared caches may serve the session cookie, and thus the session, to other clients
     */
    case Public = 'public';

    /** Sends no caching header */
    case None = 'none';
}
