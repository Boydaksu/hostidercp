<?php
declare(strict_types=1);
namespace Hostider\Migration\Operations;
use Hostider\Core\Security\Crypto;
use Hostider\Migration\Contract;

final readonly class AuthenticatedBackup
{
    private function __construct(public array $payload,public string $cipherSha256) {}
    public static function open(string $id,#[\SensitiveParameter] string $cipher,#[\SensitiveParameter] Crypto $crypto,string $keyId,#[\SensitiveParameter] Vault $issuer): self
    {
        Contract::sha($id);Contract::require(strlen($cipher)<=4194304,'F6D_PACKAGE_BOUND');
        try{$plain=$crypto->decrypt($cipher,'f6d-backup:'.$id);$value=json_decode($plain,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new \RuntimeException('F6D_AUTHENTICATION');}
        Contract::require(\Hostider\Core\CanonicalJson::encode($value)===$plain,'F6D_NONCANONICAL_OR_DUPLICATE_KEYS');
        Contract::fields($value,['data','logical_sha256','created_at','key_id','encoding','issuer_id','issuer_mac']);
        Contract::require($value['encoding']==='json-base64-no-compression'&&$value['key_id']===$keyId&&$value['logical_sha256']===$id&&Contract::hash($value['data'])===$id,'F6D_MANIFEST_INTEGRITY');
        Contract::require($issuer->acceptIssued($id,$value['issuer_id'],$value['issuer_mac']),'F6D_UNISSUED_BACKUP');
        Contract::timestamp($value['created_at']);Vault::validateData($value['data']);return new self($value,hash('sha256',$cipher));
    }
}
