# PSR-7 Storage-less HTTP Sessions

[![Build Status](https://travis-ci.org/psr7-sessions/storageless.svg)](https://travis-ci.org/psr7-sessions/storageless)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/psr7-sessions/storageless/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/psr7-sessions/storageless/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/psr7-sessions/storageless/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/psr7-sessions/storageless/?branch=master)
[![Packagist](https://img.shields.io/packagist/v/psr7-sessions/storageless.svg)](https://packagist.org/packages/psr7-sessions/storageless)
[![Packagist](https://img.shields.io/packagist/vpre/psr7-sessions/storageless.svg)](https://packagist.org/packages/psr7-sessions/storageless)

**PSR7Session** is a [PSR-7](http://www.php-fig.org/psr/psr-7/) and 
[PSR-15](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers.md)
compatible [middleware](https://mwop.net/blog/2015-01-08-on-http-middleware-and-psr-7.html) that enables
session without I/O usage in PSR-7 based applications.

Proudly brought to you by [ocramius](https://github.com/Ocramius), [malukenho](https://github.com/malukenho) and [lcobucci](https://github.com/lcobucci).

### Installation

```sh
composer require psr7-sessions/storageless
```

### Usage

You can use the `PSR7Sessions\Storageless\Http\SessionMiddleware` in any 
[PSR-15](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers.md)
compatible middleware.

In a [`zendframework/zend-expressive`](https://github.com/zendframework/zend-expressive)
application, this would look like following:

```php
$app = \Zend\Expressive\AppFactory::create();

$app->pipe(\PSR7Sessions\Storageless\Http\SessionMiddleware::fromSymmetricKeyDefaults(
    'mBC5v1sOKVvbdEitdSBenu59nfNfhwkedkJVNabosTw=', // replace this with a key of your own (see docs below)
    1200 // 20 minutes
));
```

After this, you can access the session data inside any middleware that
has access to the `Psr\Http\Message\ServerRequestInterface` attributes:

```php
$app->get('/get', function (ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
    /* @var \PSR7Sessions\Storageless\Session\Data $session */
    $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
    $session->set('counter', $session->get('counter', 0) + 1);

    $response
        ->getBody()
        ->write('Counter Value: ' . $session->get('counter'));

    return $response;
});
```

You can do this also in asynchronous contexts and long running processes,
since no super-globals nor I/O are involved.

It is recommended that you use a key with lots of entropy, preferably
generated using a cryptographically secure pseudo-random number generator
(CSPRNG). You can use the [CryptoKey tool](https://github.com/AndrewCarterUK/CryptoKey)
to do this for you.

Note that you can also use asymmetric keys by using either the
`PSR7Sessions\Storageless\Http\SessionMiddleware` constructor or the named
constructor `PSR7Sessions\Storageless\Http\SessionMiddleware::fromAsymmetricKeyDefaults()`

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
 * not designed to be used for multiple dispatch cycles

### What does this project do?

This project tries to implement storage-less sessions and to mitigate the
issues listed above.

### Assumptions

 * your sessions are fairly small and contain only few identifiers and
   some CSRF tokens. Small means `< 400` bytes
 * data in your session is `JsonSerializable` or equivalent
 * data in your session is **freely readable by the client**

### How does it work?

Session data is directly stored inside a session cookie as a JWT token.

This approach is not new, and is commonly used with `Bearer` tokens in
HTTP/REST/OAuth APIs.

In order to guarantee that the session data is not modified, that the
client can trust the information and that the expiration date is
mutually agreed between server and client, a [JWT token](https://tools.ietf.org/html/rfc7519)
is used to transmit the information.

The JWT token is always signed to ensure that the user-agent is never
able to manipulate the session.
Both symmetric and asymmetric keys are supported for signing/verifying
tokens.

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
 * can be used over multiple dispatch cycles

### Configuration options

Please refer to the [configuration documentation](docs/configuration.md).

### Known limitations

Please refer to the [limitations documentation](docs/limitations.md).

### Contributing

Please refer to the [contributing notes](CONTRIBUTING.md).

### License

This project is made public under the [MIT LICENSE](LICENSE).
