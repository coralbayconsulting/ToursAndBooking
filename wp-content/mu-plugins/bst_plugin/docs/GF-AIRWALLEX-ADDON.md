# Gravity Forms + Airwallex

## Goal

- **One-time payments** (no subscriptions in this integration).
- **Authorize** during validation so the entry is not finalized unless Airwallex confirms the PaymentIntent.
- **Embedded checkout** via Airwallex **Drop-in** (`@airwallex/components-sdk`) on the front end.

## What exists now (v0.2)

- **`BST_Airwallex_API`**
  - Login: `POST /api/v1/authentication/login` (Bearer token cached ~25 minutes).
  - `create_payment_intent()` → `POST /api/v1/pa/payment_intents/create` (amount in **minor units** per Airwallex rules).
  - `get_payment_intent()` → `GET /api/v1/pa/payment_intents/{id}`.
- **Global settings** — **Forms → Settings → Airwallex**: sandbox vs live credentials, optional webhook secrets (for future `callback()`).
- **Field: “Airwallex”** (under **Pricing Fields**): mounts the Drop-in UI + hidden PaymentIntent id.
- **Feed** — **Form → Settings → Airwallex**: payment amount, billing maps (from `GFPaymentAddOn`), etc.
- **Front end** — `includes/integrations/gf-airwallex/js/gf-airwallex.js` (ES module) loads the SDK from jsDelivr, creates the intent via AJAX (`bst_gf_airwallex_create_intent`), mounts Drop-in, fills the hidden field on `success`.
- **`authorize()`** — Retrieves the intent, requires `status === SUCCEEDED`, and checks amount vs Gravity Forms `submission_data` payment total.

## Cardholder name, ZIP / postal code, and validation

- **What the PaymentIntent “needs”** — The server creates an intent with amount/currency; **PAN, expiry, and CVC** are collected inside the **Airwallex Drop-in** (hosted fields / iframe). They are **not** posted to WordPress.
- **Name on card & postal / ZIP** — Not always mandatory for every region or scheme, but **strongly recommended** for **AVS**, **fraud scoring**, and a smoother **3DS** experience. The Drop-in can collect them via **`requiredBillingContactFields`** (see Airwallex [Drop-in options](https://www.airwallex.com/docs/js/payments/dropin)).
- **Default in this plugin** — The front end passes (via PHP + filter) roughly:
  - `methods: ['card']`
  - `requiredBillingContactFields: ['name', 'postalAddress']`  
  `postalAddress` follows Airwallex’s **ContactField** naming (billing postal / address; often includes ZIP/postal as part of that contact).
- **Customize** — Use the WordPress filter **`bst_gf_airwallex_dropin_options`** to change or remove those fields, or add **`shopper_name` / `shopper_email`** prefills if you map GF fields later.
- **Who validates what?**
  - **Inside Drop-in:** Airwallex’s UI validates card formatting, expiry, CVC, and required billing fields **before** confirm; failures surface through the element’s **`error`** event (shown in the status line next to the field).
  - **Gravity Forms:** Keep the **Airwallex** field **required** so the form cannot submit until the hidden PaymentIntent id is set (after a successful Drop-in **`success`**). Add ordinary GF fields (Name, Email, Address) if you need them on the entry **in addition to** what Drop-in collects.

## Form setup

1. Add **Product** (or other pricing) fields and a **Total** field (recommended so the order total is posted reliably).
2. Add the **Airwallex** field (place it where shoppers should pay).
3. **Form → Settings → Airwallex**: create a feed (**Products & Services**), map amount and billing fields.

Without a **Total** field, intent creation falls back to `GFCommon::get_product_submission()` when available; if totals are wrong, add a **Total** field.

## Requirements

- **HTTPS** in production; Airwallex.js loads over HTTPS.
- **Credentials** for the selected mode (sandbox vs live) in global settings.
- **Browser**: ES modules + dynamic `import()` (modern evergreen browsers).

## Next steps (optional)

- Webhooks: set `$_supports_callbacks = true`, implement `callback()`, verify signatures with the stored webhook secret.
- Subscriptions: not supported (`subscribe()` returns an error).
- Tune Drop-in remount/debounce when many pricing fields change.
- Hosted payment / redirect methods may need `return_url` handling beyond the current home URL default.

## References

- [GFPaymentAddOn](https://docs.gravityforms.com/gfpaymentaddon/)
- [Airwallex guest Drop-in](https://www.airwallex.com/docs/payments/online-payments/drop-in-element/guest-user-checkout)
- [Create PaymentIntent](https://www.airwallex.com/docs/api/payments/payment_intents/create)
- [Retrieve PaymentIntent](https://www.airwallex.com/docs/api/payments/payment_intents/retrieve)

## Security

- Do not commit API keys; use GF settings or environment variables.
- Amount for the PaymentIntent is computed server-side from the posted GF fields (same request as intent creation).
- `authorize()` re-checks intent status and amount against the submission.
