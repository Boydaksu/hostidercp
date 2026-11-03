<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final readonly class Observation
{
    public function __construct(public array $runtime,public array $filesystem,public array $database,public ?int $declaredWebVersion=null){}
}
