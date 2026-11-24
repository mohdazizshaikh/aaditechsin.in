import gzip
import hashlib
import importlib.util
from pathlib import Path
import tempfile
spec=importlib.util.spec_from_file_location("backup",Path(__file__).resolve().parents[1]/"workers/backup-verify.py")
m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
def blocked(fn):
    try:fn()
    except (ValueError,OSError,EOFError):return
    raise AssertionError("Expected refusal")
with tempfile.TemporaryDirectory() as tmp:
    p=Path(tmp)/"synthetic.sql.gz";payload=b"CREATE TABLE synthetic(id INT);\n"
    raw=gzip.compress(payload);p.write_bytes(raw);p.chmod(0o600);h=hashlib.sha256(raw).hexdigest()
    assert m.verify(p,h)==(len(raw),len(payload))
    blocked(lambda:m.verify(p,"0"*64))
    blocked(lambda:m.verify(p,h,10))
    p.chmod(0o644);blocked(lambda:m.verify(p,h));p.chmod(0o600)
    q=Path(tmp)/"link.gz";q.symlink_to(p);blocked(lambda:m.verify(q,h))
    p.write_bytes(raw[:-5]);blocked(lambda:m.verify(p,hashlib.sha256(raw[:-5]).hexdigest()))
print("Synthetic backup artifact integrity: PASS")
