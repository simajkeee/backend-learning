<?php

declare(strict_types=1);

namespace App\Helper;

class Debugger
{
    public static function isObjectAProxy(object $e): bool
    {
        return new \ReflectionClass($e)->isUninitializedLazyObject($e);
    }
}
