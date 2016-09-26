## Configuring PSR7Session

In most HTTPS-based setups, PSR7Session can be initialized with some sane
defaults.

#### Symmetric key

You can set up symmetric key based signatures via the
`PSR7Sessions::fromSymmetricKeyDefaults` named constructor:

```php
use PSR7Sessions\Storageless\Http\SessionMiddleware;

$sessionMiddleware = SessionMiddleware::fromSymmetricKeyDefaults(
    'OpcMuKmoxkhzW0Y1iESpjWwL/D3UBdDauJOe742BJ5Q=', // replace this with a key of your own (see below)
    1200                                            // session lifetime, in seconds
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
use PSR7Sessions\Storageless\Http\SessionMiddleware;

$sessionMiddleware = SessionMiddleware::fromAsymmetricKeyDefaults(
    file_get_contents('/path/to/private_key.pem'),
    file_get_contents('/path/to/public_key.pem'),
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

Since `PSR7Session` depends on [`lcobucci/jwt`](https://packagist.org/packages/lcobucci/jwt)
and [`dflydev/fig-cookies`](https://packagist.org/packages/dflydev/fig-cookies),
you need to require them as well, since with this sort of setup you are explicitly using
those components:

```sh
composer require "lcobucci/jwt:~3.1"
composer require "dflydev/fig-cookies:^1.0.1"
```

If you want to fine-tune more settings of `PSR7Session`, then simply use the
`PSR7Sessions\Storageless\Http\SessionMiddleware` constructor.

```php
use PSR7Sessions\Storageless\Http\SessionMiddleware;

// a blueprint of the cookie that `PSR7Session` should use to generate
// and read cookies:
$cookieBlueprint   = \Dflydev\FigCookies\SetCookie::create('cookie-name');
$sessionMiddleware = new SessionMiddleware(
    $signer, // an \Lcobucci\JWT\Signer
    'signature key contents',
    'verification key contents',
    $cookieBlueprint,
    new \Lcobucci\JWT\Parser(),
    1200, // session lifetime, in seconds
    new \PSR7Sessions\Storageless\Time\SystemCurrentTime(), // Current time provider implementation using current system time
    60    // session automatic refresh time, in seconds
);
```

It is recommended not to use this setup

### Defaults

By default, sessions generated via the `SessionMiddleware` use following parameters:

 * `"slsession"` is the name of the cookie where the session is stored
 * `"slsession"` cookie is configured as [`HttpOnly`](https://www.owasp.org/index.php/HttpOnly)
 * `"slsession"` cookie is configured as [`secure`](https://www.owasp.org/index.php/SecureFlag)
 * The `"slsession"` cookie will contain a [JWT token](http://jwt.io/)
 * The JWT token in the `"slsession"` is signed, but **unencrypted**
 * The JWT token in the `"slsession"` has an [`iat` claim](https://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#rfc.section.4.1.6)
 * The session is re-generated only after `60` seconds, and **not** at every user-agent interaction
