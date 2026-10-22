<?php
declare(strict_types=1);
namespace Hostider\Finance;
enum Rounding: string
{
    case HalfUp='HALF_UP';
    case HalfEven='HALF_EVEN';
    case TowardZero='TOWARD_ZERO';
}
