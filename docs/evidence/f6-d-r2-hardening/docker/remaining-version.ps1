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
$metaPath=Join-Path $taskRoot "remaining-result-$Php.json"
function Save-Meta {[IO.File]::WriteAllText($metaPath,($meta | ConvertTo-Json -Depth 8),[Text.UTF8Encoding]::new($false))}
$writer=[IO.StreamWriter]::new((Join-Path $taskRoot "remaining-sanitized-$Php.txt"),$false,[Text.UTF8Encoding]::new($false))
function Log-Safe([string]$line){$writer.WriteLine($line);$writer.Flush();if($line -match '^LOCAL_|^FAST_SUITE|^\d+ tests,|^FAIL '){Write-Host $line}}
function Invoke-CapturedDocker([string[]]$DockerArgs){
    & docker @DockerArgs 2>&1 | ForEach-Object {
        $line=$_.ToString()
        if($line -match '^FAIL '){$label=($line -split ': ',2)[0];Log-Safe "$label`: [details redacted]"}
        elseif($line -match '^CONTINUATION_|^\d+ dependent tests BLOCKED|^PASS |^FAST_SUITE |^\d+ tests, \d+ failures$|^LOCAL_|^SOURCE_PROOF|^\s*"(application_sha|checkout_sha|tree|workflow_sha|run_id|php)"\s*:|^\s*[{}]\s*$|^\d+ PHP files checked;|^\d+ source/config/test files scanned;|^\d+ documentation files:|No security vulnerability advisories found|\[OK\] No errors|^\d+ state checks,|found 0 vulnerabilities') {Log-Safe $line}
        else{$meta.filtered_output_lines++}
    }
    return $LASTEXITCODE
}
try {
 & docker start $db | Out-Null
 for($i=0;$i -lt 40;$i++){if((& docker inspect --format '{{.State.Health.Status}}' $db) -eq 'healthy'){break};Start-Sleep -Seconds 2}
 $argsDocker=@('run','--rm','--log-driver','none','--label',"hostider.r2h=$RunId",'--name',"$prefix-continuation",'--network',"container:$db",'--mount',"type=volume,source=$volume,target=/workspace",'--mount',"type=bind,source=$taskRoot,target=/input,readonly",'-e',"GITHUB_SHA=$sha",'-e',"APPLICATION_SHA=$sha",'-e','APP_ENV=test','-e','HTTP_TEST_ENGINE=interpreter','-e','TEST_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=hostider_f0_test;charset=utf8mb4','-e','TEST_DB_USER=root','-e','TEST_DB_PASSWORD=isolated-ci-password','--entrypoint','sh',$image,'/input/remaining.sh')
 $meta.application_exit=Invoke-CapturedDocker $argsDocker
 $meta.status=if($meta.application_exit -eq 0){'SUCCESS'}else{'FAILURE'}
} finally { $meta.finished_at=[DateTime]::UtcNow.ToString('o');Save-Meta;$writer.Dispose(); & docker stop --time 20 $db 2>&1 | Out-Null }
Write-Output ($meta | ConvertTo-Json -Depth 8)
