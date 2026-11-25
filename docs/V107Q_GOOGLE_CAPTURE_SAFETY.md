# V10.7Q — scheduled Google capture safety

Baseline: V10.7P `53c2cdf03feaf77513f2074d599436077f53facb`. Both PR-triggered CI runs completed successfully: [45695332318](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45695332318), [45695325904](https://github.com/mohdazizshaikh/aaditechsin.in/actions/runs/45695325904).

## Changes
- Revalidate current exact reviewed grant, database configuration, monotonic wall-clock and remaining token safety window before **each** individual provider read, including Gmail profile to INBOX (previously one compound call) and Search Console site to query.
- Check the growth pause gate in a short DB transaction before each read; do not hold it across HTTP. Roll back on refusal and release advisory lock.
- Reject backward clock movement and elapsed token safety windows before additional reads or final commit. Bind CLI configuration reloads to the database settings used to open the connection.
- Twelve disposable integration scenarios (two providers × six disruptions: revoke, pause, database drift, backward clock, expired token safety window, wrong identity) verify second request is not made, old snapshot unchanged, transaction closed and advisory lock freed.

## Limits and acceptance
- No additional provider permission, configuration flag, credential persistence, migrations, production deployment or actual Google call. Existing default-OFF flags and review deadlines unchanged.
- In-flight HTTP cannot be cancelled; private configuration changes must still be coordinated with stopped workers. Wall-clock checks detect backwards readings at sampled boundaries, not arbitrary changes between samples.
- Current V10.7Q CI pending until independently verified. Actual Gmail/SC consent, provider behavior, isolated cron and database/media restore still pending.
- Previous source tests do not count as Hostinger acceptance; no overall percentage or 100–200-task completion claim.
