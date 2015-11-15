# Storage-Less HTTP Sessions

[![Build Status](https://travis-ci.org/Ocramius/StorageLessSession.svg)](https://travis-ci.org/Ocramius/StorageLessSession)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Ocramius/StorageLessSession/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/StorageLessSession/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Ocramius/StorageLessSession/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/StorageLessSession/?branch=master)
[![Packagist](https://img.shields.io/packagist/v/ocramius/storage-less-session.svg)](https://packagist.org/packages/ocramius/storage-less-session)
[![Packagist](https://img.shields.io/packagist/vpre/ocramius/storage-less-session.svg)](https://packagist.org/packages/ocramius/storage-less-session)

**StoragelessSession** is a [PSR-7](http://www.php-fig.org/psr/psr-7/)
[middleware](https://mwop.net/blog/2015-01-08-on-http-middleware-and-psr-7.html) that enables
session usage in PSR-7 based applications

### Installation

```sh
composer require ocramius/storage-less-session
```

### Usage

You can use the `StoragelessSession\Http\SessionMiddleware` in any 
[`zendframework/zend-stratigility`](https://github.com/zendframework/zend-stratigility)
compatible [PSR-7](http://www.php-fig.org/psr/psr-7/)
[middleware](https://github.com/zendframework/zend-stratigility/blob/1.1.2/src/MiddlewareInterface.php).

In a [`zendframework/zend-expressive`](https://github.com/zendframework/zend-expressive)
application, this would look like following:

```php
$app = \Zend\Expressive\AppFactory::create();

$app->pipe(new \StoragelessSession\Http\SessionMiddleware::fromSymmetricKeyDefaults(
    'a symmetric key',
    1200 // 20 minutes
));
```

After this, you can access the session data inside any middleware that
has access to the `Psr\Http\Message\ServerRequestInterface` attributes:

```php
$app->get('/get', function (ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
    /* @var \StoragelessSession\Session\Data $container */
    $container = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
    $container->set('counter', $container->get('counter', 0) + 1);

    $response
        ->getBody()
        ->write('Counter Value: ' . $container->get('counter'));

    return $response;
});
```

You can do this also in asynchronous contexts and long running processes,
since no super-globals nor I/O are involved.

Note that you can also use asymmetric keys by using either the
`StoragelessSession\Http\SessionMiddleware` constructor or the named
constructor `StoragelessSession\Http\SessionMiddleware::fromAsymmetricKeyDefaults()`

### Examples

Simply browse to the `examples` directory in your console, then run

```sh
php -S localhost:9999 index.php
```

Then try accessing `http://localhost:9999`: you should see a counter
that increases at every page refresh

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
   some CSRF tokens. Small means `< 400` bytes
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
 * when in a multi-server setup, you may allow read-only access to servers
   that only have access to public keys, while writes are limited to
   servers that have access to private keys