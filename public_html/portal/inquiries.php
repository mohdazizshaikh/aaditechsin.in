<?php
declare(strict_types=1);
require __DIR__.'/../api/inquiry-queue-core.php';
opsHeaders();
try{
    if(!opsSsl()||!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true))throw new RuntimeException('Unavailable');
    $cfg=opsConfig();opsSession();if(!inquiryQueueOwner($cfg,$_SESSION,time()))throw new RuntimeException('Unavailable');
    $db=opsDb($cfg);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        opsCheckCsrf();
        if(count($_POST)!==6||($_POST['action']??null)!=='record_response'||!is_string($_POST['lead_id']??null)||!preg_match('/^[1-9][0-9]{0,14}$/D',$_POST['lead_id'])||!is_string($_POST['status']??null)||!is_string($_POST['updated_at']??null)||!is_string($_POST['confirmed']??null))throw new InvalidArgumentException('Unavailable');
        $db->beginTransaction();$changed=inquiryQueueRecord($db,$cfg,(int)$_POST['lead_id'],$_POST['status'],$_POST['updated_at'],$_POST['confirmed']);$db->commit();
        $_SESSION['inquiry_flash']=$changed?'Response recorded. No message was sent by this tool.':'Inquiry changed; review the current status.';
        $_SESSION['last_seen']=time();header('Location: /portal/inquiries.php',true,303);exit;
    }
    if(array_diff(array_keys($_GET),['filter','offset']))throw new InvalidArgumentException('Unavailable');
    $filter=$_GET['filter']??'all';$offset=$_GET['offset']??'0';
    if(!is_string($filter)||!is_string($offset)||!preg_match('/^(0|[1-9][0-9]{0,4})$/D',$offset))throw new InvalidArgumentException('Unavailable');
    $db->beginTransaction();$data=inquiryQueueSnapshot($db,$cfg,$filter,(int)$offset,null,true);$db->commit();
    $csrf=opsCsrf();$flash=$_SESSION['inquiry_flash']??'';unset($_SESSION['inquiry_flash']);$_SESSION['last_seen']=time();session_write_close();
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);exit('Unavailable');}
function iqh(mixed $v):string{return opsH((string)$v);}
?><!doctype html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private inquiry response queue</title><link rel="stylesheet" href="/assets/portal.css"></head><body>
<header><div class="shell top"><a class="brand" href="/portal/">Aaditechs <small>Private inquiry queue</small></a><a href="/portal/">Operations portal</a></div></header>
<main class="shell dashboard"><h1>Customer inquiry response queue</h1><p>Oldest first, based on saved inquiry status. Internal response target: <?=iqh($data['target_hours'])?> hours from intake; not a customer-facing SLA or proof of missed contact.</p>
<?php if($flash): ?><p class="alert" role="status"><?=iqh($flash)?></p><?php endif; ?>
<div class="dash-metrics"><?php foreach(['total'=>'Awaiting recorded response','due_count'=>'Target reached','new_count'=>'New','qualified_count'=>'Qualified'] as $k=>$label): ?><div class="panel"><span class="small"><?=iqh($label)?></span><strong><?=iqh($data['counts'][$k])?></strong></div><?php endforeach; ?></div>
<nav class="dash-period" aria-label="Filter inquiries"><?php foreach(['all'=>'All awaiting response','due'=>'Target reached','new'=>'New','qualified'=>'Qualified'] as $k=>$label): ?><a href="?filter=<?=iqh($k)?>" <?=$filter===$k?'aria-current="page"':''?>><?=iqh($label)?></a><?php endforeach; ?></nav>
<section class="panel"><h2>Review inquiries</h2><p class="small">Contact only about the customer's requested inquiry, through an authorized channel. Do not mark responded until you have actually made a response. This page never sends a message.</p>
<?php if(!$data['rows']): ?><p>No matching inquiries in this page.</p><?php endif; ?>
<?php foreach($data['rows'] as $r): ?><details class="entry"><summary><strong><?=iqh($r['reference'])?></strong> · <?=iqh($r['service'])?> · <?=iqh($r['status'])?> · <?=$r['due']?'Target reached':'Within target'?> · <?=iqh($r['created_at'])?> UTC</summary><p><?=iqh($r['full_name'])?> · <?=iqh($r['email'])?> · <?=iqh($r['phone'])?></p><p>Intake route: <?=iqh($r['source'])?></p><pre><?=iqh($r['message'])?></pre><p>Suggested response structure: acknowledge the inquiry, clarify scope and constraints, offer a practical next step, and confirm that pricing and availability require assessment.</p>
<form method="post"><input type="hidden" name="csrf" value="<?=iqh($csrf)?>"><input type="hidden" name="lead_id" value="<?=iqh($r['id'])?>"><input type="hidden" name="status" value="<?=iqh($r['status'])?>"><input type="hidden" name="updated_at" value="<?=iqh($r['updated_at'])?>"><label><input type="checkbox" name="confirmed" value="yes" required> I have responded to this inquiry through an authorized channel.</label><button name="action" value="record_response">Record response</button></form></details><?php endforeach; ?>
<nav class="dash-period" aria-label="Inquiry pages"><?php if((int)$offset>0): ?><a href="?filter=<?=iqh($filter)?>&amp;offset=<?=iqh((int)$offset-25)?>">Previous</a><?php endif; ?><?php if($data['has_more']&&(int)$offset<10000): ?><a href="?filter=<?=iqh($filter)?>&amp;offset=<?=iqh((int)$offset+25)?>">Next</a><?php endif; ?></nav>
</section></main></body></html>
