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
    new StoragelessConfig(
        JwtConfig::forSymmetricSigner(
            new Signer\Hmac\Sha256(),
            InMemory::base64Encoded('OpcMuKmoxkhzW0Y1iESpjWwL/D3UBdDauJOe742BJ5Q='), // replace this with a key of your own (see below)
        )
    )
);
```

More information on the JWT signature can be found in [`lcobucci/jwt`](https://packagist.org/packages/lcobucci/jwt)
documentation:

1. The `Configuration` object: https://lcobucci-jwt.readthedocs.io/en/stable/configuration/
2. Supported algorithms: https://lcobucci-jwt.readthedocs.io/en/stable/supported-algorithms/

### Fine-tuning

Since `PSR7Session` depends on 
[`lcobucci/jwt`](https://packagist.org/packages/lcobucci/jwt), 
[`lcobucci/clock`](https://packagist.org/packages/lcobucci/clock), and 
[`dflydev/fig-cookies`](https://packagist.org/packages/dflydev/fig-cookies),
you need to require them as well, since with this sort of setup you are explicitly using
those components:

```sh
composer require "lcobucci/jwt:^5.0"
composer require "lcobucci/clock:^3.0"
composer require "dflydev/fig-cookies:^3.0"
```

If you want to fine-tune more settings of `PSR7Session`, then simply use the
`PSR7Sessions\Storageless\Http\Configuration` API.

```php
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Http\Configuration as StoragelessConfig;

$sessionMiddleware = new SessionMiddleware(
    (new StoragelessConfig(
        JwtConfig::forAsymmetricSigner(
            new Signer\Eddsa(),
            InMemory::base64Encoded('dv6B60wqqFVDpt8+TnW7T6NtRpVQjiQP/PoqonDWBZkVboQttTfzXux+WnZeacJDcklMgyKFHVFy1C7tVDvcWA=='),
            InMemory::base64Encoded('FW6ELbU3817sflp2XmnCQ3JJTIMihR1RctQu7VQ73Fg=')
        )
    ))
        ->withIdleTimeout(1200) // in seconds
        ->withRefreshTime(60) // in seconds
);
```

### Defaults

By default, sessions generated via the `SessionMiddleware` factory methods use following parameters:

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

### Local development

When running applications locally on `http://localhost`, some settings may need to be changed to work without HTTPS support.
`Secure` cookies are *sent* to localhost on the following browsers, so the example below shouldn't be needed on these:

1. Firefox >= 75 (see [bug#1618113](https://bugzilla.mozilla.org/show_bug.cgi?id=1618113),
[Set-Cookie#Secure](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#Secure), [Cookies#restrict_access_to_cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies#restrict_access_to_cookies))
2. Chrome >= 89 (see [bug#1056543](https://bugs.chromium.org/p/chromium/issues/detail?id=1056543))

**The example below is completely insecure. It should only be used for local development.**

```php
use Dflydev\FigCookies\SetCookie;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration as JwtConfig;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Http\Configuration as StoragelessConfig;

$key = '<random key>';
return new SessionMiddleware(
    (new StoragelessConfig(
        JwtConfig::forSymmetricSigner(
            new Signer\Hmac\Sha256(),
            InMemory::base64Encoded($key),
        )
    ))
        // Override the default `__Secure-slsession` which only works on HTTPS
        ->withCookie(
            SetCookie::create('slsession')
                // Disable mandatory HTTPS
                ->withSecure(false)
                ->withHttpOnly(true)
                ->withPath('/')
        )
);
```
