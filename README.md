# GSAA Global — Backend (PHP + MySQL)

Production-ready backend for the GSAA Global membership / MLM platform:
unified admin + member login, 3-position matrix placement with spillover,
sponsor/parent-level income, one-time level-completion reward+boost bonuses,
KYC, services, QR + Razorpay payments, withdrawals, and an admin panel to run
all of it.

This is **code only** — no hosting, FTP or database credentials are
included. You (or whoever manages hosting) must create the database and
upload these files yourselves. Nothing here talks to a live server until you
fill in `config.php`.

---

## 1. What's inside

```
backend/
├── schema.sql              ← run this once to create all tables (MySQL)
├── config.php               ← EDIT THIS: DB + Razorpay + app secrets
├── .htaccess                 ← blocks direct access to config.php, .sql, .log
├── login.php / logout.php / register.php   ← public pages
├── lib/                      ← shared PHP (db, auth, csrf, MLM/income engine, razorpay)
├── admin/                    ← admin panel (13 pages)
├── member/                   ← member panel (8 pages)
├── api/                      ← Razorpay order-create / verify / webhook endpoints
├── assets/style.css          ← shared stylesheet
└── uploads/                  ← KYC photos, QR code image, service screenshots (writable)
```

There is **no `tests/` folder and no `backend_test/` folder** in this zip —
those were local-only tools I used to test the code before sending it to
you and are not meant for production.

---

## 2. First-time setup (do this in order)

### Step 1 — Create the database

In your hosting control panel (cPanel/Plesk/etc.) or via SSH:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < schema.sql
```

This creates all 14 tables and seeds:
- One admin login — username `admin`, password `admin123`
- One root member — member code `7284519`, password `root123` (this is the
  top of the matrix tree; every public registration eventually places
  somewhere under this member)
- Two packages (`starter` ₹49, `premium` ₹1555 — editable from the admin
  panel under **Income setup**, including prices)
- Default level-reward table (levels 1–7)
- Default withdrawal settings (5% admin charge, 2% TDS, ₹100 minimum)

**⚠️ Change both passwords immediately after your first login** — see the
go-live checklist below.

### Step 2 — Edit `config.php`

Open `config.php` and fill in the `CHANGE_ME` placeholders:

```php
'DB_DRIVER'   => 'mysql',
'DB_HOST'     => 'localhost',        // your DB host
'DB_NAME'     => 'your_db_name',
'DB_USER'     => 'your_db_user',
'DB_PASS'     => 'your_db_password',

'SITE_URL'    => 'https://gsaaglobal.com/gsaanew',  // no trailing slash

'RAZORPAY_KEY_ID'        => 'CHANGE_ME',  // from Razorpay dashboard
'RAZORPAY_KEY_SECRET'    => 'CHANGE_ME',
'RAZORPAY_WEBHOOK_SECRET'=> 'CHANGE_ME',

'APP_SECRET'  => 'CHANGE_ME',   // random 64-char hex string, see below
'APP_DEBUG'   => true,          // ⚠️ set to false before going live
```

Generate a strong `APP_SECRET` (used for CSRF/session hardening) by running
this once on any machine with PHP installed:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Paste the output in as `APP_SECRET`.

### Step 3 — Razorpay keys & webhook

1. Log in to the [Razorpay dashboard](https://dashboard.razorpay.com/).
2. Go to **Settings → API Keys** and generate a Key ID + Key Secret (use
   **Test mode** keys first to try everything end-to-end before going live).
   Paste both into `config.php`.
3. Go to **Settings → Webhooks → Add New Webhook**:
   - Webhook URL: `https://yourdomain.com/path-to-backend/api/razorpay-webhook.php`
   - Active events: tick **`payment.captured`**
   - Set a secret — paste that same secret into `config.php` as
     `RAZORPAY_WEBHOOK_SECRET`.

The webhook is what actually activates a member's package and credits
income — it is the source of truth even if the customer closes the browser
tab right after paying, so don't skip it.

### Step 4 — Upload the files

Upload the entire `backend/` folder's contents to your hosting (e.g. into
`gsaanew/` next to your existing PHP site, or wherever you want the new
system to live) via FTP / your hosting file manager.

Make sure the `uploads/` folder (and its `kyc/`, `qr/`, `services/`
subfolders) is **writable** by PHP (normally permissions `755` or `775`
depending on your host; ask your hosting support if uploads fail).

### Step 5 — Test it

