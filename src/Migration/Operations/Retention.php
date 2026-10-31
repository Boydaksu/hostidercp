<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Migration\Contract;

final class Retention
{
    public static function plan(array $artifacts,string $now,int $quota): array
    {
        Contract::require(count($artifacts)<=128&&$quota>=0,'F6D_RETENTION_BOUND');Contract::timestamp($now);$clock=new \DateTimeImmutable($now);Contract::require($clock->getTimezone()->getName()==='+00:00'||$clock->getTimezone()->getName()==='Z','F6D_RETENTION_TIMEZONE');
        $keep=[];$review=[];$tiers=['daily'=>[],'weekly'=>[],'monthly'=>[]];$bytes=0;$valid=[];
        $ids=[];foreach($artifacts as $a){Contract::sha($a['id']);Contract::require(!isset($ids[$a['id']])&&is_bool($a['key_available']),'F6D_RETENTION_IDENTITY');Contract::require(!isset($a['requires'])||$a['requires']===[],'F6D_INCREMENTAL_CHAIN_BLOCKED');$ids[$a['id']]=true;Contract::timestamp($a['created_at']);Contract::require(is_int($a['bytes'])&&$a['bytes']>=0&&$a['bytes']<=4194304,'F6D_RETENTION_SIZE');$bytes+=$a['bytes'];$time=new \DateTimeImmutable($a['created_at']);
            if($time>$clock){$review[]='FUTURE_CLOCK';$keep[$a['id']]=true;continue;}if($a['state']!=='VERIFIED'||!$a['key_available']){$review[]='INCOMPLETE_CORRUPT_OR_KEY_UNAVAILABLE';$keep[$a['id']]=true;continue;}$valid[]=$a;
        }
        usort($valid,static fn(array $a,array $b): int=>strcmp($b['created_at'].$b['id'],$a['created_at'].$a['id']));
        foreach($valid as $a){$time=new \DateTimeImmutable($a['created_at']);foreach(['daily'=>[$clock->modify('-14 days'),'Y-m-d',14],'weekly'=>[$clock->modify('-8 weeks'),'o-W',8],'monthly'=>[$clock->modify('-12 months'),'Y-m',12]] as $tier=>[$cutoff,$format,$limit]){if($time>=$cutoff&&count($tiers[$tier])<$limit&&!isset($tiers[$tier][$time->format($format)])){$tiers[$tier][$time->format($format)]=$a['id'];$keep[$a['id']]=true;}}}
        if($valid!==[]){$keep[$valid[0]['id']]=true;}else{$review[]='NO_KNOWN_GOOD_RECOVERABLE_COPY';}
        $candidates=[];foreach($artifacts as $a){if(!isset($keep[$a['id']])){$candidates[]=$a['id'];}}sort($candidates);if($bytes>$quota){$review[]='QUOTA_THRESHOLD';}
        $review=array_values(array_unique($review));sort($review);return ['classification'=>'SYNTHETIC','mode'=>'DRY_RUN','tiers'=>$tiers,'delete_candidates'=>$candidates,'protected'=>array_keys($keep),'reasons'=>$review,'deletion_performed'=>false,'legal_retention'=>'G_O04_OPEN','production_rpo_rto'=>'NOT_VERIFIED'];
    }
}
