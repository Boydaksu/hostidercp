<?php
declare(strict_types=1);
namespace Hostider\Payments;
use Hostider\Core\Http\HttpError;
/** No merchant signature/amount/currency semantics are claimed before G-P01/G-P02. */
final class PaytrBoundary
{
    public function createSession(array $request): never { throw new HttpError(503,'PAYTR_G_P01_G_P02_OPEN'); }
    public function acceptCallback(array $payload): never { throw new HttpError(403,'PAYTR_G_P01_G_P02_OPEN'); }
    public function refund(array $request): never { throw new HttpError(503,'PAYTR_G_P01_G_P02_OPEN'); }
}
