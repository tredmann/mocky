<?php

declare(strict_types=1);

namespace App\Enums;

enum EndpointType: string
{
    case Rest = 'rest';
    case Soap = 'soap';
}
