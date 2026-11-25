<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/preflight-rules.php';
function pfCheck(bool $ok,string $msg):void{if(!$ok)throw new LogicException($msg);}
function pfSet(array &$cfg,string $path,mixed $v):void{
    $parts=explode('.',$path);$ref=&$cfg;
    foreach($parts as $part)$ref=&$ref[$part];
    $ref=$v;
}
function pfBoolPaths(array $cfg,string $prefix=''):array{
    $out=[];foreach($cfg as $k=>$v){
        $p=$prefix===''?$k:$prefix.'.'.$k;
        if(is_bool($v))$out[]=$p;
        elseif(is_array($v))$out=array_merge($out,pfBoolPaths($v,$p));
    }
    sort($out,SORT_STRING);return $out;
}
$root=dirname(__DIR__);$cfg=require $root.'/secure-setup/aaditechs-config.php.example';
$flags=preflightFlagPaths();
pfCheck($flags===pfBoolPaths($cfg),'All example boolean leaves classified exactly');
$cfg['public_base_url']='https://staging.example.invalid';
pfCheck(preflightBaseline($cfg,$cfg['public_base_url'])===[],'Baseline accepts all-off fixture');
$cases=0;
foreach($flags as $path){
    $x=$cfg;pfSet($x,$path,true);
    pfCheck(preflightFlags($x)[$path]==='ON'&&in_array($path.' must be explicitly OFF',preflightBaseline($x,$cfg['public_base_url']),true),'Enabled flag blocks');++$cases;
    $x=$cfg;pfSet($x,$path,'false');
    pfCheck(preflightFlags($x)[$path]==='INVALID_OR_MISSING'&&preflightBaseline($x,$cfg['public_base_url'])!==[],'Nonboolean blocks');++$cases;
    $x=$cfg;pfSet($x,$path,null);
    pfCheck(preflightFlags($x)[$path]==='INVALID_OR_MISSING','Null blocks');++$cases;
}
foreach(preflightDependencies() as $path=>$deps){
    pfCheck(in_array($path,$flags,true),'Known flag');
    $x=$cfg;pfSet($x,$path,true);
    foreach($deps as $dep)pfCheck(in_array($dep,$flags,true)&&in_array($path.' requires '.$dep,preflightDependencyFailures($x),true),'Missing dependency');
    foreach($deps as $dep)pfSet($x,$dep,true);
    foreach($deps as $dep)pfCheck(!in_array($path.' requires '.$dep,preflightDependencyFailures($x),true),'Enabled dependency');
}
foreach(['https://aaditechs.in','https://www.aaditechs.in','https://aaditechsin.in','https://www.aaditechsin.in','http://staging.example.invalid','https://127.0.0.1','https://staging.example.invalid/','https://user@staging.example.invalid','https://staging..invalid'] as $origin){
    $x=$cfg;$x['public_base_url']=$origin;pfCheck(preflightBaseline($x,$origin)!==[],'Unsafe origin');
}
$tables=preflightContract($root.'/secure-setup/maintenance/release-contract.json');$actual=[];
foreach($tables as $table=>$columns)$actual[$table]=array_fill_keys($columns,true);
pfCheck(preflightSchemaFailures($tables,$actual)===[],'Exact inventory present');
foreach($tables as $table=>$cols){
    $x=$actual;unset($x[$table]);pfCheck(preflightSchemaFailures($tables,$x)===[$table],'Missing table');++$cases;
    $x=$actual;unset($x[$table][$cols[0]]);pfCheck(preflightSchemaFailures($tables,$x)===[$table.'.'.$cols[0]],'Missing column');++$cases;
}
echo 'Preflight: '.count($flags)." explicit flags; $cases flag/schema matrix cases PASS\n";

$tmp=sys_get_temp_dir().'/aaditechs-preflight-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
mkdir($tmp.'/public_html',0700);mkdir($tmp.'/secure',0700);
$p=$tmp.'/secure/aaditechs-config.php';file_put_contents($p,'<?php return [];');chmod($p,0600);
try{
    pfCheck(preflightPrivateConfig($tmp.'/public_html',$tmp.'/secure',$p),'Private file accepted');
    chmod($p,0644);clearstatcache();pfCheck(!preflightPrivateConfig($tmp.'/public_html',$tmp.'/secure',$p),'Public permission rejected');chmod($p,0600);
    $alias=$tmp.'/secure/alias';symlink($p,$alias);pfCheck(!preflightPrivateConfig($tmp.'/public_html',$tmp.'/secure',$alias),'Symlink rejected');unlink($alias);
    link($p,$alias);clearstatcache();pfCheck(!preflightPrivateConfig($tmp.'/public_html',$tmp.'/secure',$p),'Hardlink rejected');unlink($alias);
    chmod($tmp.'/secure',0755);clearstatcache();pfCheck(!preflightPrivateConfig($tmp.'/public_html',$tmp.'/secure',$p),'Public directory rejected');chmod($tmp.'/secure',0700);
}finally{unlink($p);rmdir($tmp.'/secure');rmdir($tmp.'/public_html');rmdir($tmp);}
echo "Private preflight config path and permissions: PASS\n";
