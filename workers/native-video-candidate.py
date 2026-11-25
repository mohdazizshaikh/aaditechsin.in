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
def private_candidate_dir(path):
    p=Path(path).expanduser().absolute()
    if p.resolve(strict=True)!=p or not p.is_dir() or p.stat().st_mode&0o077 or p==media.ROOT or media.ROOT in p.parents or p in media.ROOT.parents or "public_html" in p.parts:
        raise ValueError("Canonical private candidate directory required")
    return p
def private_asset(path,limit):
    if path.resolve(strict=True)!=path or path.lstat().st_mode&0o077:
        raise ValueError("Private candidate file required")
    return stable_hash(path,limit)[0]
def private_json(path,limit=65536):
    h=private_asset(path,limit)
    with path.open("rb") as f:raw=f.read(limit+1)
    if len(raw)>limit or hashlib.sha256(raw).hexdigest()!=h:
        raise ValueError("Candidate changed")
    v=media.local.strict_json(raw)
    if canonical(v)+b"\n"!=raw:raise ValueError("Canonical candidate required")
    return v,h
def verify_batch(doc,model_sha,font_sha,seed,expected,directory,index_digest,probe_video=probe):
    reviewed=plan(doc,model_sha,font_sha,seed)
    if expected!=reviewed["plan_sha256"] or not exact_hash(index_digest):
        raise ValueError("Reviewed plan or index binding")
    root=private_candidate_dir(directory)
    index,actual_digest=private_json(root/"review-index.json")
    if actual_digest!=index_digest or not isinstance(index,dict) or set(index)!={"version","scope","plan_sha256","candidates"} or type(index["version"]) is not int or index["version"]!=1 or index["scope"]!="native-video-review-only" or index["plan_sha256"]!=expected:
        raise ValueError("Candidate index mismatch")
    rows=index["candidates"]
    if not isinstance(rows,list) or len(rows)!=len(doc["jobs"]):raise ValueError("Candidate inventory")
    expected_names={"review-index.json"}
    results=[]
    from PIL import Image
    for i,(job,row) in enumerate(zip(doc["jobs"],rows)):
        name=f"task-{job['task_id']}-r{job['revision']}"
        if not isinstance(row,dict) or set(row)!={"directory","review_sha256"} or row["directory"]!=name or not exact_hash(row["review_sha256"]):
            raise ValueError("Candidate directory binding")
        expected_names.add(name)
        bundle=private_candidate_dir(root/name)
        if {x.name for x in bundle.iterdir()}!={"poster.png","candidate.mp4","review.json"}:
            raise ValueError("Candidate bundle inventory")
        review,h=private_json(bundle/"review.json",8192)
        poster=private_asset(bundle/"poster.png",10*1024**2)
        video=private_asset(bundle/"candidate.mp4",20*1024**2)
        expected_review={"version":1,"scope":"native-video-review-only","task_id":job["task_id"],"revision":job["revision"],"channel":job["channel"],"dimensions":job["dimensions"],"model_sha256":model_sha,"job_sha256":digest(job),"seed":seed+i,"poster_sha256":poster,"candidate_sha256":video}
        if h!=row["review_sha256"] or canonical(review)!=canonical(expected_review):
            raise ValueError("Candidate metadata or asset mismatch")
        with Image.open(bundle/"poster.png") as im:
            if im.format!="PNG" or im.size!=tuple(job["dimensions"]) or im.mode!="RGB":raise ValueError("Poster profile mismatch")
            im.verify()
        probe_video(bundle/"candidate.mp4",job["dimensions"])
        if private_asset(bundle/"poster.png",10*1024**2)!=poster or private_asset(bundle/"candidate.mp4",20*1024**2)!=video or private_asset(bundle/"review.json",8192)!=h:
            raise ValueError("Candidate changed during verification")
        results.append({"task_id":job["task_id"],"revision":job["revision"],"review_sha256":h})
    if {x.name for x in root.iterdir()}!=expected_names or private_asset(root/"review-index.json",65536)!=index_digest:
        raise ValueError("Candidate inventory changed")
    return {"version":1,"scope":"verified-native-candidates","plan_sha256":expected,"index_sha256":index_digest,"candidates":results}
