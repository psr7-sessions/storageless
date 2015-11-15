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
    1200                             // session lifetime
);
```

Please use a fairly long symmetric key: it is suggested to use a
[pseudorandom number generator](https://en.wikipedia.org/wiki/Cryptographically_secure_pseudorandom_number_generator)
to achieve that.

In this example, we just used a manually typed-in string for the sake
of explicitness.

