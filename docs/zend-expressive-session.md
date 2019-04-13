## Zend Expressive Session Integration

This integration allows you to use storageless as an implementation for [zend-expressive-session][1]

#### Symmetric key

```php
use PSR7Sessions\Storageless\Http\SessionMiddleware as PSR7SessionMiddleware;
use PSR7Sessions\Storageless\Session\Zend\SessionPersistence;
use Zend\Expressive\Session\SessionMiddleware;

$app = \Zend\Expressive\AppFactory::create();
$app->pipe(PSR7SessionMiddleware::fromSymmetricKeyDefaults(
    'OpcMuKmoxkhzW0Y1iESpjWwL/D3UBdDauJOe742BJ5Q=',
    1200
));
$app->pipe(new SessionMiddleware(new SessionPersistence()));
```

#### Asymmetric key

```php
use PSR7Sessions\Storageless\Http\SessionMiddleware as PSR7SessionMiddleware;
use PSR7Sessions\Storageless\Session\Zend\SessionPersistence;
use Zend\Expressive\Session\SessionMiddleware;

$app = \Zend\Expressive\AppFactory::create();
$app->pipe(PSR7SessionMiddleware::fromSymmetricKeyDefaults(
    file_get_contents('/path/to/private_key.pem'),
    file_get_contents('/path/to/public_key.pem'),
    1200
));
$app->pipe(new SessionMiddleware(new SessionPersistence()));
```

[1]: https://github.com/zendframework/zend-expressive-session
