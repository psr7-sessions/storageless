# Storage-Less HTTP Sessions

### Installation

```sh
composer require ocramius/storage-less-session
```

### Usage

You can use the `StoragelessSession\Http\SessionMiddleware` in any 
[`zendframework/zend-stratigility`](https://github.com/zendframework/zend-stratigility)
compatible [PSR-7](http://www.php-fig.org/psr/psr-7/) middleware.

In a [`zendframework/zend-expressive`](https://github.com/zendframework/zend-expressive)
application, this would look like following:

```php
$app = \Zend\Expressive\AppFactory::create();

$app
    ->pipe(new \StoragelessSession\Http\SessionMiddleware(
        new \Lcobucci\JWT\Signer\Hmac\Sha256(),
        'a symmetric key',
        'a symmetric key',
        \Dflydev\FigCookies\SetCookie::create('the-session-cookie-name'),
        new \Lcobucci\JWT\Parser(),
        14400
    ));
```

### WHY?

In most PHP+HTTP related projects, `ext/session` serves its purpose and
allows us to store server-side information by associating a certain
identifier to a visiting user-agent.

### What is the problem with `ext/session`?

This is all fair and nice, except for:

 * relying on the `$_SESSION` superglobal
 * relying on the shutdown handlers in order to "commit" sessions to the 
   storage
 * having a huge limitation of number of active users (due to storage)
 * having a lot of I/O due to storage
 * having serialized data cross different processes (PHP serializes and
   de-serializes `$_SESSION` for you, and there are security implications)
 * having to use a centralized storage for setups that scale horizontally
 * having to use sticky sessions (with a "smart" load-balancer) when the
   storage is not centralized

### What does this project do?

This project tries to implement storage-less sessions and to mitigate the
issues listed above.

Most of the logic isn't finalized yet, and this is just a mockup of a
[PSR-7](http://www.php-fig.org/psr/psr-7/) middleware that injects a 
`'session'` attribute (containing session data) into incoming requests.

### Assumptions

 * your sessions are fairly small and contain only few identifiers and
   some CSRF tokens. Small means `< 400` byes
 * data in your session is `JsonSerializable` or equivalent
 * data in your session is **freely available to the client** (we may 
   introduce encryption to change this in future)

### How does it work?

Nothing new happening here: session data is directly stored inside the 
session cookie.

In order to guarantee that the session data is not modified, that the
client can trust the information and that the expiration date is
mutually agreed between server and client, a [JWT token](https://tools.ietf.org/html/rfc7519)
is used to transmit the information.
 
The token MUST be signed (and eventually encrypted) in the default
implementation of the library.

Encryption must be asymmetric and based on private/public key, where the
private key is owned by the server creating the session. Client-side
verification of the session is not necessary if TLS is used, but it can
eventually be introduced.

### Advantages

 * no storage required
 * no sticky sessions required (any server having a copy of the private or 
   public keys can generate sessions or consume them)
 * can transmit cleartext information to the client, allowing it to share
   some information with the server (a standard example is about sharing the
   "username" or "user-id" in a given session)
 * can transmit encrypted information to the client, allowing server-only
   consumption of the information
 * not affected by PHP serialization RCE attacks
 * not limited to PHP process scope: can have many sessions per process
 * no reliance on global state
