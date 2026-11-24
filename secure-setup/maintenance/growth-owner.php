<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/growth-core.php';
require_once dirname(__DIR__,2).'/public_html/api/discovery-core.php';
try{
    $cfg=opsConfig();if(($cfg['growth']['enabled']??false)!==true)throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$db->beginTransaction();
    $mode=$argv[1]??'--today';
    if(count($argv)>2)throw new InvalidArgumentException('Invalid arguments');
    if(in_array($mode,['--create','--replace','--import-opportunity'],true)){
        $raw=stream_get_contents(STDIN,8193);if(strlen($raw)>8192)throw new InvalidArgumentException('Too large');
        $input=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($input))throw new InvalidArgumentException('Invalid JSON');
        if($mode==='--import-opportunity'){
            if(!discoveryEnabled($cfg))throw new RuntimeException('Discovery disabled');
            $result=discoveryImport($db,$input);
            $reply='Opportunity #'.$result['id'].' '.$result['status'].' (no external action)';
        }elseif($mode==='--create')$reply='Pending task #'.growthCreate($db,$input).' revision 1';
        else{
            if(!is_int($input['id']??null)||!is_int($input['revision']??null)||!is_string($input['draft']??null))throw new InvalidArgumentException('Invalid replacement');
            $q=$db->prepare('SELECT 1 FROM growth_creative WHERE task_id=?');$q->execute([$input['id']]);
            if($q->fetchColumn()!==false)throw new InvalidArgumentException('Use creative-worker import for creative results');
            $reply=growthReplace($db,$input['id'],$input['revision'],$input['draft'])?'Revised draft pending review':'No matching regeneration request';
        }
    }elseif($mode==='--today')$reply=growthCommand($db,'/today',$cfg);
    else throw new InvalidArgumentException('Unsupported command');
    $db->commit();echo $reply."\n";
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,"BLOCKED: growth configuration, database or input invalid.\n");exit(1);
}
