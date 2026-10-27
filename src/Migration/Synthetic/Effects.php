<?php
declare(strict_types=1);
namespace Hostider\Migration\Synthetic;
final class Effects
{
    private array $calls=['CPANEL_CREATE'=>0,'VOYAR_CREATE'=>0,'RESELLERCLUB_CREATE'=>0,'DOMAINNAMEAPI_CREATE'=>0,'PAYMENT'=>0,'BILLING'=>0,'MAIL'=>0,'NOTIFICATION'=>0];
    private int $denied=0;
    public function request(string $effect): never { ++$this->denied; throw new \LogicException('F6B_EFFECT_FORBIDDEN'); }
    public function counts(): array { return ['calls'=>$this->calls,'denied'=>$this->denied]; }
}
