# Customer sign-in by mobile number + OTP

Customers do **not** use passwords. They sign in (and register) with their mobile
number and a 6-digit one-time password (OTP) sent by SMS. Administrators still
sign in at `/admin/login` with email + password — unchanged.

## How it works

| Step | Sign-in (`/login`) | Registration (`/register`) |
|------|--------------------|----------------------------|
| 1 | Customer enters their mobile number → **Send OTP** | Name, email, mobile, plan → **Send OTP** (no account yet) |
| 2 | Enters the OTP → **Verify OTP & Login** | Enters the OTP → account + first scheme created, signed in |

* Only **Active** accounts can sign in. Inactive and Blocked customers are told so
  after entering a correct OTP, and no session is created.
* The response to step 1 is the same whether or not the number is registered
  (no SMS is sent to unregistered numbers).
* The number being verified is stored server-side in the session; the browser
  only submits the code.

## Security policy

| Rule | Value |
|------|-------|
| OTP | 6 digits, `random_int` (CSPRNG) |
| Validity | 5 minutes |
| Storage | HMAC-SHA256 (keyed with `APP_KEY`, bound to mobile + purpose) — the code itself is never stored or logged |
| Wrong attempts | 5 per OTP, then the OTP is locked |
| New OTP | invalidates every earlier unused OTP for that number |
| After success | consumed atomically — cannot be reused |
| Resend cooldown | 60 seconds per number |
| Send limits | 5 per number per hour; 20 per IP per hour |
| Verify limit | 30 attempts per IP per 10 minutes (plus route throttles) |
| SMS failure | the OTP is invalidated and the customer is told it was **not** sent |

Settings live in `config/sms.php`.

## Configure MSG91 (production)

1. **DLT registration (India).** Register your entity and an SMS header (sender ID)
   on a DLT portal, and get an OTP content template approved, e.g.
   `{#var#} is your Sri Sindhu Jewellery OTP. It is valid for 5 minutes. Do not share it with anyone.`
2. **MSG91.** In the MSG91 dashboard add the DLT entity, sender ID and the approved
   template (as a Flow / SMS template). Note the template id, and the variable name
   the template uses for the OTP (`otp` by default here).
3. **Auth key.** MSG91 → API → create an auth key (whitelist your server IP if required).
4. **Environment** (`.env` on the server — never commit real keys):

   ```dotenv
   SMS_DRIVER=msg91
   SMS_MSG91_AUTH_KEY=your-auth-key
   SMS_MSG91_TEMPLATE_ID=your-template-id
   SMS_MSG91_OTP_VARIABLE=otp
   ```

5. `php artisan config:clear` (or `config:cache` in production).

The app calls `POST https://control.msg91.com/api/v5/flow` with the `authkey` header,
`template_id`, and `recipients: [{ mobiles: "91XXXXXXXXXX", otp: "123456" }]`, and treats
the OTP as sent only when MSG91 returns HTTP 2xx with `"type": "success"`.

> **Not yet verified against the live MSG91 service.** The request format is covered
> by automated tests with a faked HTTP response only. Send a real OTP to a test number
> after configuring production credentials, and confirm delivery before going live.

## Local development

```dotenv
APP_ENV=local
SMS_DRIVER=development
```

No SMS is sent; the OTP appears on the OTP screen in a box marked
**Development only — no SMS was sent**. This driver is refused unless `APP_ENV` is
`local` or `testing` — anywhere else the app behaves as if no provider is configured.

With `SMS_DRIVER` empty, OTPs cannot be sent and customers see
"We could not send the OTP right now".

## Existing customers

Existing accounts are unchanged. A customer can sign in only if their account has a
mobile number — admins can add or correct it on **Edit Customer**. Existing password
hashes are left in the database (not used by the customer login).
