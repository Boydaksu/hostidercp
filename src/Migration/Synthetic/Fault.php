<?php
declare(strict_types=1);
namespace Hostider\Migration\Synthetic;
enum Fault: string
{
    case None='NONE';
    case AfterInsert='AFTER_INSERT';
    case AfterCheckpoint='AFTER_CHECKPOINT';
    case Crash='CRASH';
    case Disconnect='DISCONNECT';
}
