# Customer Booking, Deposit and Notification Architecture

## Current behavior

The public reservation form creates a `waitlist` request and requires LINE Login. Staff notifications are stored in the existing in-app notification list; the current page polling is not a native mobile push notification. Deposit collection is opt-in per branch and defaults off.

The integration boundary routes new reservation notifications only to active users who can view/manage reservations in the active branch, plus the linked Sales account. No slip verifier provider is bundled or contacted unless its adapter and server environment credentials are present.

## Booking and payment flow

1. Customer selects branch, date/time, party size, table/zone preference and contact details.
2. If the branch deposit policy is enabled, calculate the required amount from the branch policy and show its QR/instructions. Do not hold or confirm a table while payment is pending.
3. Customer uploads a slip to private storage. The current compatible reservation status remains `waitlist`, with a separate `deposit_status=pending_review`; no sequential reservation ID grants access to the slip.
4. In API mode, send the slip and booking context to the configured verifier adapter. Timeout/unknown/incomplete responses remain `pending_review`, never `verified`.
5. Only a verified payment can transition the reservation to `confirmed` or allow seating. Staff review is audited; turning off the deposit policy bypasses payment for new reservations without removing old reservations' payment requirements.
6. On confirmation, send the existing customer/staff notifications. Keep cancellation/refund policy separate from payment verification.

Provider-specific code belongs in `app/integrations/slip-{provider}.php` and must expose `mrbar_slip_verify_{provider}($filePath, $context)`. Secrets are read from server environment variables only. No provider endpoint, credentials or production deposit amount is assumed here.

## Notification channels

Use one event with channel adapters so other event types can reuse the same delivery path:

- `in_app`: existing staff notification list (available now).
- `mobile_push`: Web Push/PWA or FCM provider (requires choosing a provider, application credentials and mobile opt-in/subscription lifecycle).
- `line`: LINE Messaging API push to a staff account linked by verified LINE user ID (requires Official Account channel access token and the recipient to be reachable by that OA).

LIFF is the mobile Mini App/login and account-linking surface. LIFF itself does not send a server-initiated notification; that is the LINE Messaging API's job. The server must verify `liff.getIDToken()` with LINE before persisting the LINE user ID. Never trust a user ID/profile sent directly from browser JavaScript.

Events should carry an idempotency key such as `reservation:{branch}:{id}:confirmed`. Delivery should run from a queue/worker, record provider message IDs and retryable failures, use LINE retry keys, and never make a reservation transaction wait on an external API.

## Server environment contract

Set these in the host/container environment, not in a public settings page or source control:

- `MRBAR_SLIP_PROVIDER`, `MRBAR_SLIP_API_KEY` and provider-specific values for the selected verifier.
- `MRBAR_LINE_CHANNEL_ACCESS_TOKEN`, `MRBAR_LINE_CHANNEL_SECRET`, `MRBAR_LINE_LOGIN_CHANNEL_ID`, `MRBAR_LINE_LIFF_ID`.
- `MRBAR_PUSH_PROVIDER`, `MRBAR_PUSH_APP_ID`, `MRBAR_PUSH_SERVER_KEY` (names are the boundary; exact values depend on the selected push service).

No secrets are returned by the integration status helper. All integrations stay dormant when their required values/adapter are missing.

## Decisions needed before enabling customer deposits

- Slip verification provider and its API contract.
- Deposit amount/rules by branch, zone/table or party size; minimum-spend treatment.
- PromptPay/bank destination and whether the provider creates dynamic QR codes or only verifies uploaded slips.
- Payment expiry, cancellation/no-show, refund/partial-refund rules and staff override permissions.
- Push choice (Web Push/VAPID versus FCM/OneSignal) and whether staff should opt in through PWA, LINE, or both.
- LINE Official Account, LIFF app ID, LINE Login channel ID and which roles receive each event.

## Reservation payment settings added

The branch-scoped admin page `reservation-settings.php` controls online reservation availability, the deposit emergency bypass, payment mode, confirmation mode, deposit amount/calculation, QR URL, recipient, payment instructions and reservation terms. Defaults keep the current no-payment flow unchanged. Turning off the deposit toggle bypasses payment for new bookings without deleting the configured payment details.

The customer form snapshots the selected payment mode, expected amount and private slip path on each reservation. JPG/PNG/WEBP uploads are capped at 5 MB and stored under `storage/reservation-slips/{branchId}`; the existing `storage/.htaccess` blocks direct web access. Authorized reservation users view a slip through `reservation-slip.php`. A staff review can mark a slip verified or rejected, but only verified deposits can be confirmed or seated.

For `slip_api`, an adapter must return `status=verified`, `amount_match=true`, `receiver_match=true`, `duplicate=false`, and the exact numeric `amount`. Any incomplete or uncertain result remains in staff review; a definite rejection or mismatch is not accepted. The admin UI exposes API readiness but does not collect or store API secrets. Without the provider-specific adapter and environment credentials, this remains a manual-review flow and must not be described as automatic verification.
