"""Disposable MariaDB only: replay actual V9.6 main schema to current, compare fresh schema."""
import json
import hashlib
import secrets
import struct
import tarfile
import zlib
import os
from pathlib import Path
import re
import subprocess
import tempfile
import pymysql

ROOT=Path(__file__).resolve().parents[1]
assert os.environ.get("AADITECHS_DISPOSABLE_TEST")=="yes"
assert os.environ.get("CI_DB_HOST")=="127.0.0.1"
assert os.environ.get("CI_DB_NAME")==os.environ.get("CI_DB_USER")=="aaditechs_ci"
assert os.environ.get("CI_DB_PASSWORD")=="ci_only_not_real_password"
assert os.environ.get("CI_DB_PORT")=="3306"
contract=json.loads((ROOT/"secure-setup/maintenance/release-contract.json").read_text())
order=contract["migrations"]
assert len(order)==len(set(order))
assert set(order)=={p.name for p in (ROOT/"secure-setup/migrations").glob("*.sql")}
assert order.index("2026-11-24-v99.sql")<order.index("2026-11-24-v100.sql")
db=pymysql.connect(host="127.0.0.1",port=3306,user="aaditechs_ci",password=os.environ["CI_DB_PASSWORD"],database="aaditechs_ci",autocommit=True)
cur=db.cursor()
def tables():
    cur.execute("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")
    return {r[0] for r in cur.fetchall()}
def apply(path):
    # Repository migrations use plain SQL and line comments, no stored procedures.
    sql=re.sub(r"(?m)^\s*--[^\n]*$","",path.read_text(encoding="utf-8"))
    for statement in sql.split(";"):
        if statement.strip():cur.execute(statement)
def signature():
    out={}
    for name,query in [
        ("tables","SELECT TABLE_NAME,ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME"),
        ("columns","SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,COLUMN_NAME"),
        ("indexes","SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX"),
        ("foreign_keys","SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION")
    ]:
        cur.execute(query);out[name]=set(cur.fetchall())
    return out
def reset():
    current=tables()
    assert current<=set(contract["tables"]), "Unknown table; refusing reset"
    cur.execute("SET FOREIGN_KEY_CHECKS=0")
    try:
        for name in sorted(current):
            assert re.fullmatch(r"[a-z0-9_]+",name)
            cur.execute("DROP TABLE "+name)
    finally:cur.execute("SET FOREIGN_KEY_CHECKS=1")
