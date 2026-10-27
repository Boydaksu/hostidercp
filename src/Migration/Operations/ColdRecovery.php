<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;

use Hostider\Core\CanonicalJson;
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Contract;

/** Independent read-only verification. It cannot construct a Sandbox or restore capability. */
final class ColdRecovery
{
    public static function verify(#[\SensitiveParameter] string $capsule,#[\SensitiveParameter] Crypto $crypto,RecoveryAuthorization $authority): array
    {
        RecoveryAuthorization::flags();Contract::require(strlen($capsule)<=5242880,'R1_CAPSULE_BOUND');
        try{$outer=json_decode($capsule,true,12,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \InvalidArgumentException('R1_CAPSULE_FORMAT');}
        Contract::require(is_array($outer)&&CanonicalJson::encode($outer)===$capsule,'R1_CAPSULE_NONCANONICAL');Contract::fields($outer,['attestation','signature','ciphertext']);
        $m=$outer['attestation'];Contract::fields($m,['contract','classification','purpose','authority_key_id','encryption_key_id','backup_id','cipher_sha256','logical_sha256','source_roots_sha256','original_target_snapshot','original_issuer_id','issuer_record_sha256','created_at','enrolled_at']);
        Contract::require($m['contract']==='hostider.cold-recovery.v1'&&$m['classification']==='SYNTHETIC'&&$m['purpose']==='VERIFY_ONLY_NO_RUNTIME_AUTHORITY','R1_ATTESTATION_CONTEXT');
        foreach(['backup_id','cipher_sha256','logical_sha256','source_roots_sha256','original_target_snapshot','issuer_record_sha256'] as $field){Contract::sha($m[$field]);}
        Contract::id($m['authority_key_id']);Contract::id($m['encryption_key_id']);Contract::timestamp($m['created_at']);Contract::timestamp($m['enrolled_at']);
        Contract::require(preg_match('/^[a-f0-9]{24}$/D',$m['original_issuer_id'])===1&&$m['created_at']<=$m['enrolled_at']&&$m['enrolled_at']<=gmdate('Y-m-d\TH:i:s\Z'),'R1_ATTESTATION_TIME_OR_ISSUER');
        $public=$authority->check($m);$signature=base64_decode($outer['signature'],true);
        Contract::require(is_string($signature)&&strlen($signature)===SODIUM_CRYPTO_SIGN_BYTES&&base64_encode($signature)===$outer['signature']&&sodium_crypto_sign_verify_detached($signature,RecoverySigner::CONTEXT.CanonicalJson::encode($m),$public),'R1_ISSUER_SIGNATURE');
        $cipher=$outer['ciphertext'];Contract::require(is_string($cipher)&&strlen($cipher)<=4194304&&hash_equals($m['cipher_sha256'],hash('sha256',$cipher)),'R1_CIPHER_INTEGRITY');
        try{$plain=$crypto->decrypt($cipher,'f6d-backup:'.$m['backup_id']);$v=json_decode($plain,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('R1_DECRYPTION');}
        Contract::require(is_array($v)&&CanonicalJson::encode($v)===$plain,'R1_PAYLOAD_NONCANONICAL');
        Contract::fields($v,['data','logical_sha256','created_at','key_id','encoding','issuer_id','issuer_mac']);
        Contract::require($v['encoding']==='json-base64-no-compression'&&$v['key_id']===$m['encryption_key_id']&&$v['logical_sha256']===$m['backup_id']&&Contract::hash($v['data'])===$m['backup_id']&&$v['created_at']===$m['created_at']&&$v['issuer_id']===$m['original_issuer_id']&&Contract::hash([$v['issuer_id'],$v['issuer_mac']])===$m['issuer_record_sha256'],'R1_IMMUTABLE_EVIDENCE');
        $d=$v['data'];Vault::validateData($d);
        Contract::require(Contract::hash($d['tables'])===$m['logical_sha256']&&Contract::hash($d['report']['source_snapshot_roots'])===$m['source_roots_sha256']&&$d['report']['target_snapshot']===$m['original_target_snapshot'],'R1_SOURCE_EVIDENCE');
        $authority->check($m);
        return ['classification'=>'SYNTHETIC','contract'=>'hostider.cold-recovery.v1','verification'=>'VERIFIED_PRE_ENROLLED_ESCROW',
            'backup_id'=>$m['backup_id'],'logical_sha256'=>$m['logical_sha256'],'source_roots_sha256'=>$m['source_roots_sha256'],
            'original_target_snapshot'=>$m['original_target_snapshot'],'authority_key_id'=>$m['authority_key_id'],'encryption_key_id'=>$m['encryption_key_id'],
            'operator_sha256'=>$authority->operatorFingerprint(),'tables'=>count($d['tables']),'records'=>array_sum(array_map('count',$d['tables'])),'files'=>count($d['files']),
            'finance'=>$d['report']['finance'],'original_issuer_record'=>'PRESERVED_AUTHENTICATED_AT_ENROLMENT','original_hmac_recomputed'=>false,
            'source_connection_required'=>false,'operational_vault_required'=>false,'native_recovery'=>'BLOCKED_IDENTITY_BOUND',
            'original_archive_bytes'=>'NOT_VERIFIED','production_restore'=>'NOT_VERIFIED','key_custody'=>'NOT_VERIFIED','effects_allowed'=>false];
    }
}
