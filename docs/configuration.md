## Configuring StorageLessSession

In most HTTPS-based setups, StorageLessSession can be initialized with some sane
defaults.

#### Symmetric key

You can set up symmetric key based signatures via the
`StorageLessSession::fromSymmetricKeyDefaults` named constructor:

```php
use StoragelessSession\Http\StorageLessSession;

$sessionMiddleware = StorageLessSession::fromSymmetricKeyDefaults(
    'contents of the symmetric key'
);
```

Please use a fairly long symmetric key: it is suggested to use a
[pseudorandom number generator](https://en.wikipedia.org/wiki/Cryptographically_secure_pseudorandom_number_generator)
to achieve that.