Visit `https://yourdomain.com/path-to-backend/login.php` and log in with
the admin or root-member credentials from Step 1 to confirm the database
connection works. Try registering a new test member via
`register.php?ref=7284519`, activating a test package with a **Razorpay
test-mode** card, and checking that income shows up under the root
member's **Income** tab.

---

## 3. Go-live checklist

Before telling the client this is live, do all of these:

- [ ] Change the default `admin` password (admin panel → your profile)
- [ ] Change the default root-member (`7284519`) password
- [ ] Switch Razorpay from Test keys to **Live** keys in `config.php`, and
      add a Live-mode webhook (Step 3 above) pointing at the same URL
- [ ] Set `'APP_DEBUG' => false` in `config.php` (stops PHP errors from
      being shown to visitors)
- [ ] Confirm the site loads over **HTTPS** — login cookies are marked
      "secure" automatically once Razorpay/your domain is on HTTPS, and
      won't work correctly over plain HTTP
- [ ] Confirm `.htaccess` support (`AllowOverride All`) is enabled on your
      host — this is what stops people from browsing directly to
      `config.php`, `schema.sql`, or executing a file someone uploaded as
      "photo.php" inside `uploads/`. Most shared hosting (cPanel/Apache)
      has this on by default; if you're on **nginx** instead of Apache,
      `.htaccess` files are ignored — ask your host to apply equivalent
      rules, or let me know and I'll write the nginx version.
- [ ] Create a MySQL user scoped only to this one database (not a
      root/admin MySQL account) for `DB_USER`/`DB_PASS`
- [ ] Take a database backup before the first real member signs up, and
      set up regular backups going forward (ask your host how, or use a
      cron + `mysqldump`)

---

## 4. How the system works (quick reference)

- **One login for everyone** — `login.php` checks both admins and members
  by member code or phone number, then sends each to their own panel.
- **Registration** — `register.php?ref=MEMBER_CODE` captures the sponsor.
  A member is placed into the matrix tree under their sponsor (or the next
  open slot found via spillover if the sponsor's 3 slots are full) only once
  they activate a paid package — matches the behavior shown in the original
  demo.
- **Income** — on activation: sponsor gets a one-time "direct income"; every
  *premium* ancestor up the matrix tree gets "parent/level income" per the
  package's configured levels. When a member's own subtree under them hits
  3, 9, 27, 81… IDs at a given depth, they get a one-time reward + boost
  bonus (configurable per level in **Income setup**).
- **Wallet balance** is always calculated live from the income ledger
  (never a cached number), so it can't drift out of sync.
- **Withdrawals** — admin can batch-process all eligible wallets, or a
  member can request manually from their panel; both compute
  gross → minus admin charge % → minus TDS % → net, and this is **not**
  wired to any real bank-transfer API (matches the original design — the
  app records a withdrawal and marks it processed; actually sending the
  money is a manual step on your side, exactly like the reference design
  stated).
- **QR payments** — an alternative to Razorpay: admin sets a company
  UPI ID / QR image in **QR payments**, members upload a screenshot + UTR
  after paying manually, admin approves and income is credited then.
- **Services** — admin lists services (with a payout %, e.g. "Mobile
  Recharge pays 2%"); members submit proof (screenshot + UTR) of using the
  service, admin approves, member gets their service income credited.

---

## 5. Things to settle with the client (not purely technical)

I made reasonable default choices where the client's exact business rules
weren't specified in what was shown/approved. These are easy to change (all
from **admin → Income setup / Withdrawal settings**, no code changes
needed) but worth confirming with the client before going live:

- Exact ₹ amounts for sponsor income, matrix/parent income, and the
  level-reward + boost bonuses (currently: sponsor ₹200, matrix parent ₹50
  up to level 10 for the Premium package — seeded values, easy to edit).
- Admin charge % and TDS % on withdrawals (currently 5% / 2%).
- Minimum withdrawal amount (currently ₹100) and whether withdrawals run
  daily or on another cycle.

---

## 6. If something doesn't work

Most issues after deployment are one of:
1. **Wrong DB credentials** in `config.php` → check your hosting's DB
   connection details again.
2. **Blank white page** → temporarily set `'APP_DEBUG' => true` in
   `config.php`, reload, read the error, then set it back to `false`.
3. **Uploads failing (KYC/QR/service screenshots)** → check that
   `uploads/`, `uploads/kyc/`, `uploads/qr/`, `uploads/services/` are
   writable by PHP on your host.
4. **Razorpay button does nothing / payment not confirming** → re-check the
   Key ID/Secret and that the webhook URL in the Razorpay dashboard exactly
   matches your live `api/razorpay-webhook.php` URL.
