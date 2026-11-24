"""Synthetic media rendering and tamper tests, no actual diffusion model."""
import importlib.util
from pathlib import Path
import tempfile
import json
import subprocess
from PIL import Image
root=Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location("media",root/"workers/local-media.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
font=Path("/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf")
job={"version":1,"task_id":1,"revision":2,"channel":"linkedin","service_title":"IT Infrastructure","caption":"Plan your network.","visual_brief":"Illustrative equipment","video_brief":"Planning checklist","dimensions":[1200,628]}
assert len(m.PROFILES)==9
try:
    m.validate({**job,"dimensions":[1,1]})
except ValueError:
    pass
else:
    raise AssertionError("Invalid profile accepted")
try:
    m.private_root(root/"public_html")
except ValueError:
    pass
else:
    raise AssertionError("Public root accepted")
with tempfile.TemporaryDirectory() as d:
    out=m.render(job,Path(d),font,"template")
    assert Image.open(out/"creative.png").size==(1200,628)
    meta=json.loads((out/"manifest.json").read_text())
    assert meta["renderer"]=="template" and m.verify(out)==m.sha(out/"manifest.json")
    probe=subprocess.check_output(["ffprobe","-v","error","-select_streams","v:0","-show_entries","stream=width,height,codec_name","-of","json",str(out/"creative.mp4")])
    assert json.loads(probe)["streams"]==[{"codec_name":"h264","width":1200,"height":628}]
    with (out/"creative.png").open("ab") as f:
        f.write(b"tampered")
    try:
        m.verify(out)
    except ValueError:
        pass
    else:
        raise AssertionError("Tampering undetected")
    fake=m.render(job,Path(d),font,"local-diffusion",generate=lambda j,p:Image.new("RGB",(768,768),"blue"))
    assert json.loads((fake/"manifest.json").read_text())["renderer"]=="local-diffusion"
    meta=json.loads((fake/"manifest.json").read_text()); meta["width"]=1
    (fake/"manifest.json").write_text(json.dumps(meta))
    try: m.verify(fake)
    except ValueError: pass
    else: raise AssertionError("Manifest profile mismatch accepted")
print("Actual synthetic PNG/MP4 render, profile, private root, fake AI adapter, integrity: PASS")
