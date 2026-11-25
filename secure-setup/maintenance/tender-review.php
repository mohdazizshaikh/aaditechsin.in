<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/tender-review-lib.php';
try{
    $mode=$argv[1]??'';
    if(!((in_array($mode,['--shortlist','--plan'],true)&&count($argv)===2)||($mode==='--apply'&&count($argv)===3)))throw new InvalidArgumentException('Explicit mode required');
    $raw=stream_get_contents(STDIN,65537);if(!is_string($raw))throw new RuntimeException('Input unavailable');
    if($mode==='--shortlist'){$r=tenderShortlist($raw);}
    else{
        require dirname(__DIR__,2).'/public_html/api/operations.php';
        $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
        if($mode==='--plan'){$r=tenderReviewPlan($db,$cfg,$raw);$db->rollBack();}
        else{$r=tenderReviewApply($db,$cfg,$raw,$argv[2]);$db->commit();}
    }
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: notice, scope, deadline, review gate or database error. No bid authorized.\n");exit(1);}
