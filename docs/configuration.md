## Configuring PSR7Session

In most HTTPS-based setups, `PSR7Session` can be initialized with some sane
defaults.

Since version 9, the only configuration needed to be explicitly set is the algorithm and key type
to sign the session with.

Here is a basic example with a symmetric key based signature:

```php
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Http\Configuration as StoragelessConfig;

$sessionMiddleware = new SessionMiddleware(
    StoragelessConfig::fromJwtConfiguration(
        JwtConfig::forSymmetricSigner(
            new Signer\Hmac\Sha256(),
            InMemory::base64Encoded((string) getenv('SESSION_SIGNING_KEY')),
        )
    )
);
```

The key must be kept secret and generated using a cryptographically secure
pseudo-random number generator (CSPRNG). **Never use a key found in documentation
or examples.** For the `Sha256` signer above, you can generate a key with:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

More information on the JWT signature can be found in [`lcobucci/jwt`](https://packagist.org/packages/lcobucci/jwt)
documentation:

1. The `Configuration` object: https://lcobucci-jwt.readthedocs.io/en/stable/configuration/
2. Supported algorithms: https://lcobucci-jwt.readthedocs.io/en/stable/supported-algorithms/

### Fine-tuning

Since the configuration API makes you use
[`lcobucci/jwt`](https://packagist.org/packages/lcobucci/jwt) and, when customizing the cookie,
[`dflydev/fig-cookies`](https://packagist.org/packages/dflydev/fig-cookies)
directly, you should require them explicitly in your own project:

```sh
composer require "lcobucci/jwt:^5.6.1"
composer require "dflydev/fig-cookies:^3.2.0"
```

If you replace the default clock via `withClock()`, you also need a
[PSR-20](https://www.php-fig.org/psr/psr-20/) clock implementation, such as
[`lcobucci/clock`](https://packagist.org/packages/lcobucci/clock).

If you want to fine-tune more settings of `PSR7Session`, then simply use the
`PSR7Sessions\Storageless\Http\Configuration` API.

```php
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Http\Configuration as StoragelessConfig;

$sessionMiddleware = new SessionMiddleware(
    StoragelessConfig::fromJwtConfiguration(
        JwtConfig::forAsymmetricSigner(
            new Signer\Eddsa(),
            InMemory::base64Encoded((string) getenv('SESSION_SIGNING_PRIVATE_KEY')),
            InMemory::base64Encoded((string) getenv('SESSION_SIGNING_PUBLIC_KEY'))
        )
    )
        ->withIdleTimeout(1200) // in seconds
        ->withRefreshTime(60) // in seconds
);
```

For the `Eddsa` signer above, you can generate a key pair with:

```sh
php -r '$k = sodium_crypto_sign_keypair(); echo "private: ", base64_encode(sodium_crypto_sign_secretkey($k)), PHP_EOL, "public:  ", base64_encode(sodium_crypto_sign_publickey($k)), PHP_EOL;'
```

### Defaults

By default, `PSR7Sessions\Storageless\Http\Configuration::fromJwtConfiguration()` uses the following parameters:

 * `"__Secure-slsession"` is the name of the cookie where the session is stored, [`__Secure-`](https://tools.ietf.org/html/draft-ietf-httpbis-cookie-prefixes)
 prefix is intentional
 * `"__Secure-slsession"` cookie is configured as [`Secure`](https://tools.ietf.org/html/rfc6265#section-4.1.2.5)
 * `"__Secure-slsession"` cookie is configured as [`HttpOnly`](https://tools.ietf.org/html/rfc6265#section-4.1.2.6)
 * `"__Secure-slsession"` cookie is configured as [`SameSite=Lax`](https://tools.ietf.org/html/draft-ietf-httpbis-cookie-same-site)
 * `"__Secure-slsession"` cookie is configured as [`path=/`](https://github.com/psr7-sessions/storageless/pull/46)
 * The `"__Secure-slsession"` cookie will contain a [JWT token](https://jwt.io/)
 * The JWT token in the `"__Secure-slsession"` is signed, but **unencrypted**
 * The JWT token in the `"__Secure-slsession"` has an [`iat` claim](https://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#rfc.section.4.1.6)
 * The session is re-generated only after `60` seconds, and **not** at every user-agent interaction
 * The session expires after `43200` seconds (12 hours) without being re-generated
 * The session is available in the `"session"` request attribute (`SessionMiddleware::SESSION_ATTRIBUTE`)
 * [Client fingerprinting](../README.md#session-hijacking-mitigation) is disabled
 * Responses using the session are sent with [`nocache` caching headers](#http-caching)

### HTTP caching

A shared cache (CDN, reverse proxy, etc.) storing a response that carries the
session cookie, or that depends on the session data, would serve it to other
clients. Like PHP's [`session.cache_limiter`](https://www.php.net/manual/en/function.session-cache-limiter.php),
the middleware sends caching headers with every response that reads or writes
the session, or that carries the session cookie (including session refreshes):

| `CacheLimiter`        | Headers                                                                                                                          |
|-----------------------|----------------------------------------------------------------------------------------------------------------------------------|
| `NoCache` (default)   | `Expires: Thu, 19 Nov 1981 08:52:00 GMT`<br>`Cache-Control: no-store, no-cache, must-revalidate`<br>`Pragma: no-cache`         |
| `Private`             | `Expires: Thu, 19 Nov 1981 08:52:00 GMT`<br>`Cache-Control: private, max-age=<cache expire>`                                    |
| `Public`              | `Expires: <now + cache expire>`<br>`Cache-Control: public, max-age=<cache expire>`                                               |
| `None`                | none                                                                                                                             |

The cache expire defaults to `10800` seconds (180 minutes), as PHP's `session.cache_expire`:

```php
use PSR7Sessions\Storageless\Http\CacheLimiter;

$sessionMiddleware = new SessionMiddleware(
    StoragelessConfig::fromJwtConfiguration(/* ... */)
        ->withCacheLimiter(CacheLimiter::Private)
        ->withCacheExpire(600) // in seconds
);
```

As with PHP, **headers set by your application are never overwritten**: each of
the headers above is only added when the response does not contain it already.
Therefore, if your application explicitly marks a response as cacheable by shared
caches (for example with `Cache-Control: public`), it must make sure that the
response does not use the session, nor carry the session cookie: since sessions
are re-generated on any request after `60` seconds, the session cookie may be
added to any response. The same applies to the `Public` cache limiter.

### Local development

When running applications locally on `http://localhost`, some settings may need to be changed to work without HTTPS support.
`Secure` cookies are *sent* to localhost on the following browsers, so the example below shouldn't be needed on these:

1. Firefox >= 75 (see [bug#1618113](https://bugzilla.mozilla.org/show_bug.cgi?id=1618113),
[Set-Cookie#Secure](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#Secure), [Cookies#restrict_access_to_cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#restrict_access_to_cookies))
2. Chrome >= 89 (see [bug#1056543](https://bugs.chromium.org/p/chromium/issues/detail?id=1056543))

**The example below is completely insecure. It should only be used for local development.**

Note that `withCookie()` replaces the whole default cookie: any attribute not set
on the given `SetCookie` (`Secure`, `HttpOnly`, `SameSite`, `Path`) is lost, so
always set all of them explicitly.

```php
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Http\Configuration as StoragelessConfig;

return new SessionMiddleware(
    StoragelessConfig::fromJwtConfiguration(
        JwtConfig::forSymmetricSigner(
            new Signer\Hmac\Sha256(),
            InMemory::base64Encoded((string) getenv('SESSION_SIGNING_KEY')),
        )
    )
        // Override the default `__Secure-slsession` which only works on HTTPS
        ->withCookie(
            SetCookie::create('slsession')
                // Disable mandatory HTTPS
                ->withSecure(false)
                ->withHttpOnly(true)
                ->withSameSite(SameSite::lax())
                ->withPath('/')
        )
);
```
