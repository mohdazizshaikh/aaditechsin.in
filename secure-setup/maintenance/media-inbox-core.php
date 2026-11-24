<?php
declare(strict_types=1);
require_once __DIR__.'/upload-assets.php';
function inboxImport(string $inbox,string $root,array $row):void{
    if($inbox===$root||str_starts_with($inbox,$root.'/')||str_starts_with($root,$inbox.'/'))throw new RuntimeException('Separate inbox required');
    foreach(['image_hash','video_hash'] as $k)if(!is_string($row[$k]??null)||!preg_match('/^[a-f0-9]{64}$/D',$row[$k]))throw new RuntimeException('Invalid manifest');
    $png=uploadPhotoRead($inbox.'/'.$row['image_hash'].'.png',$row['image_hash'],(int)$row['width'],(int)$row['height']);
    $mp4=uploadRead($inbox.'/'.$row['video_hash'].'.mp4',$row['video_hash']);
    uploadPhotoStore($root,$row['image_hash'],$png,(int)$row['width'],(int)$row['height']);uploadStore($root,$row['video_hash'],$mp4);
}
