"""Disposable MariaDB only: replay actual V9.6 main schema to current, compare fresh schema."""
import json
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
    with tempfile.TemporaryDirectory(prefix="aaditechs-ci-restore-") as tmp:
        dump=Path(tmp)/"synthetic.sql"
        with dump.open("xb") as handle:
            dump.chmod(0o600)
            subprocess.run(["mariadb-dump",*args,"--single-transaction","--skip-lock-tables","--no-tablespaces","aaditechs_ci"],env=env,stdout=handle,stderr=subprocess.PIPE,check=True,timeout=60)
        assert 0<dump.stat().st_size<16*1024*1024
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
        print("Synthetic MariaDB dump and restore round trip: PASS")
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
