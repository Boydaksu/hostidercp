param([ValidateSet('83','84')][string]$Php,[string]$RunId)
$ErrorActionPreference='Stop'
$taskRoot=Split-Path -Parent $MyInvocation.MyCommand.Path
$prefix="hostider-r2h-$RunId-$Php"
$net="$prefix-net";$volume="$prefix-work";$db="$prefix-db"
$prep="$prefix-prep";$quality="$prefix-quality";$app="$prefix-app"
$image=if($Php -eq '83'){'sha256:d4c4bd00590b5983b8a2b42ad2cdc60693e1e091517abb6ba1d3fca0c30c2bad'}else{'sha256:ecef3f23a81f103e25add00f44a77fe073d4835506af101dadc3928c5802a0ba'}
$dbImage='sha256:de61fed4a40d3842f3ee09944ba52792156cfd9adf489b2cc670fc6ded28df8d'
$sha='53162495902b3b168c2fb8554594e99c23a34d62'
$meta=[ordered]@{run_id=$RunId;php_target=$Php;application_sha=$sha;application_tree='e5a4dc3c8daff38e09ed8f1fb1f8225366a91f47';php_image=$image;db_image=$dbImage;network=$net;volume=$volume;db_container=$db;containers=@($prep,$quality,$app);status='PREPARING';started_at=[DateTime]::UtcNow.ToString('o');prepare_exit=$null;quality_exit=$null;application_exit=$null;filtered_output_lines=0;github_actions=$false;full_acceptance=$false;chromium=$false;marathons=$false;host_ports_published=$false}
$metaPath=Join-Path $taskRoot "result-$Php.json"
function Save-Meta {[IO.File]::WriteAllText($metaPath,($meta | ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))}
$writer=[IO.StreamWriter]::new((Join-Path $taskRoot "sanitized-$Php.txt"),$false,[Text.UTF8Encoding]::new($false))
function Log-Safe([string]$line){$writer.WriteLine($line);$writer.Flush();if($line -match '^LOCAL_|^FAST_SUITE|^\d+ tests,|^FAIL '){Write-Host $line}}
function Invoke-CapturedDocker([string[]]$DockerArgs){
    & docker @DockerArgs 2>&1 | ForEach-Object {
        $line=$_.ToString()
        if($line -match '^FAIL '){$label=($line -split ': ',2)[0];Log-Safe "$label`: [details redacted]"}
        elseif($line -match '^PASS |^FAST_SUITE |^\d+ tests, \d+ failures$|^LOCAL_|^SOURCE_PROOF|^\s*"(application_sha|checkout_sha|tree|workflow_sha|run_id|php)"\s*:|^\s*[{}]\s*$|^\d+ PHP files checked;|^\d+ source/config/test files scanned;|^\d+ documentation files:|No security vulnerability advisories found|\[OK\] No errors|^\d+ state checks,|found 0 vulnerabilities') {Log-Safe $line}
        else{$meta.filtered_output_lines++}
    }
    return $LASTEXITCODE
}
try{
    Save-Meta
    & docker network create --internal --label "hostider.r2h=$RunId" $net | Out-Null;if($LASTEXITCODE -ne 0){throw 'Network create failed'}
    & docker volume create --label "hostider.r2h=$RunId" $volume | Out-Null;if($LASTEXITCODE -ne 0){throw 'Volume create failed'}
    $dbArgs=@('run','-d','--name',$db,'--network',$net,'--log-driver','none','--label',"hostider.r2h=$RunId",'--tmpfs','/var/lib/mysql:rw,size=768m','-e','MARIADB_ROOT_PASSWORD=isolated-ci-password','-e','MARIADB_DATABASE=hostider_f0_test','--health-cmd','healthcheck.sh --connect --innodb_initialized','--health-interval','2s','--health-retries','40',$dbImage)
    & docker @dbArgs | Out-Null;if($LASTEXITCODE -ne 0){throw 'Owned DB start failed'}
    $mounts=@('--mount',"type=volume,source=$volume,target=/workspace",'--mount',"type=bind,source=$taskRoot,target=/input,readonly")
    $envArgs=@('-e',"GITHUB_SHA=$sha",'-e',"APPLICATION_SHA=$sha",'-e','CI_BEFORE=d62a828fc3584144d11703386a2d7752eb0b08cb','-e','APP_ENV=test','-e','HTTP_TEST_ENGINE=interpreter','-e','TEST_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=hostider_f0_test;charset=utf8mb4','-e','TEST_DB_USER=root','-e','TEST_DB_PASSWORD=isolated-ci-password')
    $common=@('run','--rm','--log-driver','none','--label',"hostider.r2h=$RunId")+$mounts+$envArgs+@('--entrypoint','sh')
    Log-Safe "LOCAL_STAGE prepare PHP$Php"
    $meta.prepare_exit=Invoke-CapturedDocker ($common+@('--name',$prep,$image,'/input/prepare.sh'));Save-Meta
    if($meta.prepare_exit -ne 0){throw 'Preparation failed; raw output not exported'}
    Log-Safe "LOCAL_STAGE quality PHP$Php"
    $meta.quality_exit=Invoke-CapturedDocker ($common+@('--name',$quality,$image,'/input/quality.sh'));Save-Meta
    $healthy=$false;for($i=0;$i -lt 40;$i++){$state=& docker inspect --format '{{.State.Health.Status}}' $db;if($state -eq 'healthy'){$healthy=$true;break};Start-Sleep -Seconds 2}
    if(-not $healthy){throw 'Owned DB health failed'}
    $meta.status='RUNNING_NATIVE_BROAD';Save-Meta;Log-Safe "LOCAL_STAGE application PHP$Php"
    $meta.application_exit=Invoke-CapturedDocker ($common+@('--name',$app,'--network',"container:$db",$image,'/input/application.sh'))
    $meta.status=if($meta.quality_exit -eq 0 -and $meta.application_exit -eq 0){'SUCCESS'}else{'FAILURE'}
}catch{$meta.status='BLOCKED_OR_FAILED';$meta.failure_safe=$_.Exception.Message;Log-Safe "LOCAL_ORCHESTRATION_FAILURE PHP$Php"}
finally{
    $meta.finished_at=[DateTime]::UtcNow.ToString('o');Save-Meta;$writer.Dispose()
    & docker stop --time 20 $db 2>&1 | Out-Null
}
Write-Output ($meta | ConvertTo-Json -Depth 8)
if($meta.status -ne 'SUCCESS'){exit 1}
