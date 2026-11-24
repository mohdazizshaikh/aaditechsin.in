<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    if(count($argv)!==2||!in_array($argv[1],['--source-only','--check-db'],true))throw new RuntimeException('Explicit mode required');
    $contract=json_decode((string)file_get_contents(__DIR__.'/release-contract.json'),true,32,JSON_THROW_ON_ERROR);
    if(($contract['version']??null)!==1||!is_array($contract['tables']??null)||!is_array($contract['migrations']??null))throw new RuntimeException('Contract invalid');
    $root=dirname(__DIR__,2);
    if($argv[1]==='--source-only'){
        $dir=$root.'/secure-setup/migrations';
        $actual=array_map('basename',glob($dir.'/*.sql')?:[]);$expected=$contract['migrations'];sort($actual);sort($expected);
        if($actual!==$expected||count($expected)!==count(array_unique($expected)))throw new RuntimeException('Migration inventory mismatch');
        echo 'PASS: '.count($expected)." ordered migration files accounted for.\n";
        echo 'SOURCE ONLY: no database or hosting acceptance.'."\n";exit;
    }
    require $root.'/public_html/api/operations.php';
    $cfg=opsConfig();$db=opsDb($cfg);
    $q=$db->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
    $actual=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$actual[$r['TABLE_NAME']][$r['COLUMN_NAME']]=true;
    $missing=0;
    foreach($contract['tables'] as $table=>$columns){
        if(!preg_match('/^[a-z0-9_]+$/D',$table)||!is_array($columns))throw new RuntimeException('Contract invalid');
        if(!isset($actual[$table])){echo 'BLOCKED: missing table '.$table."\n";++$missing;continue;}
        foreach($columns as $column){if(!preg_match('/^[a-z0-9_]+$/D',$column))throw new RuntimeException('Contract invalid');if(!isset($actual[$table][$column])){echo 'BLOCKED: missing column '.$table.'.'.$column."\n";++$missing;}}
    }
    if($missing)exit(1);
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($pause===false||(int)$pause!==1){echo "BLOCKED: growth is not paused; stop workers and pause before release rehearsal.\n";exit(1);}
    echo 'PASS: required columns present across '.count($contract['tables'])." tables; growth paused.\n";
    echo "READ ONLY: no migration execution, type/index/FK validation, data backup/restore, scheduler or provider acceptance asserted.\n";
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: release contract, database configuration or schema check unavailable. No credentials or raw exception printed.\n");exit(1);}
