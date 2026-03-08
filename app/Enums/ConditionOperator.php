<?php

declare(strict_types=1);

namespace App\Enums;

enum ConditionOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
}
