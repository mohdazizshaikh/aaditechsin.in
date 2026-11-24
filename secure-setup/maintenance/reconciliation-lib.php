<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/growth-core.php';
function recLedgers():array{return [
 'telegram-text'=>'growth_publications','meta-text'=>'growth_meta_publications','network-text'=>'growth_network_publications','youtube'=>'growth_media_uploads','instagram-reel'=>'growth_instagram_uploads','facebook-media'=>'growth_facebook_media','network-image'=>'growth_network_media','meta-media'=>'growth_meta_images','linkedin-video'=>'growth_linkedin_video','x-video'=>'growth_x_video','carousel'=>'growth_carousels','telegram-media'=>'growth_telegram_media'
];}
function recPause(PDO $db):void{
    if(!$db->inTransaction()||(int)$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn()!==1)throw new RuntimeException('Paused transaction required');
}
function recState(PDO $db,string $ledger,int $id,bool $lock=false):array{
    $table=recLedgers()[$ledger]??null;if(!$table||$id<1)throw new InvalidArgumentException('Invalid ledger');
    $q=$db->prepare("SELECT * FROM $table WHERE id=? AND status='unknown'".($lock?' FOR UPDATE':''));$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Unknown row required');
    // Hash the complete stored row and any intermediate resource evidence, without displaying contents.
    ksort($r);$extra=[];
    if($ledger==='network-image'){$q=$db->prepare('SELECT * FROM growth_network_media_resources WHERE publication_id=?');$q->execute([$id]);$extra=$q->fetchAll(PDO::FETCH_ASSOC);}
    if($ledger==='carousel'){$q=$db->prepare('SELECT * FROM growth_carousel_items WHERE carousel_id=? ORDER BY position_no');$q->execute([$id]);$extra=$q->fetchAll(PDO::FETCH_ASSOC);}
    foreach($extra as &$item)ksort($item);unset($item);
    $digest=hash('sha256',json_encode([$ledger,$r,$extra],JSON_THROW_ON_ERROR));
    return ['ledger'=>$ledger,'id'=>$id,'task_id'=>(int)$r['task_id'],'revision'=>(int)$r['revision'],'target'=>$r['target_id']??$r['target_chat_id']??'','provider'=>$r['provider']??$ledger,'receipt'=>$r['receipt']??$r['provider_message_id']??null,'resource'=>$r['video_id']??$r['container_id']??null,'state_hash'=>$digest];
}
function recRecord(PDO $db,array $cfg,string $ledger,int $id,string $digest,string $outcome,string $evidence):void{
    if(($cfg['reconciliation']['enabled']??false)!==true)throw new RuntimeException('Disabled');recPause($db);
    if(!in_array($outcome,['confirmed_present','confirmed_absent','unresolved'],true)||!preg_match('/^[a-f0-9]{64}$/D',$digest)||!preg_match('/^[a-f0-9]{64}$/D',$evidence))throw new InvalidArgumentException('Invalid review');
    $r=recState($db,$ledger,$id,true);if(!hash_equals($r['state_hash'],$digest))throw new RuntimeException('State changed');
    $q=$db->prepare('INSERT INTO growth_publication_reviews(ledger,publication_id,state_hash,evidence_hash,outcome) VALUES(?,?,?,?,?)');$q->execute([$ledger,$id,$digest,$evidence,$outcome]);
    growthAudit($db,$r['task_id'],$r['revision'],'publication_review','private_cli');
}
function recUnknownCounts(PDO $db):array{
    $out=['unreviewed'=>0,'reviewed'=>0];
    foreach(recLedgers() as $ledger=>$table){
        $n=(int)$db->query("SELECT COUNT(*) FROM $table WHERE status='unknown'")->fetchColumn();
        if($n>1000){$out['unreviewed']+=$n;continue;}
        foreach($db->query("SELECT id FROM $table WHERE status='unknown' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id){
            $r=recState($db,$ledger,(int)$id);$q=$db->prepare("SELECT COUNT(*) FROM growth_publication_reviews WHERE ledger=? AND publication_id=? AND state_hash=? AND outcome IN ('confirmed_present','confirmed_absent')");$q->execute([$ledger,$id,$r['state_hash']]);
            ++$out[(int)$q->fetchColumn()===1?'reviewed':'unreviewed'];
        }
    }return $out;
}
