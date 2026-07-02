<?php

declare(strict_types=1);

namespace App\Exception;

interface ErrorCodeInterface
{
    public function getErrorCode(): string;
}
