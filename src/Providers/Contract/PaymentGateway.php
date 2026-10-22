<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;
interface PaymentGateway extends Provider
{
    public function createAttempt(Operation $operation): ProviderResult;
    public function refund(Operation $operation): ProviderResult;
    /** Real signature/amount mapping remains G-P01/G-P02; no live verifier in F0. */
    public function verifyCallback(array $payload): never;
}
