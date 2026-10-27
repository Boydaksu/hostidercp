<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;

use Hostider\Core\CanonicalJson;
use Hostider\Migration\Contract;

/** Test-only offline attestation key; never inferred from an encryption key. */
final readonly class RecoverySigner
{
    public const CONTEXT="hostider.f6d.cold-issuer.v1\0";

    public function __construct(private string $keyId,#[\SensitiveParameter] private string $secret)
    {
        RecoveryAuthorization::flags();Contract::id($keyId);
        Contract::require(strlen($secret)===SODIUM_CRYPTO_SIGN_SECRETKEYBYTES,'R1_SIGNING_KEY');
        $pair=sodium_crypto_sign_seed_keypair(substr($secret,0,SODIUM_CRYPTO_SIGN_SEEDBYTES));
        Contract::require(hash_equals(sodium_crypto_sign_secretkey($pair),$secret),'R1_SIGNING_KEY');
    }
    public function publicKey(): string {return base64_encode(sodium_crypto_sign_publickey_from_secretkey($this->secret));}

    public function attest(#[\SensitiveParameter] AuthenticatedBackup $backup,#[\SensitiveParameter] string $cipher,#[\SensitiveParameter] Vault $issuer): string
    {
        RecoveryAuthorization::flags();
        Contract::require(strlen($cipher)<=4194304&&hash_equals($backup->cipherSha256,hash('sha256',$cipher)),'R1_CIPHER_BINDING');
        $v=$backup->payload;$d=$v['data'];
        Contract::require($issuer->acceptIssued($v['logical_sha256'],$v['issuer_id'],$v['issuer_mac']),'R1_CURRENT_ISSUER_REQUIRED');
        $meta=['contract'=>'hostider.cold-recovery.v1','classification'=>'SYNTHETIC','purpose'=>'VERIFY_ONLY_NO_RUNTIME_AUTHORITY',
            'authority_key_id'=>$this->keyId,'encryption_key_id'=>$v['key_id'],'backup_id'=>$v['logical_sha256'],
            'cipher_sha256'=>$backup->cipherSha256,'logical_sha256'=>Contract::hash($d['tables']),
            'source_roots_sha256'=>Contract::hash($d['report']['source_snapshot_roots']),
            'original_target_snapshot'=>$d['report']['target_snapshot'],'original_issuer_id'=>$v['issuer_id'],
            'issuer_record_sha256'=>Contract::hash([$v['issuer_id'],$v['issuer_mac']]),
            'created_at'=>$v['created_at'],'enrolled_at'=>gmdate('Y-m-d\TH:i:s\Z')];
        $signature=sodium_crypto_sign_detached(self::CONTEXT.CanonicalJson::encode($meta),$this->secret);
        $issuer->fence();
        return CanonicalJson::encode(['attestation'=>$meta,'signature'=>base64_encode($signature),'ciphertext'=>$cipher]);
    }
}
