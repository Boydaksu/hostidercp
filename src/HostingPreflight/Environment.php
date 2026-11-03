<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Environment
{
    public const CORE_FUNCTIONS=['random_bytes','hash','hash_hmac','hash_equals','hrtime','usleep','getenv','json_encode','json_decode','filter_var','password_hash','password_verify','password_needs_rehash','mb_check_encoding','mb_strlen','session_start','session_write_close','session_regenerate_id','session_abort','session_status','session_name','session_id','session_set_cookie_params'];
    public const FILE_FUNCTIONS=['fopen','fstat','fseek','flock','fwrite','fflush','fclose','is_file','is_dir','is_link','realpath','fileperms','filesize','file_get_contents','mkdir','chmod','unlink','rename'];
    public static function capture(array $deployment=[],?array $database=null): Observation
    {
        $functions=[];foreach([...self::CORE_FUNCTIONS,...self::FILE_FUNCTIONS,'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt','sodium_crypto_secretstream_xchacha20poly1305_init_push','sodium_crypto_secretstream_xchacha20poly1305_push','sodium_crypto_secretstream_xchacha20poly1305_init_pull','sodium_crypto_secretstream_xchacha20poly1305_pull','posix_geteuid','fsync','idn_to_ascii','finfo_open'] as $f){$functions[$f]=function_exists($f);}
        $runtime=['version'=>PHP_VERSION_ID,'bits'=>PHP_INT_SIZE*8,'sapi'=>PHP_SAPI,'extensions'=>array_fill_keys(get_loaded_extensions(),true),'functions'=>$functions,'mysql'=>class_exists(\PDO::class)&&in_array('mysql',\PDO::getAvailableDrivers(),true),'argon2id'=>function_exists('password_algos')&&in_array('argon2id',password_algos(),true),'finfo'=>class_exists(\finfo::class),'nonroot'=>PrivateFd::uid()!==null&&PrivateFd::uid()!==0];
        return new Observation($runtime,Filesystem::inspect($deployment),$database??Metadata::absent(),$deployment['web_php_version']??null);
    }
}
