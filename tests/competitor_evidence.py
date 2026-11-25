import copy
import datetime as dt
import importlib.util
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("competitor", root / "workers/competitor-evidence.py")
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
today = dt.date(2026, 11, 25)
ids = m.services()

def row(domain, service, state="unknown"):
    return {"service_id": service, "source_url": "https://" + domain + "/" + service + "/",
            "observed_on": today.isoformat(), "signals": dict.fromkeys(m.SIGNALS, state)}

def doc():
    return {"version": 1, "scope": "reviewed-public-editorial-evidence", "subjects": [
        {"host": m.OWN, "observations": [row(m.OWN, service) for service in ids]},
        {"host": "peer.example", "observations": [row("peer.example", service) for service in ids]}]}

def blocked(fn):
    try:
        fn()
    except ValueError:
        return
    raise AssertionError("Invalid evidence accepted")

cases = 0
for i, service in enumerate(ids):
    for signal in m.SIGNALS:
        for own, peer, outcome in (
            ("absent", "present", "editorial_review_candidate"),
            ("present", "absent", "own_observed_content"),
            ("unknown", "present", "insufficient_evidence"),
            ("absent", "unknown", "insufficient_evidence"),
            ("absent", "absent", "no_gap_identified"),
            ("present", "present", "no_gap_identified"),
        ):
            d = doc()
            d["subjects"][0]["observations"][i]["signals"][signal] = own
            d["subjects"][1]["observations"][i]["signals"][signal] = peer
            result = m.compare(d, today)
            cell = next(x for x in result["comparisons"] if x["service_id"] == service and x["signal"] == signal)
            assert cell["outcome"] == outcome
            assert (cell["review_action"] is not None) == (outcome == "editorial_review_candidate")
            assert sum(result["outcome_counts"].values()) == 84
            assert cell["peers_unknown"] == int(peer == "unknown")
            cases += 1
assert cases == 504

d = doc()
d["subjects"][0]["observations"].pop()
r = m.compare(d, today)
assert r["coverage"][0]["services_observed"] == 13 and len(r["comparisons"]) == 84
assert all(x["own_state"] == "unknown" and x["own_source_url"] is None for x in r["comparisons"] if x["service_id"] == ids[-1])
reordered = copy.deepcopy(d)
reordered["subjects"].reverse()
for subject in reordered["subjects"]:
    subject["observations"].reverse()
assert m.compare(reordered, today) == r

for bad in ("http://peer.example/", "https://peer.example/?token=x", "https://peer.example/#x",
            "https://peer.example:443/", "https://peer.example@evil.example/", "https://evil.example/",
            "https://peer.example/%2e%2e/", "https://peer.example/portal/", "https://peer.example/a/../",
            "https://peer.example//a", "https://peer.example/\n", "https://peer.example/private/"):
    d = doc()
    d["subjects"][1]["observations"][0]["source_url"] = bad
    blocked(lambda: m.compare(d, today))
for bad in ("2026-11-26", "2026-10-25", "2026-02-30", "2026-1-1"):
    d = doc()
    d["subjects"][1]["observations"][0]["observed_on"] = bad
    blocked(lambda: m.compare(d, today))
for bad in ("true", True, 1, None, "unverified"):
    d = doc()
    d["subjects"][0]["observations"][0]["signals"][m.SIGNALS[0]] = bad
    blocked(lambda: m.compare(d, today))
for raw in (b'{"version":1,"version":1}', b'{"x":NaN}', b'\xff', b' ' * (m.LIMIT+1)):
    blocked(lambda: m.decode(raw))
d = doc()
d["subjects"][1]["observations"].append(copy.deepcopy(d["subjects"][1]["observations"][0]))
blocked(lambda: m.compare(d, today))
d = doc()
d["subjects"].append(copy.deepcopy(d["subjects"][1]))
blocked(lambda: m.compare(d, today))
d = doc()
d["subjects"][0]["host"] = "other.example"
blocked(lambda: m.compare(d, today))
d = doc()
d["subjects"][0]["observations"][0]["contact"] = "synthetic"
blocked(lambda: m.compare(d, today))
d = doc()
for i in range(4):
    name = "peer" + str(i) + ".example"
    d["subjects"].append({"host": name, "observations": [row(name, ids[0])]})
r = m.compare(d, today)
assert len(r["coverage"]) == 6 and all(len(x["peer_states"]) == 5 for x in r["comparisons"])
d["subjects"].append({"host": "extra.example", "observations": [row("extra.example", ids[0])]})
blocked(lambda: m.compare(d, today))
assert m.decode(json.dumps(doc()).encode()) == doc()
print("Private competitor evidence: 504 service/signal decision cases and scope checks PASS")
