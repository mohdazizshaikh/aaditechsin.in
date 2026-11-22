# Aaditechs — Website V6 Source

Source repository for the Aaditechs modern HTML5 website concept and the planned Hostinger PHP backend integration. **This repository is a development baseline, not evidence of a completed production deployment.**

- Production domain: https://aaditechs.in/ (unchanged by this repository import)
- Private concept preview: https://aaditechs-modern-concept-2026.tiledparrot.chatgpt.site
- Complete deployment and secrets instructions: [README_DEPLOYMENT.md](README_DEPLOYMENT.md)
- Verified baseline / next steps: [docs/PROJECT_STATUS.md](docs/PROJECT_STATUS.md)

## Source structure

```text
public_html/
  index.html
  assets/                  HTML5 website assets, original logo, CSS, JS
  api/                     PHP server-side integrations (disabled by default)
secure-setup/
  aaditechs-config.php.example  # Empty placeholder template; do not enter real credentials
  composer.json            # PHPMailer dependency
README_DEPLOYMENT.md        # Detailed Hostinger guide
```

## Safety

- The public preview is **static** and does not execute PHP.
- Google Business Profile reviews API is **not approved or live**; no API credentials are in this repository.
- Contact form SMTP backend is **not configured or verified live**; feature flags are OFF.
- Real `aaditechs-config.php`, OAuth client secret, refresh token, SMTP passwords and customer records must **never** be committed. Store secrets outside `public_html/` on a verified protected server path.
- Never overwrite production `aaditechs.in` without backup, staging validation and owner authorization.

## Next development phase (not yet implemented)

Dedicated Video Conferencing, Auditorium AV and Professional Sound System pages, advanced intelligent enquiry/chat experience and coordinated partner operations workflows are approved ideas but **not present as production-ready implementations** in this baseline. Continue development in new feature branches or pull requests.
