"""Offline CogVideoX candidate studio. Review only; never imports or publishes."""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shutil
import stat
import subprocess
import sys
import tempfile

spec=importlib.util.spec_from_file_location("local_media",Path(__file__).with_name("local-media.py"))
media=importlib.util.module_from_spec(spec);spec.loader.exec_module(media)
MODEL_LIMIT=64*1024**3
MODEL_FILES=512
FRAMES=49
SOURCE_SIZE=(720,480)
COMPONENTS={
    "scheduler":("diffusers",{"CogVideoXDDIMScheduler","CogVideoXDPMScheduler"}),
    "transformer":("diffusers",{"CogVideoXTransformer3DModel"}),
    "vae":("diffusers",{"AutoencoderKLCogVideoX"}),
    "text_encoder":("transformers",{"T5EncoderModel"}),
    "tokenizer":("transformers",{"T5Tokenizer","T5TokenizerFast"}),
}
def canonical(value):
    return json.dumps(value,sort_keys=True,separators=(",",":"),ensure_ascii=False,allow_nan=False).encode("utf-8")
def digest(value):
    return hashlib.sha256(canonical(value)).hexdigest()
def exact_hash(value):
    return isinstance(value,str) and re.fullmatch(r"[a-f0-9]{64}",value) is not None
def identity(s):
    return (s.st_dev,s.st_ino,s.st_mode,s.st_nlink,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
def stable_hash(path,limit):
    s=path.lstat()
    if not stat.S_ISREG(s.st_mode) or s.st_nlink!=1 or not 0<s.st_size<=limit or s.st_mode&0o022:
        raise ValueError("Unsafe input file")
    fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
    with os.fdopen(fd,"rb") as f:
        if identity(os.fstat(f.fileno()))!=identity(s):raise ValueError("Changed input")
        h=hashlib.sha256();n=0
        for b in iter(lambda:f.read(1024*1024),b""):
            n+=len(b)
            if n>s.st_size:raise ValueError("Changed input")
            h.update(b)
        if n!=s.st_size or identity(os.fstat(f.fileno()))!=identity(s) or identity(path.lstat())!=identity(s):
            raise ValueError("Changed input")
    return h.hexdigest(),s.st_size
def model_index(doc):
    if not isinstance(doc,dict) or doc.get("_class_name")!="CogVideoXPipeline" or set(doc)-set(COMPONENTS)-{"_class_name","_diffusers_version","_name_or_path"}:
        raise ValueError("Unsupported pipeline")
    for name,(library,classes) in COMPONENTS.items():
        v=doc.get(name)
        if not isinstance(v,list) or len(v)!=2 or v[0]!=library or not isinstance(v[1],str) or v[1] not in classes:
            raise ValueError("Unsupported component")
def model_inventory(path):
    path=Path(path).expanduser().absolute()
    if path.resolve(strict=True)!=path or not path.is_dir() or path==media.ROOT or media.ROOT in path.parents or path in media.ROOT.parents or "public_html" in path.parts:
        raise ValueError("Canonical private model directory required")
    rows=[];total=0;seen=0
    for directory,dirs,names in os.walk(path,followlinks=False,onerror=lambda e: (_ for _ in ()).throw(e)):
        for name in dirs+names:
            seen+=1
            if seen>MODEL_FILES*2:raise ValueError("Model inventory cap")
            p=Path(directory)/name
            if p.is_symlink() or not re.fullmatch(r"[A-Za-z0-9_.-]+",name) or name.startswith("."):
                raise ValueError("Unsafe model path")
            if name in dirs:
                if p.stat().st_mode&0o022:raise ValueError("Writable model directory")
                continue
            if p.suffix.lower() not in {".json",".safetensors",".model",".txt",".md",".vocab",".merges",".tiktoken"}:
                raise ValueError("Unsupported model file")
            h,size=stable_hash(p,MODEL_LIMIT)
            total+=size
            if total>MODEL_LIMIT or len(rows)>=MODEL_FILES:raise ValueError("Model size cap")
            rows.append([p.relative_to(path).as_posix(),size,h])
    if path.stat().st_mode&0o022:raise ValueError("Writable model root")
    model_index(media.read_json(path/"model_index.json",65536))
    if not any(r[0].startswith("transformer/") and r[0].endswith(".safetensors") for r in rows):
        raise ValueError("Local safetensors required")
    return digest(sorted(rows))
def font_digest(font):
    p=Path(font).expanduser().absolute()
    if p.resolve(strict=True)!=p or p.suffix.lower() not in {".ttf",".otf"}:raise ValueError("Trusted font required")
    return stable_hash(p,16*1024**2)[0]
def plan(doc,model_sha,font_sha,seed):
    media.batch_plan(doc)
    if not exact_hash(model_sha) or not exact_hash(font_sha) or type(seed) is not int or not 0<=seed<=2**31-13:
        raise ValueError("Invalid reviewed model, font or seed")
    return {"jobs":len(doc["jobs"]),"model_sha256":model_sha,"font_sha256":font_sha,"seed":seed,
            "plan_sha256":digest({"scope":"native-video-candidate-v1","jobs":doc["jobs"],"model":model_sha,"font":font_sha,"seed":seed})}
def generate(job,model,seed):
    # Install reviewed local libraries separately. Offline flags are not a network sandbox.
    os.environ.update({"HF_HUB_OFFLINE":"1","TRANSFORMERS_OFFLINE":"1","HF_DATASETS_OFFLINE":"1","HF_HUB_DISABLE_TELEMETRY":"1"})
    import torch
    from diffusers import CogVideoXPipeline
    if not torch.cuda.is_available():raise ValueError("CUDA runtime required; no still-image fallback")
    pipe=CogVideoXPipeline.from_pretrained(str(model),local_files_only=True,use_safetensors=True,torch_dtype=torch.float16)
    pipe.enable_model_cpu_offload()
    if hasattr(pipe.vae,"enable_slicing"):pipe.vae.enable_slicing()
    if hasattr(pipe.vae,"enable_tiling"):pipe.vae.enable_tiling()
    with torch.inference_mode():
        result=pipe(prompt="Fictional professional technology service illustration, no text, logos, identifiable people or real customer project. "+job["video_brief"],
                    negative_prompt="text, watermark, logo, identifiable people, personal data",width=720,height=480,num_frames=FRAMES,
                    num_inference_steps=30,num_videos_per_prompt=1,generator=torch.Generator(device="cpu").manual_seed(seed),output_type="pil")
    if not isinstance(result.frames,list) or len(result.frames)!=1:raise ValueError("Unexpected model output")
    return result.frames[0]
def validate_frames(frames):
    from PIL import Image
    if not isinstance(frames,list) or len(frames)!=FRAMES or any(not isinstance(f,Image.Image) or f.size!=SOURCE_SIZE or f.mode!="RGB" for f in frames):
        raise ValueError("Unexpected frame profile")
    if len({hashlib.sha256(f.tobytes()).digest() for f in frames})<2:raise ValueError("Static candidate refused")
    return frames
def probe(video,dimensions):
    if not 0<video.stat().st_size<=20*1024**2:raise ValueError("Candidate size")
    r=json.loads(subprocess.check_output(["ffprobe","-v","error","-show_entries","stream=codec_type,codec_name,width,height,pix_fmt:format=duration,format_name","-of","json",str(video)],timeout=30))
    streams=r["streams"]
    if len(streams)!=1 or streams[0].get("codec_type")!="video" or streams[0].get("codec_name")!="h264" or streams[0].get("pix_fmt")!="yuv420p" or (streams[0].get("width"),streams[0].get("height"))!=tuple(dimensions) or "mp4" not in r["format"]["format_name"].split(",") or not 3.9<=float(r["format"]["duration"])<=4.1:
        raise ValueError("Candidate profile")
    raw=subprocess.check_output(["ffmpeg","-nostdin","-v","error","-xerror","-i",str(video),"-map","0:v:0","-f","framemd5","-"],timeout=90)
    hashes=[line.rsplit(b",",1)[-1].strip() for line in raw.splitlines() if line and not line.startswith(b"#")]
    if len(hashes)!=96 or len(set(hashes))<2:raise ValueError("Decoded candidate motion/profile")
def render_one(job,out,font,frames,model_sha,seed):
    frames=validate_frames(frames)
    out.mkdir(mode=0o700)
    with tempfile.TemporaryDirectory(dir=out,prefix="frames-") as tmp:
        temp=Path(tmp)
        # Select 32 distinct time positions across the generated clip, then encode 4 s at 24 fps.
        for i in range(32):
            media.create_image(job,temp/f"{i:03d}.png",font,frames[round(i*(FRAMES-1)/31)])
        shutil.copyfile(temp/"000.png",out/"poster.png")
        subprocess.run(["ffmpeg","-nostdin","-v","error","-n","-framerate","8","-i",str(temp/"%03d.png"),"-t","4","-vf","fps=24","-c:v","libx264","-preset","ultrafast","-pix_fmt","yuv420p","-movflags","+faststart","-an",str(out/"candidate.mp4")],check=True,timeout=180,capture_output=True)
    probe(out/"candidate.mp4",job["dimensions"])
    result={"version":1,"scope":"native-video-review-only","task_id":job["task_id"],"revision":job["revision"],"channel":job["channel"],"dimensions":job["dimensions"],"model_sha256":model_sha,"job_sha256":digest(job),"seed":seed,"poster_sha256":media.sha(out/"poster.png"),"candidate_sha256":media.sha(out/"candidate.mp4")}
    (out/"review.json").write_bytes(canonical(result)+b"\n")
    for name in ("poster.png","candidate.mp4","review.json"):(out/name).chmod(0o600)
    return result
def render_batch(doc,expected,model,font,seed,output,infer=generate):
    model=Path(model).expanduser().absolute();font=Path(font).expanduser().absolute()
    model_sha=model_inventory(model);font_sha=font_digest(font)
    reviewed=plan(doc,model_sha,font_sha,seed)
    if not exact_hash(expected) or expected!=reviewed["plan_sha256"]:raise ValueError("Reviewed plan changed")
    root=media.private_root(Path(output))
    if root==model or root in model.parents or model in root.parents:raise ValueError("Separate output and model required")
    stage=Path(tempfile.mkdtemp(dir=root,prefix="native-review-"))
    try:
        results=[]
        for i,job in enumerate(doc["jobs"]):
            out=stage/f"task-{job['task_id']}-r{job['revision']}"
            review=render_one(job,out,font,infer(job,model,seed+i),model_sha,seed+i)
            results.append({"directory":out.name,"review_sha256":media.sha(out/"review.json")})
        if model_inventory(model)!=model_sha or font_digest(font)!=font_sha:raise ValueError("Reviewed model or font changed")
        index={"version":1,"scope":"native-video-review-only","plan_sha256":expected,"candidates":results}
        (stage/"review-index.json").write_bytes(canonical(index)+b"\n");(stage/"review-index.json").chmod(0o600)
        return stage
    except BaseException:
        shutil.rmtree(stage)
        raise
def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("mode",choices=["plan","render"]);p.add_argument("--jobs",type=Path,required=True);p.add_argument("--model",type=Path,required=True);p.add_argument("--font",type=Path,required=True);p.add_argument("--seed",type=int,required=True);p.add_argument("--output",type=Path);p.add_argument("--plan-sha256")
    a=p.parse_args()
    try:
        doc=media.read_json(a.jobs,65536)
        if a.mode=="plan":
            if a.output is not None or a.plan_sha256 is not None:raise ValueError("Unexpected argument")
            print(json.dumps(plan(doc,model_inventory(a.model),font_digest(a.font),a.seed)))
        else:
            if a.output is None:raise ValueError("Private output required")
            print(render_batch(doc,a.plan_sha256,a.model,a.font,a.seed,a.output))
            print("Private candidate only; no approved media manifest or publication.")
        return 0
    except Exception:
        print("BLOCKED: reviewed job, local model, font, CUDA or candidate validation.",file=sys.stderr)
        return 1
if __name__=="__main__":sys.exit(main())
