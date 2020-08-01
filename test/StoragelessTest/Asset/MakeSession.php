<?php

declare(strict_types=1);

namespace PSR7SessionsTest\Storageless\Asset;

use PSR7Sessions\Storageless\Session\SessionInterface;

interface MakeSession
{
    public function __invoke(): SessionInterface;
}
