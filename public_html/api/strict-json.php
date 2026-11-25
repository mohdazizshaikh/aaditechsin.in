<?php
declare(strict_types=1);
// Native validation of syntax, UTF-8 and depth precedes duplicate checking.
function strictJsonWhitespace(string $raw,int &$i):void{
    $n=strlen($raw);while($i<$n&&str_contains(" \t\r\n",$raw[$i]))++$i;
}
function strictJsonString(string $raw,int &$i):string{
    $start=$i++;$n=strlen($raw);
    while($i<$n){
        $c=$raw[$i++];
        if($c==='"')return substr($raw,$start,$i-$start);
        if($c==="\\")++$i;
    }
    throw new InvalidArgumentException('JSON scan failed');
}
function strictJsonWalk(string $raw,int &$i):void{
    strictJsonWhitespace($raw,$i);$c=$raw[$i]??'';
    if($c==='{'){
        ++$i;strictJsonWhitespace($raw,$i);$keys=[];
        if(($raw[$i]??'')==='}') {++$i;return;}
        while(true){
            if(($raw[$i]??'')!=='"')throw new InvalidArgumentException('JSON scan failed');
            $key='k:'.json_decode(strictJsonString($raw,$i),true,2,JSON_THROW_ON_ERROR);
            if(isset($keys[$key]))throw new InvalidArgumentException('Duplicate JSON member');
            $keys[$key]=true;strictJsonWhitespace($raw,$i);
            if(($raw[$i++]??'')!==':')throw new InvalidArgumentException('JSON scan failed');
            strictJsonWalk($raw,$i);strictJsonWhitespace($raw,$i);
            $end=$raw[$i++]??'';
            if($end==='}')return;
            if($end!==',')throw new InvalidArgumentException('JSON scan failed');
            strictJsonWhitespace($raw,$i);
        }
    }
    if($c==='['){
        ++$i;strictJsonWhitespace($raw,$i);
        if(($raw[$i]??'')===']'){++$i;return;}
        while(true){
            strictJsonWalk($raw,$i);strictJsonWhitespace($raw,$i);
            $end=$raw[$i++]??'';
            if($end===']')return;
            if($end!==',')throw new InvalidArgumentException('JSON scan failed');
        }
    }
    if($c==='"'){strictJsonString($raw,$i);return;}
    $start=$i;$n=strlen($raw);
    while($i<$n&&!str_contains(" \t\r\n,]}",$raw[$i]))++$i;
    if($i===$start)throw new InvalidArgumentException('JSON scan failed');
}
function strictJsonDecode(string $raw,bool $assoc,int $depth):mixed{
    if(strlen($raw)>1048576||$depth<2||$depth>32)throw new InvalidArgumentException('JSON bounds');
    $value=json_decode($raw,$assoc,$depth,JSON_THROW_ON_ERROR);
    $i=0;strictJsonWalk($raw,$i);strictJsonWhitespace($raw,$i);
    if($i!==strlen($raw))throw new InvalidArgumentException('JSON scan failed');
    return $value;
}
