<?php
declare(strict_types=1);
namespace Hostider\Finance;
use Hostider\Core\{Config,Id};use Hostider\Core\Database\Database;use Hostider\Core\Http\HttpError;use Hostider\Core\Security\Principal;
/** Existing Ledger accrual, not a second accounting system and not a cash payment. */
final readonly class ProviderCosts
{
    public function __construct(private Database $db,private Config $config){}
    public function accrueMock(string $costId,Money $gross): ?string
    {
        $this->db->requireTransaction();if($this->config->environment!=='test'){throw new HttpError(403,'CLOUD_COST_TEST_ONLY');}
        if((new Decimal($gross->minor))->compare(new Decimal('0'))<0){throw new \InvalidArgumentException('Non-negative provider cost required');}if($gross->minor==='0'){return null;}
        return (new Ledger($this->db))->post('cloud-cost:'.$costId,$gross->currency,[['account'=>'provider_cost','side'=>'DEBIT','minor'=>$gross->minor],['account'=>'provider_payable','side'=>'CREDIT','minor'=>$gross->minor]],new Principal('cloud-cost-worker',[]),Id::uuid());
    }
}
