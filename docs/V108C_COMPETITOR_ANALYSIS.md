# V10.8C — offline competitor content analysis and reviewed recommendations

Baseline V10.8B be57c718fd48a6488f3c12463cb4abf156ced999. V10.7Z cumulative CI passed (45717209235). No migration.

## Private source prerequisites
Use V10.8A authorized capture and independently permitted local HTML copies. V10.8A deliberately does not retain raw HTML; the operator must independently obtain the exact same response bytes under the site's terms and permission. If the bytes differ, stop; do not forge a capture digest. Do not collect or keep personal, confidential, copyrighted material beyond what is authorized. Use a dedicated canonical 0700 source directory outside webroot, files 0600, and keep them out of Git. Each selected file is named SHA-256 of its exact source URL (UTF-8, no newline) plus .html. Capture and decision JSON also require 0600 single-link regular files. Do not archive source copies unnecessarily.

Provide a private selection JSON: {"version":1,"capture_sha256":"REVIEWED_CAPTURE_DIGEST","pages":[{"host":"aaditechs.in","service_id":"it-amc"},{"host":"peer.example","service_id":"it-amc"}]}. One to 12 selected pages, no duplicates. Current UTC capture day, exact source bytes and structural metadata required. 

`python3 workers/competitor-analysis.py --capture /PRIVATE/capture.json --source-root /PRIVATE/sources < /PRIVATE/selection.json`

Deterministic English lexical cues identify potential evidence for six editorial signals. The worker strips common noncontent and hidden HTML elements, skips contact-like text units, bounds units and output, never emits raw source text or treats lack of a cue as absence. HTML heuristics cannot prove visibility or fully deidentify text. Results remain unreviewed suggestions.

## Optional local AI
Use an independently trusted, installed three-way NLI model with explicit entailment/neutral/contradiction labels and safetensors weights, plus local torch/transformers dependencies. Independently verify model provenance, license, integrity, memory and runtime before use. Add `--model /PRIVATE/trusted-nli-model` to enable local CPU inference. No downloads, custom remote model code, external API requests or publishing. Scores are model entailment outputs, not calibrated truth or evidence of absence. At most three matching units per signal, no free-form model-generated claims. No real model was executed in development; synthetic scorer tests only. Do not assume a model is installed or validated.

## Reviewed recommendations
Add `--decisions /PRIVATE/decisions.json` only after independently reviewing the V10.8A decision template against actual page content. Recommendations depend exclusively on verified editorial inputs, never lexical cues or model scores. Scope must include both own and reviewed present peer pages for the service. Sort candidates by reviewed peer count (not traffic, quality, market rank, or commercial impact). A separate task_scope object is compatible with V10.7B private reviewed task workflow. Owner reviews before any task creation; no automatic task, public copy, outreach or publication.

## Verification
168 cue cases across 14 services and two hosts, 84 isolated reviewed gap cases and 84 missing-peer selection cases, plus private file, source tamper, hidden text, contact-like text, unknown evidence and bounded classifier fixtures authored. New CI pending. No actual model inference, live competitor collection, source retention or deployment.
