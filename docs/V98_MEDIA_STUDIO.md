# V9.8 private local media studio — source phase

V9.8 is stacked on V9.7. It renders a branded PNG and four-second silent MP4 motion slide on the owner PC. Optional local diffusion adapter loads a trusted compatible preinstalled Diffusers pipeline using local files/safetensors; no model downloads by the worker. Template mode is deterministic, not AI imagery. The MP4 is an image-based fade animation, not native generative video, voiceover or storyboard synthesis. The video brief is exported for human review but is not interpreted by this renderer. Original logo is composited from the existing repository asset. No binary media uploads, no publishing connector changes.

## Setup and owner workflow

- Apply additive V9.8 migration after V9.7 on isolated backed-up staging. Set private media.enabled true only after acceptance; requires creative and growth enabled and growth resumed. New flag defaults OFF.
- Approve text revision in private Telegram. Export only that current approved revision on the private server CLI: php secure/maintenance/media-worker.php --export 123 2 > media-job.json
- Transfer privately to owner PC. Install Python 3.11+, Pillow, trusted FFmpeg with libx264, trusted local font (e.g. DejaVu Sans). For AI mode separately install compatible torch/diffusers/transformers and vetted local model weights, verify license and hardware. Use OS firewall/offline execution for model isolation; offline environment flags are not a sandbox. Never use untrusted custom pipelines.
- Create a private output root outside Git/repo/public webroot with Unix 0700 or restrictive Windows ACL. Example:
  python workers/local-media.py render --job media-job.json --output PRIVATE_DIRECTORY --font TRUSTED_FONT.ttf --renderer template
  For AI mode replace template with local-diffusion and add --model LOCAL_MODEL_DIRECTORY.
- Inspect creative.png and creative.mp4 in local image/video viewer. Verify actual content and rights; check logo, cropping, claims and illustrative nature. The worker does not render the caption or infer claims.
- Run python workers/local-media.py verify --directory OUTPUT_JOB_DIRECTORY, then transfer only manifest.json privately to server and run php secure/maintenance/media-worker.php --register < manifest.json. Compare printed SHA-256 against local verification; server registers metadata only, not actual files.
- /media ID lists metadata. After personally reviewing matching files, /approvemedia ID REV SHA256 or /rejectmedia ID REV SHA256 records decision for exact manifest and current approved text revision. No upload or publication happens. A new render gets a distinct manifest if bytes differ. Duplicate registration does not reset review status. Never treat local file hashes as provider acceptance.
- No automatic media job transport or scheduling yet; use explicit CLI transfer. No public asset URLs. Storage cleanup/retention is owner-managed. Keep existing text-only social publishing separate.

## Initial output profiles (creative defaults, not provider API guarantees)

Instagram and YouTube: 1080x1920 vertical; Threads and Telegram: 1080x1080 square; Facebook, LinkedIn, Google Business Profile, X and blog: 1200x628 landscape. Actual upload requirements, duration, safe zones and provider constraints must be verified before any future connector.

## Acceptance

Synthetic CI exercises real template PNG/MP4 creation, a fake diffusion adapter, manifest validation and owner review. Real model inference, model-specific safety and licensing, Windows ACLs, local hardware/codec performance, asset transfer and review acceptance on staging remain pending. Native text-to-video, storyboard sequencing, automated authenticated binary transport, owner preview in Telegram and image/video social posting remain future work.
