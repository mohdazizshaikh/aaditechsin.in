"""Private offline editorial comparison of operator-reviewed public page observations.

No requests, scraping, search results, traffic estimates, or automated publishing.
"""
import argparse
import datetime as dt
import hashlib
import json
import re
import sys
from pathlib import Path
from urllib.parse import urlsplit

ROOT = Path(__file__).resolve().parents[1]
OWN = "aaditechs.in"
LIMIT = 131072
SIGNALS = (
    "service_scope", "process_explained", "limitations_explained",
    "contact_route", "case_evidence_visible", "maintenance_options",
)
STATES = ("present", "absent", "unknown")
ACTIONS = {
    "service_scope": "Review and clarify the actual service scope.",
    "process_explained": "Review whether the real assessment and delivery process can be explained.",
    "limitations_explained": "Review actual exclusions, dependencies and limitations.",
    "contact_route": "Review an authorized inquiry route without exposing customer data.",
    "case_evidence_visible": "Review whether permissioned, substantiated case evidence exists; never invent proof.",
    "maintenance_options": "Review actual maintenance options and eligibility before describing them.",
}

def exact(obj, keys):
    if type(obj) is not dict or set(obj) != set(keys):
        raise ValueError("Unexpected fields")

def unique_object(pairs):
    out = {}
    for key, val in pairs:
        if key in out:
            raise ValueError("Duplicate JSON member")
        out[key] = val
    return out

def reject_constant(_):
    raise ValueError("Invalid JSON constant")

def decode(raw):
    if not isinstance(raw, bytes) or len(raw) > LIMIT:
        raise ValueError("Input cap")
    return json.loads(raw.decode("utf-8"), object_pairs_hook=unique_object,
                      parse_constant=reject_constant)

def services():
    registry = json.loads((ROOT / "public_html/assets/service-registry.json").read_text(encoding="utf-8"))
    topics = json.loads((ROOT / "public_html/assets/seo-topics.json").read_text(encoding="utf-8"))
    ids = tuple(row["id"] for row in registry["services"])
    if len(ids) != 14 or len(set(ids)) != 14 or set(ids) != set(topics["services"]):
        raise ValueError("Registry coverage")
    return ids

def host(value):
    if type(value) is not str or len(value) > 253 or value != value.lower():
        raise ValueError("Invalid host")
    labels = value.split(".")
    if len(labels) < 2 or not re.fullmatch(r"[a-z]{2,63}", labels[-1]):
        raise ValueError("DNS hostname required")
    if any(not re.fullmatch(r"[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?", x) for x in labels):
        raise ValueError("Invalid DNS labels")
    if labels[-1] in {"local", "localhost", "internal", "onion"}:
        raise ValueError("Non-public host")
    return value

def evidence_url(value, domain):
    # Deliberately limited to clean public-page references. No query, credentials,
    # percent escapes, private routes, port, fragment, or non-ASCII path.
    if type(value) is not str or len(value) > 512:
        raise ValueError("Invalid URL")
    if not re.fullmatch(r"https://[a-z0-9.-]+(?:/[A-Za-z0-9_./~-]*)?", value):
        raise ValueError("Invalid URL syntax")
    u = urlsplit(value)
    if u.netloc != domain or u.scheme != "https":
        raise ValueError("Unbound evidence host")
    parts = u.path.split("/")
    if any(x in {".", ".."} for x in parts) or "//" in u.path:
        raise ValueError("Ambiguous path")
    if any(x.lower() in {"api", "admin", "portal", "track", "login", "account", "private"} for x in parts):
        raise ValueError("Non-public route")
    return "https://" + domain + (u.path or "/")

def observed(value, today):
    if type(value) is not str or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", value):
        raise ValueError("Invalid observation date")
    day = dt.date.fromisoformat(value)
    if day > today or day < today - dt.timedelta(days=30):
        raise ValueError("Observation outside 30-day window")
    return value

