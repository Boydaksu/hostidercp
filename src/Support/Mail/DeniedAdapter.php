<?php
declare(strict_types=1);
namespace Hostider\Support\Mail;
use Hostider\Core\Http\HttpError;
/** No network implementation or credentials are accepted in this phase. */
final class DeniedAdapter implements InboundAdapter
{
    public function receive(): ?array{throw new HttpError(503,'MAIL_LIVE_GATE_CLOSED');}
    public function acknowledge(): void{throw new HttpError(503,'MAIL_LIVE_GATE_CLOSED');}
    public function send(): never{throw new HttpError(503,'MAIL_LIVE_GATE_CLOSED');}
}
