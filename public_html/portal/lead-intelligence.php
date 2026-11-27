<?php
declare(strict_types=1);
require __DIR__.'/../api/lead-intelligence-core.php';
opsHeaders();
try{
    if(!opsSsl()||!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true))throw new RuntimeException('Unavailable');
    $cfg=opsConfig();opsSession();
    if(!leadIntelEnabled($cfg)||!inquiryQueueOwner($cfg,$_SESSION,time()))throw new RuntimeException('Unavailable');
    $db=opsDb($cfg);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        opsCheckCsrf();$action=$_POST['action']??null;$fields=match($action){
            'qualify'=>['csrf','action','lead_id','status','updated_at','confirmed'],
            'reminder'=>['csrf','action','lead_id','status','updated_at','day','reason','confirmed'],
            'close'=>['csrf','action','task_id','revision','decision','confirmed'],
            default=>throw new InvalidArgumentException('Action')
        };
        if(count($_POST)!==count($fields)||array_diff(array_keys($_POST),$fields))throw new InvalidArgumentException('Fields');
        foreach($fields as $field)if(!is_string($_POST[$field]??null))throw new InvalidArgumentException('Field type');
        foreach(['lead_id','task_id','revision'] as $field)if(isset($_POST[$field])&&!preg_match('/^[1-9][0-9]{0,14}$/D',$_POST[$field]))throw new InvalidArgumentException('ID');
        $db->beginTransaction();
        $result=match($action){
            'qualify'=>leadIntelQualify($db,$cfg,(int)$_POST['lead_id'],$_POST['status'],$_POST['updated_at'],$_POST['confirmed'])?'Qualification recorded.':'Inquiry changed or service unmapped.',
            'reminder'=>match(leadIntelReminder($db,$cfg,(int)$_POST['lead_id'],$_POST['status'],$_POST['updated_at'],$_POST['day'],$_POST['reason'],$_POST['confirmed'])['status']){
                'created'=>'Internal review reminder created.','existing_open'=>'An open reminder already exists.','existing_history'=>'This exact reminder already exists in history.',default=>'Inquiry changed or service unmapped.'
            },
            'close'=>leadIntelClose($db,$cfg,(int)$_POST['task_id'],(int)$_POST['revision'],$_POST['decision'],$_POST['confirmed'])?'Reminder disposition recorded.':'Reminder changed; review again.'
        };
        if(opsConfig()!==$cfg)throw new RuntimeException('Config changed');
        $db->commit();$_SESSION['lead_intel_flash']=$result.' No customer message sent.';$_SESSION['last_seen']=time();
        header('Location: /portal/lead-intelligence.php',true,303);exit;
    }
    if($_GET)throw new InvalidArgumentException('No query fields');
    $db->beginTransaction();$data=leadIntelSnapshot($db,$cfg,null,true);
    $leads=$db->query("SELECT id,reference,service,status,created_at,updated_at FROM leads WHERE status IN ('new','qualified','responded') ORDER BY created_at,id LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);$db->commit();
    $csrf=opsCsrf();$flash=$_SESSION['lead_intel_flash']??'';unset($_SESSION['lead_intel_flash']);$_SESSION['last_seen']=time();session_write_close();
    $today=gmdate('Y-m-d');$max=gmdate('Y-m-d',time()+90*86400);
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);exit('Unavailable');}
function lih(mixed $v):string{return opsH((string)$v);}
?><!doctype html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private inquiry qualification and follow-up</title><link rel="stylesheet" href="/assets/portal.css"></head><body>
<header><div class="shell top"><a class="brand" href="/portal/">Aaditechs <small>Private lead review</small></a><a href="/portal/inquiries.php">Inquiry queue</a></div></header>
<main class="shell dashboard"><h1>Inquiry qualification and follow-up</h1><p>Internal review only. Review actual requirements and consent in the operations portal before qualifying or contacting anyone. No automated messages, quotes or case creation.</p>
<?php if($flash): ?><p class="alert" role="status"><?=lih($flash)?></p><?php endif; ?>
<div class="dash-metrics"><?php foreach(['open'=>'Active open reminders','due'=>'Due or overdue UTC','inactive_lead'=>'Inactive inquiry reminders','unverified'=>'Require reconciliation'] as $k=>$label): ?><div class="panel"><span class="small"><?=lih($label)?></span><strong><?=lih($data['counts'][$k])?></strong></div><?php endforeach; ?></div>
<?php if($data['truncated']): ?><p>Reminder counts limited to latest 250 records.</p><?php endif; ?>
<section class="panel"><h2>Review active inquiries (oldest 50)</h2><p><a href="/portal/">Read stored inquiry details in the operations portal.</a></p>
<?php foreach($leads as $r): ?><details class="entry"><summary><?=lih($r['reference'])?> · <?=lih($r['service'])?> · <?=lih($r['status'])?></summary>
<?php if(!leadIntelService($r['service'])): ?><p>Unmapped service. Review manually in the operations portal.</p><?php else: ?>
<?php if($r['status']==='new'): ?><form method="post"><input type="hidden" name="csrf" value="<?=lih($csrf)?>"><input type="hidden" name="lead_id" value="<?=lih($r['id'])?>"><input type="hidden" name="status" value="<?=lih($r['status'])?>"><input type="hidden" name="updated_at" value="<?=lih($r['updated_at'])?>"><label><input type="checkbox" name="confirmed" value="yes" required> I independently reviewed requested scope, service fit and consent. This is not a quote or sale.</label><button name="action" value="qualify">Record qualification</button></form><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?=lih($csrf)?>"><input type="hidden" name="lead_id" value="<?=lih($r['id'])?>"><input type="hidden" name="status" value="<?=lih($r['status'])?>"><input type="hidden" name="updated_at" value="<?=lih($r['updated_at'])?>"><label>Review date UTC<input type="date" name="day" required min="<?=lih($today)?>" max="<?=lih($max)?>" value="<?=lih($today)?>"></label><label>Review reason<select name="reason"><?php foreach(leadIntelReasons() as $key=>$label): ?><option value="<?=lih($key)?>"><?=lih($label)?></option><?php endforeach; ?></select></label><label><input type="checkbox" name="confirmed" value="yes" required> I reviewed the current inquiry and authorize an internal reminder only.</label><button name="action" value="reminder">Set internal reminder</button></form><?php endif; ?></details><?php endforeach; ?>
</section><section class="panel"><h2>Open reminder review</h2><?php if(!$data['rows']): ?><p>No verifiable open reminders in this view.</p><?php endif; ?>
<?php foreach($data['rows'] as $r): ?><details class="entry"><summary>Inquiry #<?=lih($r['lead_id'])?> · <?=lih($r['service'])?> · <?=lih($r['day'])?> UTC · <?=lih($r['lead_status'])?></summary><p><?=lih(leadIntelReasons()[$r['reason']])?></p><form method="post"><input type="hidden" name="csrf" value="<?=lih($csrf)?>"><input type="hidden" name="task_id" value="<?=lih($r['task_id'])?>"><input type="hidden" name="revision" value="<?=lih($r['revision'])?>"><label>Disposition<select name="decision"><option value="reviewed">Review completed (not contact confirmation)</option><option value="cancelled">Cancel internal reminder</option></select></label><label><input type="checkbox" name="confirmed" value="yes" required> I reviewed this reminder and confirm the disposition.</label><button name="action" value="close">Record disposition</button></form></details><?php endforeach; ?>
</section></main></body></html>
