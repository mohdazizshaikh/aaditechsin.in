<?php
declare(strict_types=1);
require_once __DIR__.'/reconciliation-lib.php';
function releaseBlobHash(string $raw):string{return sha1('blob '.strlen($raw)."\0".$raw);}
function releaseSourceIntegrity(string $root,array $contract):void{
    $expected=$contract['source_git_blob_sha1']??null;
    if(!is_array($expected))throw new RuntimeException('Missing file inventory');
    $paths=array_map(static fn($name)=>'secure-setup/migrations/'.$name,$contract['migrations']);$paths[]='secure-setup/schema.sql';$paths[]='tests/fixtures/schema-v96.sql';
    sort($paths);$actual=array_keys($expected);sort($actual);
    if($actual!==$paths)throw new RuntimeException('File inventory mismatch');
    foreach($expected as $path=>$hash){
        if(!preg_match('#^(?:secure-setup/migrations/[0-9a-z.-]+\.sql|secure-setup/schema\.sql|tests/fixtures/schema-v96\.sql)$#D',$path)||!is_string($hash)||!preg_match('/^[a-f0-9]{40}$/D',$hash)||is_link($root.'/'.$path)||!is_file($root.'/'.$path))throw new RuntimeException('File inventory invalid');
        $raw=file_get_contents($root.'/'.$path);if($raw===false||!hash_equals($hash,releaseBlobHash($raw)))throw new RuntimeException('Source file drift');
    }
}
function releaseWorkerState(PDO $db):array{
    $queries=[
        'smtp_sending'=>"SELECT COUNT(*) FROM notification_outbox WHERE status='sending'",
        'discovery_running'=>"SELECT COUNT(*) FROM growth_source_runs WHERE status='running'",
        'creative_leased'=>"SELECT COUNT(*) FROM growth_creative_leases WHERE status='leased'",
        'owner_report_sending'=>"SELECT COUNT(*) FROM growth_report_runs WHERE status='sending'",
        'single_media_bearer'=>"SELECT COUNT(*) FROM growth_meta_images WHERE token_hash IS NOT NULL AND expires_at>UTC_TIMESTAMP()",
        'carousel_bearer'=>"SELECT COUNT(*) FROM growth_carousel_items WHERE token_hash IS NOT NULL AND expires_at>UTC_TIMESTAMP()"
    ];
    $out=[];foreach($queries as $key=>$sql)$out[$key]=(int)$db->query($sql)->fetchColumn();
    $out['publication_inflight']=0;$out['publication_processing']=0;$out['publication_unknown']=0;
    foreach(recLedgers() as $table){
        foreach($db->query("SELECT status,COUNT(*) AS n FROM $table GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(in_array($r['status'],['sending','uploading','fetching','assembling','publishing'],true))$out['publication_inflight']+=(int)$r['n'];
            elseif(in_array($r['status'],['processing','parent_ready'],true))$out['publication_processing']+=(int)$r['n'];
            elseif($r['status']==='unknown')$out['publication_unknown']+=(int)$r['n'];
        }
    }
    $out['publication_unknown']=recUnknownCounts($db)['unreviewed'];
    return $out;
}
function releaseWorkerClear(array $state):bool{if(!$state)return false;foreach($state as $n)if(!is_int($n)||$n!==0)return false;return true;}
