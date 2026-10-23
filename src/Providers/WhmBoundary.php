<?php
declare(strict_types=1);
namespace Hostider\Providers;
use Hostider\Core\Config;
/** No verified live transport exists. Configuration flags cannot bypass G-C01. */
final readonly class WhmBoundary
{
    public function __construct(private Config $config){}
    public function dispatch(): never
    {
        if($this->config->environment!=='production'){throw new \RuntimeException('LIVE_WHM_ENVIRONMENT_FORBIDDEN');}
        throw new \RuntimeException('G-C01_OPEN_LIVE_WHM_TRANSPORT_DISABLED');
    }
}
