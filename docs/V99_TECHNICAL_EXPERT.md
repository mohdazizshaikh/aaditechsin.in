# V9.9 technical expert — structured initial phase

All 14 registered services have a versioned general requirements, planning and escalation knowledge entry. Public technical guide is optional and static, not a live LLM. Existing guided chat and consent-based inquiry flow are preserved. No customer text or personal data is sent to the local model by this module.

## Owner workflow
Apply additive V9.9 migration after V9.8 on isolated staging. Set expert.enabled true in private configuration only after acceptance, with growth enabled and resumed. /expert infrastructure assessment (or planning or escalation) creates a private research task. /experts lists drafts. No free text case details or customer data accepted. Export with php secure/maintenance/expert-worker.php --export ID > expert-job.json, transfer privately to local PC, run python3 workers/local-expert.py --model YOUR_LOCAL_MODEL < expert-job.json > expert-result.json, then privately transfer result and run php secure/maintenance/expert-worker.php --import < expert-result.json. Local PC must have Ollama installed, bound to loopback, cloud disabled, trusted model installed and no remote access. Worker checks the job exactly against local versioned catalog and knowledge. Update both copies together.

Use /task ID and /approve ID REV or /reject ID REV or /regenerate ID REV. Review records an internal draft only. No external action. A fixed escalation note is appended; regex filters and prompt constraints do not prove model safety or correctness. Human reviewer must check every output, especially security, electrical/rigging and data recovery. Do not use generated text as an engineering specification. No expert publishing connector.

## Optional website guide
technicalGuide defaults false in site-config.js. Enable only after browser/staging acceptance. Existing chat panel then offers a separate 14-service selector with general requirements, planning and escalation guidance. Static JSON loaded same-origin only, no user input, model, storage or inquiry submission. Existing lead consent flow remains separate.

## Pending
Actual local model inference and quality evaluation, expanded technical reference review, free-text customer Q&A with consent and abuse controls, automated expert job transport, public live AI endpoint, customer-specific qualification, multi-turn sessions, structured project estimates and staging acceptance. No credentials, production change or contact delivery in CI.
