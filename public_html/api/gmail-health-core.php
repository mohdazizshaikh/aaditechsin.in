<?php
declare(strict_types=1);
require_once __DIR__.'/communications-core.php';
require_once __DIR__.'/creative-core.php';
function gmailConfig(array $cfg,?int $now=null):?array{
    if(!communicationsEnabled($cfg)||($cfg['communications']['gmail']['enabled']??false)!==true)return null;
    $p=$cfg['communications']['gmail'];$now=$now??time();
    $email=$p['mailbox']??null;$token=$p['access_token']??null;$gen=$p['generation']??null;$expiry=$p['expires_utc']??null;
    if(!is_string($email)||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$token)||!is_string($gen)||!preg_match('/^[a-f0-9]{32}$/D',$gen)||!is_string($expiry)||($p['revoked']??false)!==false)return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$expiry,new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$expiry||$d->getTimestamp()<=$now+600)return null;
    return ['mailbox'=>$email,'token'=>$token,'generation'=>$gen];
}
// Independent offline grant review deadline; access tokens are ephemeral in this mode.
function gmailAutoGrant(array $cfg,?int $now=null):?array{
    $p=$cfg['communications']['gmail']??[];$r=$p['renewal']??[];$now=$now??time();
    if(!communicationsEnabled($cfg)||($p['enabled']??false)!==true||($p['revoked']??null)!==false||($r['enabled']??false)!==true||($r['scheduled_capture_enabled']??false)!==true||($r['authorized']??false)!==true)return null;
    $email=$p['mailbox']??null;$gen=$p['generation']??null;$expiry=$r['review_expires_utc']??null;
    if(!is_string($email)||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||!is_string($gen)||!preg_match('/^[a-f0-9]{32}$/D',$gen)||$gen===str_repeat('0',32)||!is_string($expiry))return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$expiry,new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$expiry||$d->getTimestamp()<=$now+600)return null;
    foreach(['client_id','client_secret','refresh_token'] as $k)if(!is_string($r[$k]??null)||!preg_match('/^[A-Za-z0-9._~+\/=-]{8,4096}$/D',$r[$k])||str_starts_with($r[$k],'REPLACE_'))return null;
    return ['mailbox'=>$email,'generation'=>$gen,'review_expires_utc'=>$expiry,'client_id'=>$r['client_id'],'client_secret'=>$r['client_secret'],'refresh_token'=>$r['refresh_token']];
}
function gmailCounts(array $r):array{
    if(($r['id']??null)!=='INBOX'||($r['type']??null)!=='system'||!is_int($r['messagesTotal']??null)||!is_int($r['messagesUnread']??null)||$r['messagesTotal']<0||$r['messagesTotal']>10000000||$r['messagesUnread']<0||$r['messagesUnread']>$r['messagesTotal'])throw new RuntimeException('Invalid label counts');
    return ['inbox'=>$r['messagesTotal'],'unread'=>$r['messagesUnread']];
}
function gmailCapture(PDO $db,array $cfg,array $counts,?DateTimeImmutable $now=null,bool $automatic=false):void{
    if(!$db->inTransaction())throw new LogicException('Transaction required');creativeActive($db);
    $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));$auto=($cfg['communications']['gmail']['renewal']['scheduled_capture_enabled']??false)===true;
    if($auto!==$automatic)throw new RuntimeException('Capture mode changed');
    $p=$automatic?gmailAutoGrant($cfg,$now->getTimestamp()):gmailConfig($cfg,$now->getTimestamp());
    if(!$p)throw new RuntimeException('Disabled');
    $r=gmailCounts(['id'=>'INBOX','type'=>'system','messagesTotal'=>$counts['inbox']??null,'messagesUnread'=>$counts['unread']??null]);
    $db->prepare('INSERT INTO growth_gmail_snapshot(id,generation,inbox_count,unread_count,captured_at) VALUES(1,?,?,?,?) ON DUPLICATE KEY UPDATE generation=VALUES(generation),inbox_count=VALUES(inbox_count),unread_count=VALUES(unread_count),captured_at=VALUES(captured_at)')->execute([$p['generation'],$r['inbox'],$r['unread'],$now->format('Y-m-d H:i:s')]);
    growthAudit($db,null,null,'gmail_counts_capture','private_cli');
}
function gmailSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $now=$now??new DateTimeImmutable('now');$p=($cfg['communications']['gmail']['renewal']['scheduled_capture_enabled']??false)===true?gmailAutoGrant($cfg,$now->getTimestamp()):gmailConfig($cfg,$now->getTimestamp());
    if(!$p)return 'Gmail snapshot disabled or credentials unverified/expired.';
    $r=$db->query('SELECT generation,inbox_count,unread_count,captured_at FROM growth_gmail_snapshot WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if(!$r||$r['generation']!==$p['generation'])return 'Gmail snapshot: no current evidence.';
    $ts=strtotime($r['captured_at'].' UTC');if($ts===false||$ts>$now->getTimestamp()||$now->getTimestamp()-$ts>86400)return 'Gmail snapshot: stale (refresh privately).';
    return 'Gmail INBOX snapshot: '.$r['inbox_count'].' messages, '.$r['unread_count'].' unread; '.$r['captured_at'].' UTC. Unread is not unanswered; not daily arrivals.';
}
