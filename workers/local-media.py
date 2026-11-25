"""Private local creative studio. No social API, no remote model downloads."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import sys
import tempfile
import stat
import importlib.util

spec=importlib.util.spec_from_file_location('local_creative',Path(__file__).with_name('local-creative.py'))
local=importlib.util.module_from_spec(spec);spec.loader.exec_module(local)

ROOT=Path(__file__).resolve().parents[1]
PROFILES={"instagram":(1080,1920),"youtube":(1080,1920),"threads":(1080,1080),"telegram":(1080,1080),**dict.fromkeys(("facebook","linkedin","google-business","x","blog"),(1200,628))}
def sha(path):
    with path.open("rb") as f:
        return hashlib.file_digest(f,"sha256").hexdigest()
def read_json(path,limit=16384):
    with path.open("rb") as f:
        raw=f.read(limit+1)
    if len(raw)>limit:
        raise ValueError("Oversized input")
    return local.strict_json(raw)
def validate(job):
    keys={"version","task_id","revision","channel","service_title","caption","visual_brief","video_brief","dimensions"}
    if not isinstance(job,dict) or set(job)!=keys or type(job["version"]) is not int or job["version"]!=1 or any(type(job[k]) is not int or job[k]<1 for k in ("task_id","revision")):
        raise ValueError("Invalid job")
    if not isinstance(job["channel"],str) or job["channel"] not in PROFILES or not isinstance(job["dimensions"],list) or any(type(n) is not int for n in job["dimensions"]) or job["dimensions"]!=list(PROFILES[job["channel"]]):
        raise ValueError("Profile mismatch")
    for key,limit in (("service_title",160),("caption",900),("visual_brief",400),("video_brief",400)):
        s=job[key]
        if not isinstance(s,str) or not s.strip() or len(s.encode())>limit or re.search(r"[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]",s):
            raise ValueError("Invalid text")
    titles={s["title"] for s in read_json(ROOT/"public_html/assets/service-registry.json",65536)["services"]}
    if job["service_title"] not in titles:
        raise ValueError("Unknown service title")
    if local.CLAIMS.search(" ".join(job[k] for k in ("caption","visual_brief","video_brief"))):
        raise ValueError("Unsupported claim or contact detail")
    if len(job["caption"].encode())>{"x":280,"threads":500}.get(job["channel"],900):
        raise ValueError("Channel caption exceeds limit")
    return job
def private_root(path):
    original=path.expanduser().absolute()
    path=original.resolve()
    if original!=path:
        raise ValueError("Canonical private path required")
    if path==ROOT or ROOT in path.parents or path in ROOT.parents or "public_html" in path.parts:
        raise ValueError("Choose a private directory outside repository and webroot")
    path.mkdir(mode=0o700,parents=True,exist_ok=True)
    if os.name=="posix" and path.stat().st_mode&0o077:
        raise ValueError("Private directory must have mode 0700")
    return path
def diffusion_image(job,model_path):
    # Trusted locally installed weights only; no remote code, no model fetch.
    os.environ["HF_HUB_OFFLINE"]="1"
    os.environ["TRANSFORMERS_OFFLINE"]="1"
    from diffusers import DiffusionPipeline
    import torch
    if not model_path or not model_path.is_dir():
        raise ValueError("Local model directory required")
    device="cuda" if torch.cuda.is_available() else "cpu"
    pipe=DiffusionPipeline.from_pretrained(str(model_path.resolve()),local_files_only=True,use_safetensors=True,trust_remote_code=False,torch_dtype=torch.float16 if device=="cuda" else torch.float32).to(device)
    result=pipe(prompt="Illustrative professional technology marketing background. No text, logo, customer identity or claimed real project. "+job["visual_brief"],width=768,height=768,num_inference_steps=25)
    if not result.images:
        raise ValueError("No generated image")
    return result.images[0]
def create_image(job,target,font_path,background=None):
    from PIL import Image,ImageDraw,ImageFont,ImageOps
    w,h=PROFILES[job["channel"]]
    if background is None:
        im=Image.new("RGB",(w,h),(12,38,63))
        d=ImageDraw.Draw(im)
        for x in range(0,w,80):
            d.line((x,0,x,h),fill=(21,52,78),width=2)
        for y in range(0,h,80):
            d.line((0,y,w,y),fill=(21,52,78),width=2)
    else:
        im=ImageOps.fit(background.convert("RGB"),(w,h))
        im=Image.blend(im,Image.new("RGB",(w,h),(9,27,44)),0.55)
    d=ImageDraw.Draw(im)
    margin=int(w*0.065)
    logo=Image.open(ROOT/"public_html/assets/aaditechs-original-logo.png").convert("RGBA")
    logo.thumbnail((int(w*.25),int(h*.15)))
    d.rounded_rectangle((margin-12,margin-12,margin+logo.width+12,margin+logo.height+12),radius=12,fill="white")
    im.paste(logo,(margin,margin),logo)
    size=int(w*.052)
    title=job["service_title"]
    # Pixel-based wrapping and bounded font scaling, no AI-generated brand text.
    while size>=18:
        font=ImageFont.truetype(str(font_path),size)
        lines=[]
        for word in title.split():
            if lines and d.textlength(lines[-1]+" "+word,font=font)<=w-2*margin:
                lines[-1]+=" "+word
            else:
                lines.append(word)
        if len(lines)<=3 and all(d.textlength(s,font=font)<=w-2*margin for s in lines):
            break
        size-=2
    else:
        raise ValueError("Title does not fit")
    y=int(h*.52)
    d.rounded_rectangle((margin-12,y-18,w-margin+12,y+len(lines)*int(size*1.3)+20),radius=16,fill=(9,27,44))
    for line in lines:
        d.text((margin,y),line,font=font,fill="white")
        y+=int(size*1.3)
    small=ImageFont.truetype(str(font_path),max(18,int(w*.025)))
    d.text((margin,int(h*.88)),"Aaditechs | Service illustration",font=small,fill="white")
    im.save(target,"PNG")
def create_video(image,target):
    # Four-second silent branded image-based motion slide; not text-to-video.
    exe=shutil.which("ffmpeg")
    if not exe:
        raise ValueError("Install trusted FFmpeg locally")
    subprocess.run([exe,"-nostdin","-hide_banner","-loglevel","error","-n","-loop","1","-framerate","24","-i",str(image),"-t","4","-vf","fade=t=in:st=0:d=0.35,fade=t=out:st=3.65:d=0.35","-c:v","libx264","-preset","ultrafast","-pix_fmt","yuv420p","-movflags","+faststart","-an",str(target)],check=True,timeout=120,capture_output=True)
def render(job,root,font,renderer,model=None,generate=diffusion_image):
    validate(job)
    root=private_root(root)
    if renderer not in ("template","local-diffusion") or not font.is_file():
        raise ValueError("Invalid renderer or trusted font")
    out=Path(tempfile.mkdtemp(prefix=f"task-{job['task_id']}-r{job['revision']}-",dir=root))
    try:
        bg=generate(job,model) if renderer=="local-diffusion" else None
        create_image(job,out/"creative.png",font,bg)
        create_video(out/"creative.png",out/"creative.mp4")
        w,h=PROFILES[job["channel"]]
        manifest={"version":1,"task_id":job["task_id"],"revision":job["revision"],"channel":job["channel"],"renderer":renderer,"width":w,"height":h,"image_sha256":sha(out/"creative.png"),"video_sha256":sha(out/"creative.mp4")}
        (out/"manifest.json").write_text(json.dumps(manifest,sort_keys=True,separators=(",",":")),encoding="utf-8")
        for name in ("creative.png","creative.mp4","manifest.json"):
            (out/name).chmod(0o600)
        return out
    except BaseException:
        shutil.rmtree(out)
        raise
def private_bundle(directory):
    directory=Path(directory).expanduser().absolute()
    if directory.resolve(strict=True)!=directory or not directory.is_dir() or directory==ROOT or ROOT in directory.parents or directory in ROOT.parents or "public_html" in directory.parts:
        raise ValueError("Private canonical bundle required")
    if os.name=="posix" and directory.stat().st_mode&0o077:
        raise ValueError("Private bundle permissions required")
    for name,limit in (("manifest.json",4096),("creative.png",10*1024*1024),("creative.mp4",20*1024*1024)):
        path=directory/name
        info=path.lstat()
        if not stat.S_ISREG(info.st_mode) or info.st_nlink!=1 or not 0<info.st_size<=limit or (os.name=="posix" and info.st_mode&0o077):
            raise ValueError("Private bounded regular bundle files required")
    return directory

def validate_manifest(manifest):
    keys={"version","task_id","revision","channel","renderer","width","height","image_sha256","video_sha256"}
    if not isinstance(manifest,dict) or set(manifest)!=keys or type(manifest["version"]) is not int or manifest["version"]!=1:
        raise ValueError("Invalid manifest")
    if any(type(manifest[k]) is not int or manifest[k]<1 for k in ("task_id","revision","width","height")):
        raise ValueError("Invalid manifest identifiers")
    if not isinstance(manifest["channel"],str) or (manifest["width"],manifest["height"])!=PROFILES.get(manifest["channel"]) or manifest["renderer"] not in ("template","local-diffusion"):
        raise ValueError("Manifest profile or renderer mismatch")
    if any(not isinstance(manifest[k],str) or not re.fullmatch(r"[a-f0-9]{64}",manifest[k]) for k in ("image_sha256","video_sha256")):
        raise ValueError("Invalid manifest hashes")
    return manifest

def verify(directory):
    directory=private_bundle(directory)
    manifest=validate_manifest(read_json(directory/"manifest.json",4096))
    if manifest["image_sha256"]!=sha(directory/"creative.png") or manifest["video_sha256"]!=sha(directory/"creative.mp4"):
        raise ValueError("Asset hash mismatch")
    from PIL import Image
    expected=PROFILES.get(manifest.get("channel"))
    if not expected or (manifest.get("width"),manifest.get("height"))!=expected:
        raise ValueError("Manifest profile mismatch")
    with Image.open(directory/"creative.png") as im:
        if im.format!="PNG" or im.size!=expected:
            raise ValueError("PNG profile mismatch")
        im.verify()
    video=directory/"creative.mp4"
    if video.stat().st_size>20*1024*1024:
        raise ValueError("MP4 too large")
    probe=json.loads(subprocess.check_output(["ffprobe","-v","error","-show_entries","stream=codec_type,codec_name,width,height,pix_fmt:format=duration,format_name","-of","json",str(video)],timeout=30))
    streams=[s for s in probe["streams"] if s.get("codec_type")=="video"]
    if len(probe["streams"])!=1 or len(streams)!=1 or streams[0].get("pix_fmt")!="yuv420p" or "mp4" not in probe["format"]["format_name"].split(",") or streams[0].get("codec_name")!="h264" or (streams[0].get("width"),streams[0].get("height"))!=expected or not 3.5<=float(probe["format"]["duration"])<=4.5:
        raise ValueError("MP4 profile mismatch")
    subprocess.run(["ffmpeg","-nostdin","-v","error","-xerror","-i",str(video),"-map","0:v:0","-f","null","-"],check=True,timeout=60,capture_output=True)
    return sha(directory/"manifest.json")
def batch_plan(doc):
    if not isinstance(doc,dict) or set(doc)!={"version","jobs"} or type(doc["version"]) is not int or doc["version"]!=1 or not isinstance(doc["jobs"],list) or not 1<=len(doc["jobs"])<=12:
        raise ValueError("Batch must have 1–12 exported jobs")
    seen=set()
    for job in doc["jobs"]:
        validate(job)
        key=job["task_id"]
        if key in seen:
            raise ValueError("Only one revision per task in a batch")
        seen.add(key)
    canonical=json.dumps(doc,sort_keys=True,separators=(",",":"),ensure_ascii=False).encode("utf-8")
    return {"jobs":len(doc["jobs"]),"sha256":hashlib.sha256(b"aaditechs-media-batch-v1\x00"+canonical).hexdigest()}

def render_batch(doc,expected,root,font,renderer,model=None,render_one=render,verify_one=verify):
    plan=batch_plan(doc)
    if not isinstance(expected,str) or not re.fullmatch(r"[a-f0-9]{64}",expected) or plan["sha256"]!=expected:
        raise ValueError("Reviewed batch plan changed")
    if renderer not in ("template","local-diffusion") or not font.is_file() or (renderer=="local-diffusion" and (model is None or not model.is_dir())):
        raise ValueError("Trusted renderer, font and model required")
    root=private_root(root)
    stage=Path(tempfile.mkdtemp(prefix="media-batch-",dir=root))
    try:
        results=[]
        for job in doc["jobs"]:
            out=render_one(job,stage,font,renderer,model)
            manifest_hash=verify_one(out)
            results.append({"task_id":job["task_id"],"revision":job["revision"],"directory":out.name,"manifest_sha256":manifest_hash})
        index=stage/"batch-index.json"
        with index.open("x",encoding="utf-8") as f:
            index.chmod(0o600)
            json.dump({"version":1,"plan_sha256":expected,"renderer":renderer,"bundles":results},f,sort_keys=True,separators=(",",":"))
        return stage
    except BaseException:
        shutil.rmtree(stage)
        raise

def main():
    p=argparse.ArgumentParser(description=__doc__)
    sub=p.add_subparsers(dest="mode",required=True)
    r=sub.add_parser("render");r.add_argument("--job",type=Path,required=True);r.add_argument("--output",type=Path,required=True);r.add_argument("--font",type=Path,required=True);r.add_argument("--renderer",choices=["template","local-diffusion"],required=True);r.add_argument("--model",type=Path)
    v=sub.add_parser("verify");v.add_argument("--directory",type=Path,required=True)
    p1=sub.add_parser("batch-plan");p1.add_argument("--jobs",type=Path,required=True)
    p2=sub.add_parser("batch-render");p2.add_argument("--jobs",type=Path,required=True);p2.add_argument("--plan-sha256",required=True);p2.add_argument("--output",type=Path,required=True);p2.add_argument("--font",type=Path,required=True);p2.add_argument("--renderer",choices=["template","local-diffusion"],required=True);p2.add_argument("--model",type=Path)
    a=p.parse_args()
    try:
        if a.mode=="render":
            out=render(read_json(a.job),a.output,a.font,a.renderer,a.model)
            print(out)
            print("Manifest SHA256:",verify(out))
        elif a.mode=="verify":
            print("Manifest SHA256:",verify(a.directory))
        elif a.mode=="batch-plan":
            print(json.dumps(batch_plan(read_json(a.jobs,65536))))
        else:
            out=render_batch(read_json(a.jobs,65536),a.plan_sha256,a.output,a.font,a.renderer,a.model)
            print(out)
            print("Local verified bundles only; individual owner review required.")
        return 0
    except Exception:
        print("BLOCKED: invalid job, private storage, local model, renderer or asset verification.",file=sys.stderr)
        return 1
if __name__=="__main__":
    sys.exit(main())
