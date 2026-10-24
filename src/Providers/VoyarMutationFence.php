<?php
declare(strict_types=1);
namespace Hostider\Providers;
use Hostider\Core\Config;
use Hostider\Core\Http\HttpError;

/** Per-dispatch transport guard. Durable operation lease must be claimed before construction. */
final class VoyarMutationFence
{
    private bool $sent=false;
    public function __construct(Config $config,private ?\Closure $admit=null)
    {
        // Real transport remains unavailable while G-V02/G-V03 are OPEN.
        if($config->environment!=='test'){throw new HttpError(403,'G_V02_G_V03_LIVE_DISPATCH_CLOSED');}
    }
    public function send(\Closure $transport): mixed
    {
        if($this->sent){throw new HttpError(409,'VOYAR_MUTATION_REPLAY_BLOCKED');}
        // Consume before I/O. Timeout, body loss, auth error and exception do not reset it.
        $this->sent=true;
        if($this->admit!==null){($this->admit)();}
        return $transport();
    }
}
