<?php
declare(strict_types=1);
namespace Hostider\HostingPreflight;
final class Cli
{
    public static function main(array $arguments): int
    {
        try{
            if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80300){throw new \RuntimeException('CLI baseline');}
            $options=[];foreach($arguments as $arg){if(!is_string($arg)||preg_match('/\A--(deployment-fd|db-config-fd)=([0-9]{1,2})\z/D',$arg,$m)!==1||isset($options[$m[1]])){throw new \InvalidArgumentException('Options');}$options[$m[1]]=(int)$m[2];}
            if(isset($options['deployment-fd'],$options['db-config-fd'])&&$options['deployment-fd']===$options['db-config-fd']){throw new \InvalidArgumentException('Descriptor reuse');}
            $deployment=isset($options['deployment-fd'])?PrivateFd::read($options['deployment-fd']):[];
            // Validate local input before a connection can be considered; no environment DB fallback.
            $local=Environment::capture($deployment);
            $database=isset($options['db-config-fd'])?Metadata::fromDescriptor($options['db-config-fd']):Metadata::absent();
            $report=Evaluator::evaluate(new Observation($local->runtime,$local->filesystem,$database,$local->declaredWebVersion))->jsonSerialize();
            echo json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES).PHP_EOL;
            return match($report['status']){'PREFLIGHT_PASS_WITHIN_CHECKED_SCOPE'=>0,'PREFLIGHT_INCOMPLETE'=>2,default=>1};
        }catch(\Throwable){echo '{"format_version":1,"scope":"LOCAL_CLI_AND_READ_ONLY_METADATA_ONLY","status":"PREFLIGHT_BLOCKED","checks":[{"check_code":"INPUT_CONTRACT","feature":"preflight","classification":"REQUIRED","status":"FAIL","reason":"UNSAFE","explanation":"Private input or runtime contract unavailable.","operator_action":"Review the documented private descriptor and CLI contract; no error details are emitted."}],"gates":{"G-O01":"OPEN","G-O02":"OPEN","OPS-002":"OPEN","OPS-003":"OPEN"},"production_allowed":false,"effects_allowed":false,"real_gate_status":"BLOCKED"}'.PHP_EOL;return 1;}
    }
}
