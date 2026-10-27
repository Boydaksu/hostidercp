<?php
declare(strict_types=1);
namespace Hostider\Migration;

enum Entity: string
{
    case Customer = 'customer';
    case User = 'user';
    case Membership = 'membership';
    case Order = 'order';
    case Service = 'service';
    case Resource = 'resource';
    case Payment = 'payment';
    case Allocation = 'allocation';
    case Refund = 'refund';
    case Wallet = 'wallet';
    case Document = 'document';
    case Ticket = 'ticket';
    case Message = 'message';
    case Attachment = 'attachment';
}
