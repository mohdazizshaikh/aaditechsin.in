<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
require_once __DIR__.'/discovery-feed-lib.php';
require_once dirname(__DIR__,2).'/public_html/api/creative-core.php';
function discoveryBatchSource(array $cfg,string $id):array{
    if(!discoveryEnabled($cfg)||($cfg['discovery']['local_batch_enabled']??false)!==true)throw new RuntimeException('Disabled');
    $source=$cfg['discovery']['feeds'][$id]??null;if(!is_array($source))throw new RuntimeException('Unknown authorized source');
    return feedDefinition($id,$source);
}
function discoveryBatch(PDO $db,array $cfg,string $id,string $raw):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $src=discoveryBatchSource($cfg,$id);creativeActive($db);
    if(strlen($raw)>262144)throw new InvalidArgumentException('Batch size');
    $doc=strictJsonDecode($raw,true,32);
    if(!is_array($doc)||count($doc)!==2||!array_key_exists('version',$doc)||!array_key_exists('items',$doc))throw new InvalidArgumentException('Exact feed contract');
    $parsed=feedItems($raw,$src);
    if($parsed['skipped']!==0||!$parsed['records'])throw new InvalidArgumentException('Every record must pass source and service validation');
    $db->exec('SAVEPOINT discovery_batch');
    try{
    $new=0;$duplicates=0;
    foreach($parsed['records'] as $item){$r=discoveryImport($db,$item);if($r['status']==='duplicate')++$duplicates;else ++$new;}
    $db->prepare("INSERT INTO growth_source_runs(source_id,status,imported_count,duplicate_count,skipped_count,finished_at) VALUES(?,'completed',?,?,0,UTC_TIMESTAMP())")->execute([$id,$new,$duplicates]);
    $db->exec('RELEASE SAVEPOINT discovery_batch');
    return ['imported'=>$new,'duplicates'=>$duplicates];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT discovery_batch');$db->exec('RELEASE SAVEPOINT discovery_batch');throw $e;}
}
