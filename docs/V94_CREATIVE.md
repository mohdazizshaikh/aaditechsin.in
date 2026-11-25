# V9.4 local AI creative workflow — source only

V9.4 adds a catalog-only draft generator for 14 website services and nine channel formats (Facebook, Instagram, LinkedIn, YouTube, Google Business Profile, X, Threads, Telegram, blog). Objectives: education, awareness, enquiry, case-planning. The AI produces a caption, visual design brief, short video storyboard brief, and human review notes. It does not render media, access social accounts, send messages, or verify factual accuracy. No live Ollama server was contacted by CI. No customer/prospect details or tokens exported. English initial content only.

## Setup and operation
Apply additive V9.4 SQL migration after V9.3.1 to backed-up isolated staging. Both growth and creative flags OFF by default. Deploy private creative-worker.php outside public_html. Use Telegram /createcontent infrastructure linkedin education after /resume, /content to list, /coverage to see inventory across all 14 services. Tasks are initially regeneration_requested and cannot be approved until a draft arrives. Existing /regenerate ID REV increments the revision; old results cannot overwrite current requests. /task ID shows the caption and briefs. Approvals record review only.

On staging shell, privately export one request:
```bash
php secure/maintenance/creative-worker.php --pending
php secure/maintenance/creative-worker.php --export 123 > job.json
```
123 is an example task ID. Transfer the file privately to the local AI PC (not public Git or webroot). Install and run Ollama with cloud features disabled and an already downloaded open-weight model you have verified is local; keep Ollama bound to loopback. Do not expose port 11434 to internet. Example, substitute your installed local model tag:
```bash
python3 workers/local-creative.py --model YOUR_LOCAL_MODEL < job.json > result.json
```
The worker calls only the loopback Ollama /api/chat endpoint using structured JSON, no proxy/redirect/tools; model/cloud-tag check alone cannot prove the server never uses a cloud service. Owner must configure and verify local-only Ollama, and firewall if needed. See https://docs.ollama.com/api/chat. No automatic download of models, no external API keys. Private result transfer back to staging:
```bash
php secure/maintenance/creative-worker.php --import < result.json
```
Import requires exact pending revision, resumed control and validated output. Channel byte limits and common unsupported-claim patterns are conservative checks, not a complete factual or compliance audit. Reviewer must verify all claims, capabilities, IP rights, imagery and brand information. Approved content is not published. No credentials or personal data in any generated job/result. No remote database access needed.

## Remaining
Automated authenticated local-worker transport/scheduling, model runtime acceptance, AI technical Q&A, opportunity analysis, content calendar scheduling, actual image/video rendering, official provider OAuth/posting/inbox/metrics, scheduled Telegram notifications, permissions and production staging. Keep external execution disabled until individual connectors and review gates pass. Synthetic tests use mock Ollama output only.

V10.6Q requires exact local catalog match for exported jobs and rejects duplicate JSON keys. Keep local checkout and staging catalog synchronized; see [pinned worker guide](V106Q_PINNED_LOCAL_WORKER.md).