def normalize(doc, today):
    exact(doc, ("version", "scope", "subjects"))
    if type(doc["version"]) is not int or doc["version"] != 1 or doc["scope"] != "reviewed-public-editorial-evidence":
        raise ValueError("Unsupported input")
    entries = doc["subjects"]
    if type(entries) is not list or not 2 <= len(entries) <= 6:
        raise ValueError("One own site and one to five peers required")
    ids = services()
    result = {}
    for item in entries:
        exact(item, ("host", "observations"))
        domain = host(item["host"])
        if domain in result or domain == "www." + OWN:
            raise ValueError("Duplicate or own-site alias")
        rows = item["observations"]
        if type(rows) is not list or not 1 <= len(rows) <= len(ids):
            raise ValueError("Observation count")
        by_service = {}
        for row in rows:
            exact(row, ("service_id", "source_url", "observed_on", "signals"))
            service = row["service_id"]
            if type(service) is not str or service not in ids or service in by_service:
                raise ValueError("Invalid or duplicate service")
            flags = row["signals"]
            exact(flags, SIGNALS)
            if any(type(v) is not str or v not in STATES for v in flags.values()):
                raise ValueError("Invalid evidence state")
            by_service[service] = {
                "service_id": service,
                "source_url": evidence_url(row["source_url"], domain),
                "observed_on": observed(row["observed_on"], today),
                "signals": {key: flags[key] for key in SIGNALS},
            }
        result[domain] = by_service
    if OWN not in result:
        raise ValueError("Own site required")
    return result

def compare(doc, today=None):
    today = today or dt.datetime.now(dt.timezone.utc).date()
    subjects = normalize(doc, today)
    ids = services()
    peers = sorted(set(subjects) - {OWN})
    canonical = [
        {"host": name, "observations": [subjects[name][s] for s in ids if s in subjects[name]]}
        for name in [OWN, *peers]
    ]
    digest = hashlib.sha256(json.dumps(canonical, sort_keys=True, separators=(",", ":")).encode()).hexdigest()
    coverage = []
    for name in [OWN, *peers]:
        rows = subjects[name]
        coverage.append({"host": name, "services_observed": len(rows),
                         "known_signals": sum(v != "unknown" for row in rows.values() for v in row["signals"].values()),
                         "possible_signals": len(ids) * len(SIGNALS)})
    comparisons = []
    for service in ids:
        own = subjects[OWN].get(service)
        for key in SIGNALS:
            state = own["signals"][key] if own else "unknown"
            peer_states = []
            for name in peers:
                row = subjects[name].get(service)
                peer_states.append({"host": name, "state": row["signals"][key] if row else "unknown",
                                    "source_url": row["source_url"] if row else None,
                                    "observed_on": row["observed_on"] if row else None})
            present = sum(p["state"] == "present" for p in peer_states)
            absent = sum(p["state"] == "absent" for p in peer_states)
            if state == "unknown" or present + absent == 0:
                outcome = "insufficient_evidence"
            elif state == "absent" and present:
                outcome = "editorial_review_candidate"
            elif state == "present" and absent:
                outcome = "own_observed_content"
            else:
                outcome = "no_gap_identified"
            comparisons.append({
                "service_id": service, "signal": key, "own_state": state,
                "own_source_url": own["source_url"] if own else None,
                "own_observed_on": own["observed_on"] if own else None,
                "peer_states": peer_states, "peers_present": present, "peers_absent": absent,
                "peers_unknown": len(peers) - present - absent, "outcome": outcome,
                "review_action": ACTIONS[key] if outcome == "editorial_review_candidate" else None,
            })
    return {
        "version": 1, "scope": "private-offline-editorial-comparison", "as_of_utc": today.isoformat(),
        "input_sha256": digest, "coverage": coverage, "comparisons": comparisons,
        "outcome_counts": {state: sum(x["outcome"] == state for x in comparisons) for state in
                           ("insufficient_evidence", "editorial_review_candidate", "own_observed_content", "no_gap_identified")},
        "limitations": "Operator observations only; not verified network provenance, provider quality, rankings, traffic, demand, market share or permission to copy.",
    }

def task_scope(doc, today=None):
    """Propose only eligible cells; operator must review and may remove items."""
    result = compare(doc, today)
    return {
        "version": 1, "scope": "competitor-editorial-review",
        "evidence_sha256": result["input_sha256"], "review_day_utc": result["as_of_utc"],
        "items": [{"service_id": x["service_id"], "signal": x["signal"]}
                  for x in result["comparisons"] if x["outcome"] == "editorial_review_candidate"],
    }

def main():
    p = argparse.ArgumentParser(description=__doc__)
    modes = p.add_mutually_exclusive_group(required=True)
    modes.add_argument("--compare-reviewed", action="store_true")
    modes.add_argument("--propose-task-scope", action="store_true")
    args = p.parse_args()
    try:
        source = decode(sys.stdin.buffer.read(LIMIT + 1))
        result = task_scope(source) if args.propose_task_scope else compare(source)
        print(json.dumps(result, ensure_ascii=True, indent=2))
    except (ValueError, TypeError, KeyError, OSError, RecursionError):
        p.exit(1, "BLOCKED: private evidence scope, registry, date or URL validation failed.\n")

if __name__ == "__main__":
    main()
