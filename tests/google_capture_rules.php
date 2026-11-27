<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/google-capture-lib.php';
function gcCheck(bool $ok,string $m):void{if(!$ok)throw new LogicException($m);}
function gcBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$t=$now->getTimestamp();
$creds=['client_id'=>'synthetic-client','client_secret'=>'synthetic-secret','refresh_token'=>'synthetic-refresh'];
$review=$now->modify('+1 day')->format('Y-m-d\TH:i:s\Z');
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'gmail'=>['enabled'=>true,'revoked'=>false,'mailbox'=>'synthetic@example.com','generation'=>str_repeat('d',32),'expires_utc'=>'2000-01-01T00:00:00Z','access_token'=>'expired','renewal'=>$creds+['enabled'=>true,'authorized'=>true,'scheduled_capture_enabled'=>true,'review_expires_utc'=>$review]]],'seo'=>['enabled'=>true,'search_console'=>$creds+['enabled'=>true,'authorized'=>true,'revoked'=>false,'scheduled_capture_enabled'=>true,'property'=>'sc-domain:aaditechs.in','generation'=>str_repeat('e',32),'review_expires_utc'=>$review]]];
gcCheck(gmailConfig($cfg,$t)===null&&gmailAutoGrant($cfg,$t)!==null,'Independent reviewed grant');
gcBlocked(static fn()=>gmailRenewConfig($cfg));
foreach(['gmail','search-console'] as $provider){
    $scope=$provider==='gmail'?'https://www.googleapis.com/auth/gmail.metadata':'https://www.googleapis.com/auth/webmasters.readonly';
    $p=googleCaptureGrant($cfg,$provider,$t);
    $good=['access_token'=>str_repeat('t',40),'expires_in'=>3600,'token_type'=>'Bearer','scope'=>$scope];
    gcCheck(googleEphemeralToken($p,$scope,static fn()=>$good)['lifetime']===3600,'Exact scope');
    foreach([['scope'=>'wrong'],['scope'=>$scope.' extra'],['scope'=>null],['expires_in'=>660],['expires_in'=>86401],['expires_in'=>'3600'],['token_type'=>'Other'],['access_token'=>'bad'],['refresh_token'=>null],['error'=>null]] as $patch)gcBlocked(static fn()=>googleEphemeralToken($p,$scope,static fn()=>array_replace($good,$patch)));
    gcBlocked(static fn()=>googleCaptureGrant($cfg,$provider,$t+86400));
}
$bad=$cfg;$bad['communications']['gmail']['revoked']=true;gcBlocked(static fn()=>googleCaptureGrant($bad,'gmail',$t));
$bad=$cfg;$bad['communications']['gmail']['renewal']['authorized']=false;gcBlocked(static fn()=>googleCaptureGrant($bad,'gmail',$t));
$bad=$cfg;$bad['seo']['search_console']['revoked']=true;gcBlocked(static fn()=>googleCaptureGrant($bad,'search-console',$t));
echo "Google scheduled grants: expiry, revoke, exact scope and token response rules PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$pause=static function($cmd)use($db,$cfg){$db->beginTransaction();growthCommand($db,$cmd,$cfg);$db->commit();};
$clock=static fn()=>$now;$get=static fn()=>$cfg;
$gmailToken=['access_token'=>str_repeat('t',40),'expires_in'=>3600,'token_type'=>'Bearer','scope'=>'https://www.googleapis.com/auth/gmail.metadata'];
$read=static fn($p,$resource)=>$resource==='profile'?['emailAddress'=>'synthetic@example.com']:['id'=>'INBOX','type'=>'system','messagesTotal'=>10,'messagesUnread'=>2];
$pause('/pause');$calls=0;
$exchange=static function()use(&$calls,$gmailToken){++$calls;return $gmailToken;};
gcBlocked(static fn()=>googleCaptureRun($db,$get,'gmail',$exchange,$read,$clock));gcCheck($calls===0,'No exchange while paused');
$pause('/resume');
$r=googleCaptureRun($db,$get,'gmail',$exchange,$read,$clock);gcCheck($r['status']==='captured'&&!$r['token_persisted'],'Gmail snapshot');
gcCheck(str_contains(gmailSummary($db,$cfg,$now),'10 messages, 2 unread'),'Snapshot works despite expired static token');
gcCheck(googleCaptureRun($db,$get,'gmail',$exchange,$read,$clock)['status']==='not_due'&&$calls===1,'Fresh snapshot skips exchange');
$db->exec("UPDATE growth_gmail_snapshot SET captured_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 HOUR) WHERE generation='".str_repeat('d',32)."'");
$mutable=$cfg;
gcBlocked(static fn()=>googleCaptureRun($db,static function()use(&$mutable){return $mutable;},'gmail',static function()use(&$mutable,$gmailToken){$mutable['communications']['gmail']['revoked']=true;return $gmailToken;},$read,$clock));
$scToken=array_replace($gmailToken,['scope'=>'https://www.googleapis.com/auth/webmasters.readonly']);
$scRead=static fn($p,$token,$resource,$body)=>$resource==='site'?['siteUrl'=>$p['property'],'permissionLevel'=>'siteRestrictedUser']:['responseAggregationType'=>'byProperty','rows'=>[['keys'=>[$body['startDate']],'clicks'=>1,'impressions'=>2,'position'=>3.0]]];
$r=googleCaptureRun($db,$get,'search-console',static fn()=>$scToken,$scRead,$clock);gcCheck($r['status']==='captured'&&str_contains(scSummary($db,$cfg,$now),'Clicks 1'),'Search Console snapshot');
gcCheck(googleCaptureRun($db,$get,'search-console',static fn()=>throw new LogicException('Unexpected exchange'),$scRead,$clock)['status']==='not_due','Search cadence');

