<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;

use Hostider\Migration\Contract;
use Hostider\Migration\Synthetic\Sandbox;

/** Trusted operator configuration, separate from untrusted capsule bytes. No DB authority. */
final readonly class RecoveryAuthorization
{
    private function __construct(private array $keys,private string $backup,private string $roots,private string $snapshot,private string $encryptionKeyId,private string $operator,private int $expires) {}

    public static function flags(): void
    {
        Sandbox::flags();Contract::require(getenv('SYNTHETIC_BACKUP_ALLOWED')==='1'&&getenv('SYNTHETIC_RECOVERY_ALLOWED')==='1','R1_ENVIRONMENT_DENIED');
    }
    public static function allow(array $trustedKeys,string $backup,string $sourceRoots,string $snapshot,string $encryptionKeyId,string $operator,int $expires): self
    {
        self::flags();Contract::sha($backup);Contract::sha($sourceRoots);Contract::sha($snapshot);Contract::id($encryptionKeyId);Contract::id($operator);
        Contract::require(count($trustedKeys)>0&&count($trustedKeys)<=8,'R1_TRUST_BOUND');
        foreach($trustedKeys as $id=>$key){Contract::id($id);Contract::fields($key,['public_key','revoked']);$raw=base64_decode($key['public_key'],true);
            Contract::require(is_string($raw)&&strlen($raw)===SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES&&base64_encode($raw)===$key['public_key']&&is_bool($key['revoked']),'R1_TRUST_KEY');}
        Contract::require($expires>time()&&$expires<=time()+900,'R1_AUTHORIZATION_EXPIRED_OR_UNBOUNDED');
        return new self($trustedKeys,$backup,$sourceRoots,$snapshot,$encryptionKeyId,$operator,$expires);
    }
    public function check(array $meta): string
    {
        self::flags();Contract::require($this->expires>time(),'R1_AUTHORIZATION_EXPIRED_OR_UNBOUNDED');
        Contract::require($meta['backup_id']===$this->backup&&$meta['source_roots_sha256']===$this->roots&&$meta['original_target_snapshot']===$this->snapshot&&$meta['encryption_key_id']===$this->encryptionKeyId,'R1_RECOVERY_IDENTITY');
        $key=$this->keys[$meta['authority_key_id']]??null;
        Contract::require(is_array($key)&&!$key['revoked'],'R1_ISSUER_UNTRUSTED_OR_REVOKED');
        return base64_decode($key['public_key'],true);
    }
    public function operatorFingerprint(): string {return hash('sha256',$this->operator);}
}
