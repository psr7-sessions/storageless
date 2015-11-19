# PSR-7 Storage-less HTTP Sessions

[![Build Status](https://travis-ci.org/Ocramius/PSR7Session.svg)](https://travis-ci.org/Ocramius/PSR7Session)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Ocramius/PSR7Session/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/PSR7Session/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/Ocramius/PSR7Session/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Ocramius/PSR7Session/?branch=master)
[![Packagist](https://img.shields.io/packagist/v/ocramius/psr7-session.svg)](https://packagist.org/packages/ocramius/psr7-session)
[![Packagist](https://img.shields.io/packagist/vpre/ocramius/psr7-session.svg)](https://packagist.org/packages/ocramius/psr7-session)

**PSR7Session** is a [PSR-7](http://www.php-fig.org/psr/psr-7/)
[middleware](https://mwop.net/blog/2015-01-08-on-http-middleware-and-psr-7.html) that enables
session usage in PSR-7 based applications.

Proudly brought to you by [ocramius](https://github.com/Ocramius), [malukenho](https://github.com/malukenho) and [lcobucci](https://github.com/lcobucci).

### Installation

```sh
composer require ocramius/psr7-session
```

### Usage

You can use the `PSR7Session\Http\SessionMiddleware` in any 
[`zendframework/zend-stratigility`](https://github.com/zendframework/zend-stratigility)
compatible [PSR-7](http://www.php-fig.org/psr/psr-7/)
[middleware](https://github.com/zendframework/zend-stratigility/blob/1.1.2/src/MiddlewareInterface.php).

In a [`zendframework/zend-expressive`](https://github.com/zendframework/zend-expressive)
application, this would look like following:

```php
$app = \Zend\Expressive\AppFactory::create();

$app->pipe(new \PSR7Session\Http\SessionMiddleware::fromSymmetricKeyDefaults(
    'a symmetric key',
    1200 // 20 minutes
));
```

After this, you can access the session data inside any middleware that
has access to the `Psr\Http\Message\ServerRequestInterface` attributes:

```php
$app->get('/get', function (ServerRequestInterface $request, ResponseInterface $response) : ResponseInterface {
    /* @var \PSR7Session\Session\Data $session */
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

Note that you can also use asymmetric keys by using either the
`PSR7Session\Http\SessionMiddleware` constructor or the named
constructor `PSR7Session\Http\SessionMiddleware::fromAsymmetricKeyDefaults()`

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
