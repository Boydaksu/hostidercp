<?php
declare(strict_types=1);
namespace Hostider\Pricing;
use Hostider\Core\{Config,Id};
use Hostider\Core\Database\Database;
use Hostider\Core\Http\HttpError;
use Hostider\Finance\{Currency,FxSnapshot,Decimal,Rounding};
final readonly class FxRateRepository
{
    public function __construct(private Database $db,private Config $config) {}
    public function publishSynthetic(FxSnapshot $rate,bool $trusted): string
    {
        $this->db->requireTransaction();
        if($this->config->environment!=='test'){throw new HttpError(403,'SYNTHETIC_RATE_FORBIDDEN');}
        $id=Id::uuid(); $data=$rate->jsonSerialize();
        $this->db->execute('INSERT INTO pricing_fx_rates(id,source_currency,target_currency,rate,spread,source_reference,observed_at,trusted) VALUES (?,?,?,?,?,?,?,?)',[$id,$data['source'],$data['target'],$data['rate'],$data['spread'],$data['reference'],$data['observed_at'],$trusted?1:0]); return $id;
    }
    public function resolve(Currency $source,Currency $target,ExchangeRatePolicy $policy,\DateTimeImmutable $now): array
    {
        // G-X01 has not closed: no live feed or synthetic quote in staging/production.
        if($this->config->environment!=='test'){throw new HttpError(409,'G_X01_OPEN');}
        $row=$this->db->execute('SELECT * FROM pricing_fx_rates WHERE source_currency=? AND target_currency=? ORDER BY observed_at DESC,created_at DESC,id DESC LIMIT 1',[$source->code,$target->code])->fetch();
        if(!$row){throw new HttpError(409,'FX_NOT_FOUND');} $policy->validate($row,$now);
        $snapshot=new FxSnapshot($source,$target,new Decimal($row['rate']),new Decimal($row['spread']),$row['source_reference'],new \DateTimeImmutable($row['observed_at'],new \DateTimeZone('UTC')),Rounding::HalfUp);
        return ['id'=>$row['id'],'snapshot'=>$snapshot];
    }
}
