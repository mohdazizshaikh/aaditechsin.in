<?php
declare(strict_types=1);
// Private CLI only. Read at most 20 MiB and hash exact bytes later sent to provider.
function uploadRoot(string $root,string $webroot):string {
    $r=realpath($root);$w=realpath($webroot);
    if($r===false||$w===false||$r===$w||str_starts_with($r,$w.DIRECTORY_SEPARATOR)||str_starts_with($w,$r.DIRECTORY_SEPARATOR)||is_link($root)||!is_dir($r)||(fileperms($r)&0077)!==0)throw new RuntimeException('Require private 0700 sibling storage');
    return $r;
}
function uploadRead(string $path,string $hash,int $expected=0):string {
    if(is_link($path)||!is_file($path))throw new RuntimeException('Not a regular file');
    $f=fopen($path,'rb');if(!$f)throw new RuntimeException('Cannot open');
    try{$bytes=stream_get_contents($f,20*1024*1024+1);}finally{fclose($f);}
    if(!is_string($bytes)||strlen($bytes)<12||strlen($bytes)>20*1024*1024||($expected!==0&&strlen($bytes)!==$expected)||substr($bytes,4,4)!=='ftyp'||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Invalid MP4 or hash mismatch');
    return $bytes;
}
function uploadStore(string $root,string $hash,string $bytes):void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Hash mismatch');
    $path=$root.'/'.$hash.'.mp4';
    if(file_exists($path)||is_link($path)){uploadRead($path,$hash,strlen($bytes));return;}
    $f=fopen($path,'xb');if(!$f)throw new RuntimeException('Cannot create');
    try{if(!chmod($path,0600)||fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Write failed');}
    catch(Throwable $e){fclose($f);unlink($path);throw $e;}
    fclose($f);
}
