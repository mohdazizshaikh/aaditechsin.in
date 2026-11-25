# V10.7K — complete service landing pages and frontend performance

Baseline V10.7J commit e2b72144f16f6170dd961ea03044410e309af5d7: all three CI runs passed (45677263673, 45677299812, 45677309456), including native-video synthetic correction and OAuth rotation mock checks. Main remains V9.6 and production was not changed.

## Business deliverables

All 14 catalog services now have dedicated, directly navigable, indexable HTML landing pages; previously 5 dedicated service pages plus the separate AV planner. Nine new pages: IT infrastructure, software/web, cloud, security/compliance, data management/backup, IT procurement, smart office, special projects and AV installation/maintenance. Each has distinct scope, assessment areas, preparation checklist, process, three FAQs, direct contact and related services. No fabricated case studies, rates, guarantees, licenses, certifications or new customer claims. Homepage has 14 server-delivered service cards and links, all 14 service choices without JS, progressive category filters and quick view; original logo and existing content preserved.

Technical SEO: 16 sitemap URLs (14 services + homepage + planner), canonical/title/description on all pages, new service Open Graph metadata, opt-in analytics allowlist expanded to 16 routes and direct service clicks. Source audit expects 16 public pages. Live SEO v3 explicit --all-services covers the same 16 fixed public paths and per-page details, preserves v1/v2 seven-page input compatibility, stores aggregates only and reports actual scope denominator. No live crawl performed. Contact form accepts allowlisted service query parameter; no automatic submission, no new tracking, flags OFF. Form fields disabled when JS unavailable, direct contact available. Eleven service pages with homepage inquiry CTAs now use the allowlisted service query; the three existing AV pages retain their typed project-planner routes. 

Performance source changes: remove render-blocking Google Fonts request and use system fonts, declare/preload hero image in static HTML instead of JS insertion, stop wholesale rewriting the service grid, lazy async decode secondary imagery and eager priority for the two existing above-fold service photos, move stylesheet into head, optional Apache static-file one-day cache and text compression (no PHP/HTML cache policy). Original photos/logo unchanged. Real Lighthouse/Core Web Vitals, mobile visual acceptance, Apache/Hostinger compression and caching need staging measurement. No claim of measured speedup. Website source and HTTP smoke tests authored; CI pending.

## Acceptance

- Verify all 14 routes, static links, responsive nav and forms, unique metadata, all 16 sitemap/analytics/live observation routes and no broken internal links.
- Verify flags remain OFF; no personal information submitted without consent and backend activation. Owner confirms service descriptions, business claims, contact details and branding before production.
- Measure mobile and desktop performance on isolated staging and compare to baseline; verify cache behavior and no private response caching.
- Major source deliverable is dedicated service page coverage: 5/14 to 14/14. This is a measurable sub-workstream, not a percentage of entire project. No agreed weighted total project baseline exists.

First source commit offline job passed (45686653328), including 14 static routes; disposable DB job failed at dashboard test's former 7-page assertion. Follow-up updates the expected 16-page dashboard dimension, adds service-specific CTA links and verifies them in source tests. Final head CI pending.
