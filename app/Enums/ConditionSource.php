<?php

declare(strict_types=1);

namespace App\Enums;

enum ConditionSource: string
{
    case Body = 'body';
    case Query = 'query';
    case Header = 'header';
    case Path = 'path';
}
