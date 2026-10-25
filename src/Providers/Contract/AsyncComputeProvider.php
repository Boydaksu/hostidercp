<?php
declare(strict_types=1);
namespace Hostider\Providers\Contract;

/** Hostider internal async boundary. Existing synchronous ComputeProvider remains unchanged.
 * Acceptance of a task is never SUCCESS of a VM. Live response normalization remains gated.
 */
interface AsyncComputeProvider
{
    public function mutate(array $request,string $fingerprint): array;
    public function observe(string $operation): array;
    public function inspectResource(string $id): ?array;
}
