## Configuring PSR7Session

In most HTTPS-based setups, PSR7Session can be initialized with some sane
defaults.

#### Symmetric key

You can set up symmetric key based signatures via the
`PSR7Sessions::fromSymmetricKeyDefaults` named constructor:

```php
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;

$sessionMiddleware = SessionMiddleware::fromSymmetricKeyDefaults(
    InMemory::base64Encoded('OpcMuKmoxkhzW0Y1iESpjWwL/D3UBdDauJOe742BJ5Q='), // replace this with a key of your own (see below)
    1200 // session lifetime, in seconds
);
```

Please use a fairly long symmetric key: it is suggested to use a
[cryptographically secure pseudo-random number generator (CSPRNG)](https://en.wikipedia.org/wiki/Cryptographically_secure_pseudorandom_number_generator),
such as the [CryptoKey tool](https://github.com/AndrewCarterUK/CryptoKey),
for this purpose.

#### Asymmetric key

You can set up symmetric key based signatures via the
`PSR7Sessions::fromAsymmetricKeyDefaults` named constructor:

```php
use Lcobucci\JWT\Signer\Key\LocalFileReference;
use PSR7Sessions\Storageless\Http\SessionMiddleware;

$sessionMiddleware = SessionMiddleware::fromRsaAsymmetricKeyDefaults(
    LocalFileReference::file('/path/to/private_key.pem'),
    LocalFileReference::file('/path/to/public_key.pem'),
    1200 // session lifetime, in seconds
);
```

You can generate a private and a public key with [GPG](https://www.gnupg.org/), via:

```sh
gpg --gen-key
```

Beware that asymmetric key signatures are more resource-greedy, and therefore
you may have higher CPU usage.

`PSR7Session` will only parse and regenerate the sessions lazily, when strictly
needed, therefore performance shouldn't be a problem for most setups.

### Fine-tuning

Since `PSR7Session` depends on 
[`lcobucci/jwt`](https://packagist.org/packages/lcobucci/jwt), 
[`lcobucci/clock`](https://packagist.org/packages/lcobucci/clock), and 
[`dflydev/fig-cookies`](https://packagist.org/packages/dflydev/fig-cookies),
you need to require them as well, since with this sort of setup you are explicitly using
those components:

```sh
composer require "lcobucci/jwt:^4.1"
composer require "lcobucci/clock:^2.0"
composer require "dflydev/fig-cookies:^3.0"
```

If you want to fine-tune more settings of `PSR7Session`, then simply use the
`PSR7Sessions\Storageless\Http\SessionMiddleware` constructor.

```php
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;

$sessionMiddleware = new SessionMiddleware(
    Configuration::forAsymmetricSigner(
        new Signer\Eddsa(),
        InMemory::base64Encoded('dv6B60wqqFVDpt8+TnW7T6NtRpVQjiQP/PoqonDWBZkVboQttTfzXux+WnZeacJDcklMgyKFHVFy1C7tVDvcWA=='),
        InMemory::base64Encoded('FW6ELbU3817sflp2XmnCQ3JJTIMihR1RctQu7VQ73Fg=')
    ),
    SessionMiddleware::buildDefaultCookie(),
    1200, // session lifetime, in seconds
    SystemClock::fromSystemTimezone(),
    60    // session automatic refresh time, in seconds
);
```

It is recommended not to use this setup.

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

When running applications locally on `http://localhost`, some settings must be changed to work without HTTPS support.

**The example below is completely insecure. It should only be used for local development.**

```php
use Dflydev\FigCookies\SetCookie;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use PSR7Sessions\Storageless\Http\SessionMiddleware;

$key = '<random key>';
return new SessionMiddleware(
    Configuration::forSymmetricSigner(
        new Signer\Hmac\Sha256(),
        InMemory::plainText($key)
    ),
    // Override the default `__Secure-slsession` which only works on HTTPS
    SetCookie::create('slsession')
        // Disable mandatory HTTPS
        ->withSecure(false)
        ->withHttpOnly(true)
        ->withPath('/'),
    1200, // session lifetime, in seconds
    SystemClock::fromUTC(),
);
```
