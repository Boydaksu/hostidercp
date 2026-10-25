<?php
declare(strict_types=1);
namespace Hostider\Support\Work;
use Hostider\Core\Http\HttpError;
use Hostider\Core\Validation;
/** Local wall intervals -> union of real UTC intervals; no ambiguous local-time parsing. */
final readonly class Calendar
{
    public array $spec;
    private \DateTimeZone $zone;
    public function __construct(array $spec)
    {
        Validation::fields($spec,['timezone','weekly','exceptions'],['timezone','weekly','exceptions']);
        if(!is_string($spec['timezone'])||!in_array($spec['timezone'],\DateTimeZone::listIdentifiers(),true)||!is_array($spec['weekly'])||!is_array($spec['exceptions'])||count($spec['exceptions'])>3660){throw new HttpError(422,'INVALID_WORK_CALENDAR');}
        $this->zone=new \DateTimeZone($spec['timezone']);if(array_keys($spec['weekly'])!==[1,2,3,4,5,6,7]){throw new HttpError(422,'SEVEN_EXPLICIT_WEEKDAYS_REQUIRED');}
        foreach($spec['weekly'] as $ranges){self::ranges($ranges);}foreach($spec['exceptions'] as $date=>$ranges){self::day($date);self::ranges($ranges);}$this->spec=$spec;
    }
    private static function day(mixed $date): \DateTimeImmutable
    {
        if(!is_string($date)||!preg_match('/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/D',$date)){throw new HttpError(422,'INVALID_CALENDAR_DAY');}$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,new \DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d')!==$date){throw new HttpError(422,'INVALID_CALENDAR_DAY');}return $d;
    }
    private static function minute(mixed $s,bool $end=false): int
    {
        if(!is_string($s)||!preg_match('/^([0-9]{2}):([0-9]{2})$/D',$s,$m)){throw new HttpError(422,'INVALID_SHIFT_TIME');}$v=(int)$m[1]*60+(int)$m[2];if((int)$m[2]>59||(int)$m[1]>23&&(!$end||$s!=='24:00')){throw new HttpError(422,'INVALID_SHIFT_TIME');}return $v;
    }
    private static function ranges(mixed $ranges): void
    {
        if(!is_array($ranges)||!array_is_list($ranges)||count($ranges)>8){throw new HttpError(422,'INVALID_SHIFT_INTERVALS');}foreach($ranges as $r){if(!is_array($r)||array_keys($r)!==[0,1]||self::minute($r[0])===self::minute($r[1],true)){throw new HttpError(422,'INVALID_SHIFT_INTERVAL');}}
    }
    private function shifts(\DateTimeImmutable $day): array{return $this->spec['exceptions'][$day->format('Y-m-d')]??$this->spec['weekly'][(int)$day->format('N')];}
    public function windows(string $date): array
    {
        $day=self::day($date);$wall=[];
        foreach($this->shifts($day) as $r){$a=self::minute($r[0]);$b=self::minute($r[1],true);$wall[]=[$a*60,($b<$a?1440:$b)*60];}
        if(!array_key_exists($date,$this->spec['exceptions'])){foreach($this->shifts($day->modify('-1 day')) as $r){$a=self::minute($r[0]);$b=self::minute($r[1],true);if($b<$a){$wall[]=[0,$b*60];}}}
        $mid=$day->getTimestamp();$from=$mid-172800;$until=$mid+259200;$segments=$this->zone->getTransitions($from,$until);$out=[];
        foreach($segments as $n=>$seg){$lo=$seg['ts'];$hi=$segments[$n+1]['ts']??$until;foreach($wall as [$a,$b]){$start=max($lo,$mid+$a-$seg['offset']);$end=min($hi,$mid+$b-$seg['offset']);if($start<$end){$out[]=[$start*1_000_000,$end*1_000_000];}}}
        usort($out,static fn(array $a,array $b): int=>$a[0]<=>$b[0]);$merged=[];foreach($out as $r){$i=count($merged)-1;if($i>=0&&$r[0]<=$merged[$i][1]){$merged[$i][1]=max($merged[$i][1],$r[1]);}else{$merged[]=$r;}}return $merged;
    }
    private function local(int $us): \DateTimeImmutable{return self::day(Time::date($us)->setTimezone($this->zone)->format('Y-m-d'));}
    public function elapsed(int $from,int $to): int
    {
        if($to<$from){throw new HttpError(409,'WORK_TIME_REVERSED');}$day=$this->local($from);$last=$this->local($to);$sum=0;$count=0;
        while($day<=$last){if(++$count>3660){throw new HttpError(422,'WORK_CALENDAR_HORIZON');}foreach($this->windows($day->format('Y-m-d')) as [$a,$b]){$sum+=max(0,min($to,$b)-max($from,$a));}$day=$day->modify('+1 day');}return $sum;
    }
    public function deadline(int $from,int $remaining): int
    {
        if($remaining<=0){return $from;}$day=$this->local($from);
        for($n=0;$n<3660;++$n){foreach($this->windows($day->format('Y-m-d')) as [$a,$b]){$a=max($a,$from);if($a>=$b){continue;}$span=$b-$a;if($remaining<=$span){return $a+$remaining;}$remaining-=$span;}$day=$day->modify('+1 day');}throw new HttpError(422,'WORK_CALENDAR_HORIZON');
    }
    public function working(int $at): bool{foreach($this->windows($this->local($at)->format('Y-m-d')) as [$a,$b]){if($at>=$a&&$at<$b){return true;}}return false;}
}
