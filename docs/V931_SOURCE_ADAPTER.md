# V9.3.1 authorized evidence feed adapter — source only

This is a working configurable HTTPS JSON-feed transport and importer, NOT a connection to any specific procurement/search/social platform. No provider configured or fetched in development. Do not treat a feed as authorized simply because it is publicly reachable. Owner must confirm terms, license, purpose and permitted retention. Current contract is intended for a compliant publisher or an authorized normalization service. Google Maps, LinkedIn, GeM, tender portals and search APIs are not connected. No automatic prospect messaging.

## Private configuration
After backed-up isolated staging migration 2026-11-24-v931.sql, configure one to five sources in the private config (outside webroot). Example only, not a real endpoint:
```php
'discovery' => [
  'enabled' => false,
  'feeds_enabled' => false,
  'feeds' => [
    'example-source' => [
      'authorized' => false,
      'url' => 'https://example.com/feed.json',
      'host' => 'example.com',
      'source_type' => 'company_announcement',
    ],
  ],
],
```
Set each authorized flag only after actual source approval. Feed must be HTTPS, exact configured lower-case hostname, public IPv4, valid certificate, no redirects, credentials, query, fragment or nonstandard port. DNS result is pinned to avoid resolution changes during request; private/reserved addresses rejected. No credentials-based provider adapter yet. cURL must be installed. Response must be HTTP 200 application/json, maximum 256 KiB, 15-second cURL timeout. Up to 25 items per source per run. DNS resolution may add time. Never put customer data, credentials or personal contact details in evidence.

## Contract
```json
{"version":1,"items":[{"source_url":"https://example.com/project","organization":"Example organization","location_label":"Mumbai","evidence":"Planned network infrastructure upgrade","observed_on":"YYYY-MM-DD","signal_type":"upgrade"}]}
```
Example is illustrative. Date must be actual observation date within the existing 180-day bound. Evidence URL must be same configured host, clean HTTPS. Each matching registry service creates one candidate. Existing V9.3 validation and deduplication apply. No assertion that supplied evidence is accurate or organization wants to buy. Human verification remains required.

## Execution and oversight
Copy discovery-feed.php and discovery-feed-lib.php to private secure/maintenance/ alongside other CLI workers. Directory must be writable for private lock. Run `php discovery-feed.php --dry-run` to validate configuration without any network or DB calls. Explicit `--run` requires enabled growth/discovery/feeds flags and resumed control. Overlapping workers blocked by file lock. Pause checked before every source fetch, does not cancel in-flight work. Each run logs counts imported, duplicates, skipped and bounded error codes; partial imports committed record by record. A process interruption can leave a running ledger entry; inspect before retry. Deduplication prevents repeated exact evidence imports, not cross-provider entity resolution. No cron installed. /discoveryruns shows latest ten source runs after authorization; counts are opportunities, not enquiries or sales. Test staging against an authorized controlled endpoint before scheduling. Synthetic test substitutes transport, so live DNS, TLS, cURL, host permissions and provider behavior are unverified.

## Remaining
Real provider selection, credentials-based connectors, licensed search/tender adapters, automatic freshness revalidation, source-run retention, real Telegram delivery, outreach contact permission and suppression registry, outbound delivery and reply integrations. No production change.