REVIEW_REASONS={"ready_for_editorial_review","visual_artifacts","wrong_service","brand_or_crop","privacy_or_claims","other_rework"}
def review_decisions(verified,decisions):
    if not isinstance(decisions,dict) or set(decisions)!={"version","index_sha256","decisions"} or type(decisions["version"]) is not int or decisions["version"]!=1 or decisions["index_sha256"]!=verified["index_sha256"]:
        raise ValueError("Decision scope mismatch")
    items=decisions["decisions"];rows=verified["candidates"]
    if not isinstance(items,list) or len(items)!=len(rows):raise ValueError("Exact candidate decisions required")
    out=[]
    for item,row in zip(items,rows):
        if not isinstance(item,dict) or set(item)!={"task_id","revision","review_sha256","decision","reason"} or any(type(item[k]) is not int or item[k]!=row[k] for k in ("task_id","revision")) or item["review_sha256"]!=row["review_sha256"]:
            raise ValueError("Decision candidate mismatch")
        choice=item["decision"];reason=item["reason"]
        if not isinstance(choice,str) or choice not in ("forward_for_editorial_review","reject") or not isinstance(reason,str) or reason not in REVIEW_REASONS or (choice=="forward_for_editorial_review")!=(reason=="ready_for_editorial_review"):
            raise ValueError("Decision or reason mismatch")
        out.append(dict(item))
    report={"version":1,"scope":"native-video-editorial-decision-only","plan_sha256":verified["plan_sha256"],"index_sha256":verified["index_sha256"],"decisions":out,"publication_authorized":False}
    return {"report":report,"sha256":digest(report)}
def editorial_packet(doc,model_sha,font_sha,seed,expected,directory,index_digest,decisions,decision_digest,day=None):
    """Review-only handoff; never creates a publication manifest or copies media."""
    from datetime import datetime,timezone
    verified=verify_batch(doc,model_sha,font_sha,seed,expected,directory,index_digest)
    report=review_decisions(verified,decisions)
    if not exact_hash(decision_digest) or report["sha256"]!=decision_digest:
        raise ValueError("Reviewed decision changed")
    root=private_candidate_dir(directory)
    items=[]
    for job,row,choice in zip(doc["jobs"],verified["candidates"],report["report"]["decisions"]):
        if choice["decision"]!="forward_for_editorial_review":continue
        name=f"task-{job['task_id']}-r{job['revision']}"
        review,h=private_json(root/name/"review.json",8192)
        if h!=row["review_sha256"]:raise ValueError("Review changed")
        items.append({"review_sha256":h,"review":review})
    if not items:raise ValueError("No forwarded candidates")
    if private_asset(root/"review-index.json",65536)!=index_digest:raise ValueError("Index changed")
    day=day or datetime.now(timezone.utc).date().isoformat()
    return {"version":1,"scope":"native-video-owner-review","review_day_utc":day,"plan_sha256":expected,"index_sha256":index_digest,"decision_sha256":decision_digest,"publication_authorized":False,"items":items}

def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument("mode",choices=["plan","render","verify","review","handoff"]);p.add_argument("--jobs",type=Path,required=True);p.add_argument("--model",type=Path,required=True);p.add_argument("--font",type=Path,required=True);p.add_argument("--seed",type=int,required=True);p.add_argument("--output",type=Path);p.add_argument("--plan-sha256");p.add_argument("--directory",type=Path);p.add_argument("--index-sha256");p.add_argument("--decisions",type=Path);p.add_argument("--decision-sha256")
    a=p.parse_args()
    try:
        doc=media.read_json(a.jobs,65536)
        if a.mode=="plan":
            if any(x is not None for x in (a.output,a.plan_sha256,a.directory,a.index_sha256,a.decisions,a.decision_sha256)):raise ValueError("Unexpected argument")
            print(json.dumps(plan(doc,model_inventory(a.model),font_digest(a.font),a.seed)))
        elif a.mode=="render":
            if a.output is None or any(x is not None for x in (a.directory,a.index_sha256,a.decisions,a.decision_sha256)):raise ValueError("Private output required")
            out=render_batch(doc,a.plan_sha256,a.model,a.font,a.seed,a.output)
            print(out)
            print("Review index SHA256:",media.sha(out/"review-index.json"))
            print("Private candidate only; no approved media manifest or publication.")
        else:
            if a.directory is None or a.output is not None or (a.mode in ("review","handoff"))!=(a.decisions is not None) or (a.mode=="handoff")!=(a.decision_sha256 is not None):raise ValueError("Explicit review input required")
            model_sha=model_inventory(a.model);font_sha=font_digest(a.font)
            if a.mode=="handoff":
                packet=editorial_packet(doc,model_sha,font_sha,a.seed,a.plan_sha256,a.directory,a.index_sha256,media.read_json(a.decisions,65536),a.decision_sha256)
                sys.stdout.buffer.write(canonical(packet)+b"\n")
            else:
                verified=verify_batch(doc,model_sha,font_sha,a.seed,a.plan_sha256,a.directory,a.index_sha256)
                if a.mode=="verify":print(json.dumps(verified))
                else:print(json.dumps(review_decisions(verified,media.read_json(a.decisions,65536))))
        return 0
    except Exception:
        print("BLOCKED: reviewed job, local model, font, CUDA or candidate validation.",file=sys.stderr)
        return 1
if __name__=="__main__":sys.exit(main())