assert not tables(),"Migration test must start on empty disposable database"
try:
    apply(ROOT/"tests/fixtures/schema-v96.sql")
    cur.execute("INSERT INTO leads(reference,full_name,email,service,message,consent_at) VALUES('AT-CI-MIGRATION','Synthetic','migration@example.invalid','IT','Synthetic',UTC_TIMESTAMP())")
    cur.execute("INSERT INTO growth_tasks(service_id,task_type,title,draft) VALUES('infrastructure','research','Synthetic migration','Synthetic')")
    cur.execute("SELECT paused FROM growth_control WHERE id=1")
    assert cur.fetchone()==(1,)
    for name in order[order.index(contract["baseline_migration"])+1:]:
        apply(ROOT/"secure-setup/migrations"/name)
        print("Replay:",name)
    cur.execute("SELECT COUNT(*) FROM leads WHERE reference='AT-CI-MIGRATION'")
    assert cur.fetchone()==(1,)
    cur.execute("SELECT COUNT(*) FROM growth_tasks WHERE title='Synthetic migration'")
    assert cur.fetchone()==(1,)
    cur.execute("SELECT paused FROM growth_control WHERE id=1")
    assert cur.fetchone()==(1,)
    upgraded=signature()
    actual={}
    for r in upgraded["columns"]:actual.setdefault(r[0],set()).add(r[1])
    assert actual=={k:set(v) for k,v in contract["tables"].items()},"Release column contract mismatch"
    # Real dump/restore round trip, only the already-guarded synthetic CI DB.
    env=dict(os.environ,MYSQL_PWD="ci_only_not_real_password")
    args=["--protocol=TCP","--host=127.0.0.1","--port=3306","--user=aaditechs_ci"]
    with tempfile.TemporaryDirectory(prefix="aaditechs-ci-restore-",dir="/tmp") as tmp:
        work=Path(tmp)
        source=work/"source"
        restored=work/"restored"
        for folder in (source,restored):
            folder.mkdir(mode=0o700)
            (folder/".quarantine").mkdir(mode=0o700)
        def write_private(path,data):
            with path.open("xb") as handle:
                path.chmod(0o600)
                handle.write(data)
        def png(width,height,color):
            def chunk(kind,data):
                return struct.pack(">I",len(data))+kind+data+struct.pack(">I",zlib.crc32(kind+data)&0xffffffff)
            return b"\x89PNG\r\n\x1a\n"+chunk(b"IHDR",struct.pack(">IIBBBBB",width,height,8,2,0,0,0))+chunk(b"IDAT",zlib.compress((b"\0"+color*width)*height))+chunk(b"IEND",b"")
        image=png(1200,628,b"\x13\x35\x57")
        orphan=png(16,16,b"\x21\x43\x65")
        video_tmp=work/"fixture.mp4"
        subprocess.run(["ffmpeg","-v","error","-f","lavfi","-i","testsrc2=size=1200x628:rate=24","-t","1","-c:v","libx264","-threads","1","-pix_fmt","yuv420p","-movflags","+faststart","-y",str(video_tmp)],check=True,timeout=30)
        video=video_tmp.read_bytes()
        digest=lambda data:hashlib.sha256(data).hexdigest()
        image_hash=digest(image)
        video_hash=digest(video)
        files={image_hash+".png":image,video_hash+".mp4":video,".quarantine/"+digest(orphan)+".png":orphan}
        for name,data in files.items():write_private(source/name,data)
        # Fixture only: one existing synthetic task points to both real generated files.
        cur.execute("SELECT id FROM growth_tasks WHERE title='Synthetic migration'")
        task_id=cur.fetchone()[0]
        cur.execute("INSERT INTO growth_media_manifests(task_id,revision,manifest_hash,image_hash,video_hash,renderer,width,height) VALUES(%s,1,%s,%s,%s,'template',1200,628)",(task_id,digest(b"synthetic restore manifest"),image_hash,video_hash))
        archive=work/"media.tar.gz"
        with tarfile.open(archive,"x:gz") as tar:
            archive.chmod(0o600)
            for name in sorted(files):tar.add(source/name,arcname=name,recursive=False)
        archive_hash=digest(archive.read_bytes())
        env["AADITECHS_RESTORE_HMAC_KEY_HEX"]=secrets.token_hex(32)
        dump=work/"synthetic.sql"
        with dump.open("xb") as handle:
            dump.chmod(0o600)
            subprocess.run(["mariadb-dump",*args,"--single-transaction","--skip-lock-tables","aaditechs_ci"],env=env,stdout=handle,stderr=subprocess.PIPE,check=True,timeout=60)
        assert 0<dump.stat().st_size<16*1024*1024
        dump_hash=digest(dump.read_bytes())
        def capture(folder):
            proc=subprocess.run(["php",str(ROOT/"tests/restore_rehearsal_capture.php"),str(folder),dump_hash,archive_hash],env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True,timeout=60)
            return json.loads(proc.stdout)
        reference=capture(source)
        assert len(reference["counts"]["counts"])==len(reference["content"]["tables"])==49
        assert len(reference["media"]["references"])==15
        assert reference["media"]["zones"]["active"]["count"]==2
        assert reference["media"]["zones"]["quarantine"]["count"]==1
        assert all(r["missing"]==r["quarantined"]==0 for r in reference["media"]["references"].values())
        assert reference["media"]["references"]["manifest-image"]["count"]==reference["media"]["references"]["manifest-video"]["count"]==1
        # Reconstruct a fresh media directory from the actual synthetic archive;
        # no extractall or external/untrusted archive support.
        with tarfile.open(archive,"r:gz") as tar:
            members=tar.getmembers()
            assert sorted(m.name for m in members)==sorted(files)
            for member in members:
                assert member.isfile() and member.size==len(files[member.name]) and member.size<=20*1024*1024
                with tar.extractfile(member) as handle:data=handle.read(member.size+1)
                assert len(data)==member.size and digest(data)==digest(files[member.name])
                write_private(restored/member.name,data)
        assert digest(archive.read_bytes())==archive_hash and digest(dump.read_bytes())==dump_hash
        reset()
        with dump.open("rb") as handle:
            subprocess.run(["mariadb",*args,"aaditechs_ci"],env=env,stdin=handle,stdout=subprocess.PIPE,stderr=subprocess.PIPE,check=True,timeout=60)
        assert signature()==upgraded,"Restored schema drift"
        cur.execute("SELECT COUNT(*) FROM leads WHERE reference='AT-CI-MIGRATION'")
        assert cur.fetchone()==(1,)
        cur.execute("SELECT COUNT(*) FROM growth_tasks WHERE title='Synthetic migration'")
        assert cur.fetchone()==(1,)
        cur.execute("SELECT paused FROM growth_control WHERE id=1")
        assert cur.fetchone()==(1,)
        assert capture(restored)==reference,"Restored schema, row-count, keyed content or media manifest drift"
        # Same-count mutation must affect content only; missing referenced media must block readiness.
        cur.execute("UPDATE growth_tasks SET title='Synthetic drift',updated_at=updated_at WHERE id=%s",(task_id,))
        mutated=capture(restored)
        assert mutated["counts"]==reference["counts"] and mutated["media"]==reference["media"]
        assert {k for k,v in mutated["content"]["tables"].items() if v!=reference["content"]["tables"][k]}=={"growth_tasks"}
        cur.execute("UPDATE growth_tasks SET title='Synthetic migration',updated_at=updated_at WHERE id=%s",(task_id,))
        video_path=restored/(video_hash+".mp4")
        video_path.unlink()
        missing=capture(restored)
        assert missing["counts"]==reference["counts"] and missing["content"]==reference["content"]
        assert missing["media"]["references"]["manifest-video"]["missing"]==1
        assert missing["media"]["zones"]["active"]["count"]==1
        write_private(video_path,video)
        assert capture(restored)==reference
        print("Synthetic MariaDB and media archive restore: 49 count and content checks, 15 reference sources, active/quarantine parity and negative drift checks PASS")
    reset()
    apply(ROOT/"secure-setup/schema.sql")
    fresh=signature()
    for k in fresh:
        if fresh[k]!=upgraded[k]:
            print("Schema drift",k,"upgrade-only",sorted(upgraded[k]-fresh[k],key=str)[:12],"fresh-only",sorted(fresh[k]-upgraded[k],key=str)[:12])
            raise AssertionError("Upgrade/fresh schema drift: "+k)
    print("V9.6 upgrade replay and fresh schema metadata parity: PASS")
finally:
    reset()
    cur.close();db.close()
