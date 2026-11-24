<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/release-gate-lib.php';
function rrCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function rrBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
rrCheck(count(recLedgers())===12&&!isset(recLedgers()['arbitrary']),'Ledger allowlist');
if(($argv[1]??'')!=='--database'){echo "Reconciliation allowlist: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $cfg=['growth'=>['enabled'=>true],'reconciliation'=>['enabled'=>true]];
    growthCommand($db,'/pause',$cfg);$before=recUnknownCounts($db);
    $task=growthCreate($db,['service_id'=>'infrastructure','task_type'=>'social','title'=>'Synthetic review','draft'=>'Synthetic']);
    $db->prepare("INSERT INTO growth_publications(task_id,revision,target_chat_id,due_at,status) VALUES(?,1,'-1001234567890',UTC_TIMESTAMP(),'unknown')")->execute([$task]);$id=(int)$db->lastInsertId();
    $r=recState($db,'telegram-text',$id,true);$h=$r['state_hash'];$e=str_repeat('a',64);
    rrCheck(recUnknownCounts($db)['unreviewed']===$before['unreviewed']+1,'Unknown blocks');
    rrBlocked(static fn()=>recRecord($db,[],'telegram-text',$id,$h,'confirmed_present',$e));
    rrBlocked(static fn()=>recRecord($db,$cfg,'telegram-text',$id,str_repeat('0',64),'confirmed_present',$e));
    rrBlocked(static fn()=>recRecord($db,$cfg,'telegram-text',$id,$h,'unresolved',$e));
    recRecord($db,$cfg,'telegram-text',$id,$h,'confirmed_present',$e);
    $after=recUnknownCounts($db);rrCheck($after['unreviewed']===$before['unreviewed']&&$after['reviewed']===$before['reviewed']+1&&recState($db,'telegram-text',$id)['state_hash']===$h,'Reviewed without changing original');
    $db->prepare("UPDATE growth_publications SET provider_message_id='123' WHERE id=?")->execute([$id]);
    rrCheck(recUnknownCounts($db)['unreviewed']===$before['unreviewed']+1,'Changed evidence blocks');
    growthCommand($db,'/resume',$cfg);rrBlocked(static fn()=>recRecord($db,$cfg,'telegram-text',$id,recState($db,'telegram-text',$id)['state_hash'],'confirmed_absent',$e));
    $db->rollBack();echo "Synthetic reconciliation and changed-state gate: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Reconciliation test failed: '.$e->getMessage()."\n");exit(1);}
