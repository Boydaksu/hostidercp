<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Migration\Contract;

/** Trusted operator pin, not an artifact-supplied connection or runtime capability. */
final readonly class RecoveryTargetPolicy
{
    private function __construct(public array $binding,private string $backup,private int $expires) {}
    public static function allow(array $binding,string $backup,int $currentEpoch,int $expires,bool $revoked=false,string $purpose='READ_ONLY_RECONCILIATION'): self
    {
        RecoveryAuthorization::flags();Contract::fields($binding,['contract','port','schema','account','marker_sha256','marker_schema_sha256','epoch']);Contract::sha($backup);Contract::sha($binding['marker_sha256']);Contract::sha($binding['marker_schema_sha256']);
        Contract::require($binding['contract']==='hostider.recovery-target.v1'&&is_int($binding['port'])&&$binding['port']>0&&$binding['port']<=65535&&preg_match('/^f6d_[a-f0-9]{24}$/D',$binding['schema'])===1&&preg_match('/^r2_[a-f0-9]{16}$/D',$binding['account'])===1,'R2_TARGET_INPUT');
        Contract::require(!$revoked&&$purpose==='READ_ONLY_RECONCILIATION'&&is_int($binding['epoch'])&&$binding['epoch']>0&&$binding['epoch']===$currentEpoch,'R2_POLICY_REVOKED_PURPOSE_OR_EPOCH');
        Contract::require($expires>time()&&$expires<=time()+300,'R2_POLICY_EXPIRED');return new self($binding,$backup,$expires);
    }
    public function check(string $backup): void
    {
        RecoveryAuthorization::flags();Contract::require($this->backup===$backup&&$this->expires>time(),'R2_POLICY_EXPIRED_OR_BACKUP');
    }
    public function fingerprint(string $logical): string {return Contract::hash([$this->binding,$this->backup,$logical,'READ_ONLY_RECONCILIATION']);}
}
