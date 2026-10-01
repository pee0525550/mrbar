# Audit Follow-Up v1.49.18

## Scope
Continues v1.49.17 with durable LINE delivery for new customer/staff bookings
and customer confirmation transitions. No database schema upgrade is required:
the branch-scoped `line_outbox` bucket is normalized by the existing migration.
Existing sent bookings are not bulk replayed or broadcast to followers.

## Delivery Contract
- Queue insertion and booking/status updates share one atomic database write.
- Customer receipts, customer confirmations and eligible staff alerts are separate
  jobs. API-auto-confirmed bookings enqueue a confirmation, not a pending receipt.
- The original message, recipient and UUID retry key are persisted together.
  Editing a template affects new messages only, never an in-flight retry.
- A 120-second claim lease prevents concurrent workers from taking the same job.
  Stale worker results cannot overwrite a replacement worker's result.
- Network/timeouts and 5xx errors retry after 60, 120, 240 and 480 seconds.
  There are at most five attempts. Other 4xx results stop automatic retry.
  An admin may retry before the limit/window after correcting configuration;
  the original payload/key and cooldown remain unchanged.
- Jobs expire 23 hours after creation, before LINE's 24-hour retry-key window.
  This also prevents old, never-attempted configuration backlogs being sent later.
- Dispatch rechecks the reservation state, confirmation toggle, recipient access
  and LINE binding. Withdrawn confirmations and superseded receipts stop sending.
- Terminal queue history is pruned after 30 days when a worker runs. Delivery
  summaries on bookings remain. Stored payloads stay in protected server storage.
- HTTP 409 is accepted only with `x-line-accepted-request-id`. Acceptance means
  LINE accepted the API request, not proof of delivery or that the user read it.

Reference: https://developers.line.biz/en/docs/messaging-api/retrying-api-request/

## Runtime
Booking redirects, receipt views, the reservation board and authenticated
notification polling opportunistically start a bounded worker. The PHP session
is closed before network calls. On PHP-FPM, `fastcgi_finish_request()` finishes
the client response before dispatch. On non-FPM servers dispatch remains bounded
but can still delay completion of the response. This is not a separate daemon.

For unattended retries while nobody is browsing, configure a hosting Cron Job
every minute to run `scripts/line-outbox-worker.php` with the hosting PHP CLI.
It is CLI-only and cannot be run via a public URL. The CLI process must receive
`MRBAR_LINE_CHANNEL_ACCESS_TOKEN` securely from the hosting environment.
Apache `.htaccess` SetEnv values do NOT automatically configure PHP CLI.
Never paste the token into source control, the command line or this document.
Without cron, durable jobs remain stored and resume on subsequent relevant
page/feed requests; continuous unattended delivery is not guaranteed.

## UI
Control Center > Reservations has a new LINE Delivery section showing the
latest 30 messages, type, state, attempt count, HTTP result and next retry time.
Retry controls require `reservations.manage`, an active current account, CSRF,
the current branch and a valid delivery state. Missing-token controls disable.
Customer receipts show queued delivery instead of reporting premature failure.

## Verification
- 29 PHP regression scripts; dedicated queue tests cover leases, crashes, stale
  results, immutable payloads, backoff, retry cutoff and recipient revocation.
- Existing five JavaScript regressions and isolated HTTP/Edge regression.
- Synthetic HTTP worker tests persist 503 failures, enforce cooldown, accept a
  verified 409 without resending, and persist confirmation jobs exactly once.
- Browser checks at 320/390/768/1200/1440 pixels cover the delivery section and
  existing booking workflows, with no overlap/overflow or browser JS errors.
- No local test uses real LINE tokens, customer records or sends real messages.

## Deployment
Install on v1.49.17. The pack uses the standard outer `_deploy_pack` folder and
contains all 13 application files listed in `release-info.json`, including the
optional CLI worker. Upload those paths relative to the existing website root.
Do not replace hosting secrets or `storage` with a local development copy.
Clear hosting OPcache if needed and check the visible version is v1.49.18.

## Remaining Boundaries
Real OA delivery and hosting FPM/Cron behavior require production verification.
Closed-app Web Push and other admin/Time Staff visual QA remain separate work.
This release does not claim every project audit item has been resolved.
