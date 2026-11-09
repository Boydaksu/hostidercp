<?php
declare(strict_types=1);
namespace Hostider\Migration;

enum Knowledge: string
{
    case Known = 'KNOWN';
    case Unknown = 'UNKNOWN';
    case Conflicting = 'CONFLICTING';
    case Missing = 'MISSING';
    case Unverifiable = 'UNVERIFIABLE';
}
