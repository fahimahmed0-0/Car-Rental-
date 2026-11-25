# DriveX Assam — Car Rental System (v2.0)

Modernized PHP 8.2 + MariaDB car rental platform featuring a clean **White / Blue / Black / Red / Light Blue** interface, transactional booking concurrency locks, an immutable loyalty token ledger, Razorpay + built-in Sandbox checkout, and a **3-Tier Resilient AI Engine** with deterministic offline fallbacks.

---

## Architecture Highlights

* **Clean Layered Backend (`src/`):**
  * `src/Core/`: Zero-dependency `.env` loader (`Env`), PDO connector (`Database`), CSRF manager (`Csrf`), secure session & rate limiter (`Session`, `Security`).
  * `src/Services/`: `AuthService` (Argon2/bcrypt + transparent MD5 auto-upgrade + 6-digit OTP resets), `BookingService` (`FOR UPDATE` row locks + auto-expiring 10-min checkout locks), `PricingService`, `TokenLedgerService` (immutable `tbltokenledger`), `PaymentService` (Razorpay + Sandbox QR/simulator), `MailService` (PHPMailer SMTP + `tblmailoutbox` fallback), `UploadService`.
  * `src/Services/AI/`: `AiOrchestrator` (**Tier 1 Cloud LLM** Gemini/OpenAI with circuit breaker → **Tier 2 Local Ollama** → **Tier 3 `DeterministicAiFallback`** PHP/SQL engine).
* **JSON REST API (`api/v1/`):** `vehicles.php`, `availability.php`, `bookings.php`, `payments.php`, `admin-analytics.php`, `ai.php`.
* **UI (`assets/css/drivex.css`):** Precompiled Tailwind CSS + Alpine.js + Chart.js v4.

---

## Quick Setup (XAMPP / Apache / MariaDB)

1. **Create `.env` from template:**
   ```bash
   cp .env.example .env
   ```
   Configure `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`. All other keys (`SMTP_*`, `RAZORPAY_*`, `AI_API_KEY`) are optional—built-in fallbacks handle local/offline mode automatically.

2. **Import database schema:**
   ```bash
   mysql -u root -p carrental < database/carrental.template.sql
   ```

3. **Run idempotent migrations & seed script (CLI only):**
   ```bash
   php database/migrate.php
   php database/seed.php
   ```
   *(If upgrading an older database, `migrate.php` safely adds missing tables/columns and migrates legacy token balances into `tbltokenledger`.)*

4. **Rebuild CSS (only needed if editing Tailwind templates):**
   ```bash
   npm install
   npm run build:css
   ```

5. **Run automated tests:**
   ```bash
   php tests/run.php
   ```

---

## Fallback Behavior (Zero Single Points of Failure)

| Subsystem | Primary Mode | Automatic Fallback |
| :--- | :--- | :--- |
| **AI Search, Assistant & Copilot** | Cloud LLM (`AI_PROVIDER=gemini` or `openai`) | Local Ollama (`AI_LOCAL_URL`) → `DeterministicAiFallback` (PHP regex/SQL engine) |
| **Payments** | Live Razorpay (`PAYMENT_MODE=razorpay`) | Built-in **Sandbox Gateway** (QR + UPI/Card/Wallet/Netbanking simulator) |
| **Email / OTPs** | PHPMailer SMTP (`SMTP_HOST` configured) | Queued in `tblmailoutbox` (inspectable in Admin → *Mail Outbox*) |
| **Vehicle Images** | Cloudinary (`CLOUDINARY_*`) or local disk (XAMPP) | External `https://` image URL input |

---

## Deploying to Vercel (`vercel.json` + `api/index.php`)

This repository includes [vercel.json](vercel.json), [.vercelignore](.vercelignore), [api/index.php](api/index.php), and `tblsessions` DB-backed sessions so all routes run through a single `vercel-php` serverless function:

1. **Provision an External Cloud MySQL Database** (Vercel has no built-in MySQL): e.g. **TiDB Serverless**, **Aiven MySQL**, or **Railway MySQL**.
2. **Initialize the Remote Database from your local terminal:**
   Set your Cloud MySQL credentials temporarily in `.env` (with `DB_PORT` and `DB_SSL=true` if required by your provider) and run:
   ```bash
   mysql -h YOUR_HOST -P YOUR_PORT -u YOUR_USER -p YOUR_DB < database/carrental.template.sql
   php database/migrate.php
   php database/seed.php
   ```
3. **Configure Vercel Project Environment Variables:**
   * `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_DRIVER=database`
   * `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_SSL=true`
   * Optional: `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET` (for admin image uploads on Vercel; `https://` URLs also work without Cloudinary)
   * Optional: `AI_PROVIDER`, `AI_API_KEY`, `RAZORPAY_KEY_ID`, `RAZORPAY_KEY_SECRET`, `SMTP_*`
4. **Deploy:** Connect the GitHub repo in Vercel and deploy (`vercel --prod` or auto-deploy on `main`).

---

## Security Notes

* Never commit `.env` or `.sql` database dumps (`.gitignore` and `.htaccess` block `src/`, `database/`, `storage/`, `tests/`, and `.env`).
* All state-changing customer and admin actions require `POST` + CSRF validation.
* `payment.php` loads `FinalAmount` strictly from the database and verifies booking ownership (`userEmail`).
