<?php
declare(strict_types=1);
namespace Hostider\Providers\Mock;
use Hostider\Core\Security\EffectPolicy;
final class ResellerClub extends Registrar
{
    public function __construct(EffectPolicy $policy, string $scenario = 'SUCCESS') { parent::__construct('resellerclub', $policy, $scenario); }
}
