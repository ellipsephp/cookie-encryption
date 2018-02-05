# Cookie encryption

This package provides a [Psr-15 middleware](https://www.php-fig.org/psr/psr-15/) allowing to encrypt cookies using [defuse/php-encryption](https://github.com/defuse/php-encryption).

**Require** php >= 7.1

**Installation** `composer require ellipse/cookie-encryption`

**Run tests** `./vendor/bin/kahlan`

- [Getting started](#getting-started)

# Getting started

This middleware takes an instance of `Defuse\Crypto\Key` from the [defuse/php-encryption](https://github.com/defuse/php-encryption) package and an array of bypassed cookie names as parameters. It will use defuse encryption mechanism to decrypt the cookies attached to the Psr-7 request and encrypt the cookies attached to the Psr-7 response. The cookies with a name in the bypassed array will stay untouched. When the decryption fails for one cookie, its value is set as an empty string.

```php
<?php

namespace App;

use Defuse\Crypto\Key;
use Ellipse\Cookies\EncryptCookiesMiddleware;

// Load an encryption key from your config.
$key = Key::loadFromAsciiSafeString(getenv('APP_KEY'));

// By using this middleware all cookies will be decrypted/encrypted, except the one named 'bypassed'.
$middleware = new EncryptCookiesMiddleware($key, ['bypassed']);
```
