<?php

declare(strict_types=1);

namespace App\Enums;

enum PostIndexSource: string
{
    case Api     = 'api';
    case Archive = 'archive';
}