// Fail closed between individual Gmail/SC provider reads, including mid-flight revocation.
foreach(['gmail','search-console'] as $provider){
    $token=$provider==='gmail'?$gmailToken:array_replace($gmailToken,['scope'=>'https://www.googleapis.com/auth/webmasters.readonly']);
    $first=$provider==='gmail'?'profile':'site';
    $table=$provider==='gmail'?'growth_gmail_snapshot':'growth_search_console';
    $db->exec("UPDATE ".$table." SET captured_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 5 HOUR) WHERE id=1");
    $before=$db->query("SELECT * FROM ".$table." WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    foreach(['revoke','pause','database','clock-back','token-elapsed','identity'] as $event){
        $mutable=$cfg;$mutable['database']=['enabled'=>true,'name'=>'synthetic-db'];
        $moment=$now;$resources=[];$exchanges=0;
        $getMutable=static function()use(&$mutable){return $mutable;};
        $getTime=static function()use(&$moment){return $moment;};
        $exchangeOnce=static function()use(&$exchanges,$token){++$exchanges;return $token;};
        $handle=static function(string $resource)use(&$resources,$first,$event,&$mutable,&$moment,$provider,$pause):array{
            $resources[]=$resource;
            if($resource!==$first)throw new LogicException('Second provider read must not run');
            switch($event){
                case 'revoke':
                    if($provider==='gmail')$mutable['communications']['gmail']['revoked']=true;
                    else $mutable['seo']['search_console']['revoked']=true;
                    break;
                case 'pause':$pause('/pause');break;
                case 'database':$mutable['database']['name']='different-db';break;
                case 'clock-back':$moment=$moment->modify('-1 second');break;
                case 'token-elapsed':$moment=$moment->modify('+3540 seconds');break;
            }
            if($provider==='gmail')return ['emailAddress'=>$event==='identity'?'wrong@example.com':'synthetic@example.com'];
            return ['siteUrl'=>$event==='identity'?'sc-domain:wrong.example':'sc-domain:aaditechs.in','permissionLevel'=>'siteRestrictedUser'];
        };
        $guardRead=$provider==='gmail'?static fn($p,$resource)=>$handle($resource):static fn($p,$token,$resource,$body)=>$handle($resource);
        try{
            gcBlocked(static fn()=>googleCaptureRun($db,$getMutable,$provider,$exchangeOnce,$guardRead,$getTime));
            gcCheck($resources===[$first]&&$exchanges===1,'Only first read: '.$provider.' '.$event);
            gcCheck(!$db->inTransaction(),'Transaction released: '.$provider.' '.$event);
            gcCheck($db->query("SELECT * FROM ".$table." WHERE id=1")->fetch(PDO::FETCH_ASSOC)===$before,'Snapshot preserved: '.$provider.' '.$event);
            $lock=$db->prepare('SELECT IS_FREE_LOCK(?)');$lock->execute(['aaditechs-google-capture-'.$provider]);
            gcCheck((string)$lock->fetchColumn()==='1','Lock released: '.$provider.' '.$event);
        }finally{$pause('/resume');}
    }
}
echo "Twelve inter-read safety scenarios preserve snapshots and release locks PASS\n";
$db->exec("DELETE FROM growth_gmail_snapshot WHERE generation='".str_repeat('d',32)."'");
$db->exec("DELETE FROM growth_search_console WHERE generation='".str_repeat('e',32)."'");
$pause('/resume');echo "Disposable Gmail/SC auto-refresh, pause, grant change, cadence and aggregate capture PASS\n";
