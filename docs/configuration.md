## Configuring StorageLessSession

In most HTTPS-based setups, StorageLessSession can be initialized with some sane
defaults.

#### Symmetric key

You can set up symmetric key based signatures via the
`StorageLessSession::fromSymmetricKeyDefaults` named constructor:

```php
use StoragelessSession\Http\StorageLessSession;

$sessionMiddleware = StorageLessSession::fromSymmetricKeyDefaults(
    'contents of the symmetric key', // symmetric key
    1200                             // session lifetime, in seconds
);
```

Please use a fairly long symmetric key: it is suggested to use a
[pseudorandom number generator](https://en.wikipedia.org/wiki/Cryptographically_secure_pseudorandom_number_generator)
to achieve that.

In this example, we just used a manually typed-in string for the sake
of explicitness.

#### Asymmetric key

You can set up symmetric key based signatures via the
`StorageLessSession::fromAsymmetricKeyDefaults` named constructor:

```php
use StoragelessSession\Http\StorageLessSession;

$sessionMiddleware = StorageLessSession::fromAsymmetricKeyDefaults(
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

`StorageLessSession` will only parse and regenerate the sessions lazily, when strictly
needed, therefore performance shouldn't be a problem for most setups.

### Defaults

By default, sessions generated via the `SessionMiddleware` use following parameters:

 * `"slsession"` is the name of the cookie where the session is stored
 * `"slsession"` cookie is configured as [`HttpOnly`](https://www.owasp.org/index.php/HttpOnly)
 * `"slsession"` cookie is configured as [`secure`](https://www.owasp.org/index.php/SecureFlag)
 * The `"slsession"` cookie will contain a [JWT token](http://jwt.io/)
 * The JWT token in the `"slsession"` is signed, but **unencrypted**
 * The JWT token in the `"slsession"` has an [`iat` claim](https://self-issued.info/docs/draft-ietf-oauth-json-web-token.html#rfc.section.4.1.6)
 * The session is re-generated only after `60` seconds, and **not** at every user-agent interaction
