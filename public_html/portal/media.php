<?php
declare(strict_types=1);
require __DIR__.'/../api/operations.php';
require __DIR__.'/../api/media-storage-core.php';
opsHeaders();
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET'||!opsSsl())throw new RuntimeException('Unavailable');
    $cfg=opsConfig();if(($cfg['portal']['enabled']??false)!==true||!storageEnabled($cfg))throw new RuntimeException('Unavailable');
    opsSession();if(!storageOwner($cfg,$_SESSION,time()))throw new RuntimeException('Unavailable');
    $_SESSION['last_seen']=time();session_write_close();
    $db=opsDb($cfg);
    if(isset($_GET['asset'])){
        if(count($_GET)!==1||!is_string($_GET['asset']))throw new RuntimeException('Unavailable');
        $name=$_GET['asset'];$bytes=storagePreview($db,$cfg,$name);
        $type=match(substr($name,65)){'png'=>'image/png','jpg'=>'image/jpeg','mp4'=>'video/mp4'};
        header('Content-Type: '.$type);header('Content-Length: '.strlen($bytes));header('Content-Disposition: inline; filename="'.$name.'"');
        echo $bytes;exit;
    }
    if(count($_GET)!==0)throw new RuntimeException('Unavailable');
    $data=storageInventory($db,miRoot($cfg),200);
}catch(Throwable $e){http_response_code(404);exit('Unavailable');}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Private media inventory</title></head><body>
<h1>Private media inventory</h1><p><a href="/portal/">Back to owner dashboard</a></p><p>Bounded inventory, not total storage. Only referenced assets can be previewed. No publishing or deletion from this page.</p>
<p>Skipped unsafe or unrecognized entries: <?=opsH((string)$data['skipped'])?>. <?= $data['truncated']?'Result truncated. Use the private CLI inventory.':'' ?></p>
<table><thead><tr><th>Asset</th><th>Bytes</th><th>Modified UTC</th><th>References</th><th>Retention</th></tr></thead><tbody>
<?php foreach($data['items'] as $r): ?><tr><td><?php if($r['references']>0): ?><a href="?asset=<?=opsH($r['name'])?>"><?=opsH($r['name'])?></a><?php else: ?><?=opsH($r['name'])?><?php endif; ?></td><td><?=opsH((string)$r['size'])?></td><td><?=opsH($r['modified_utc'])?></td><td><?=opsH((string)$r['references'])?></td><td><?= $r['candidate']?'Quarantine candidate':($r['references']?'Protected':'Retain') ?></td></tr><?php endforeach; ?>
</tbody></table></body></html>
