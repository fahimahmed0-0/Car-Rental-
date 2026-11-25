# DriveX Assam - Car Rental Platform

Full-stack car rental platform built with PHP 8.2, PDO (MariaDB / embedded SQLite demo mode), Tailwind CSS, and Alpine.js. Supports real-time booking date locking, loyalty token redemptions, online/test payment checkout, admin analytics, and natural-language fleet search.

## Architecture Overview

```text
.
├── api/                  # Vercel serverless front controller & JSON endpoints
├── admin/                # Admin portal (dashboard, bookings, fleet, tokens, CMS, mail outbox)
├── assets/css/           # Tailwind CSS input & compiled stylesheet
├── database/             # Schema SQL template, migrations, and seed script
├── includes/             # Shared layout templates, auth handlers, modals, assistant widget
├── src/
│   ├── Api/              # JSON API handlers (vehicles, bookings, availability, payments, assistant, analytics)
│   ├── Core/             # Env, Database, SqliteCompatPdo, DemoDatabase, Session/CookieSession, CSRF, Security
│   ├── Services/         # AuthService, BookingService, PricingService, TokenLedgerService, PaymentService, MailService, UploadService
│   └── Services/AI/      # FleetAssistantService & RuleEngine (optional Gemini / OpenAI / Ollama with built-in PHP engine)
├── tests/                # CLI unit & integration test suite
└── vercel.json           # Serverless routing and static asset configuration
```

## Key Features

- **Transactional Booking & Date Locks:** Overlap validation checks active reservations using row-level locking (`FOR UPDATE` on MariaDB) and auto-expiring 10-minute pending checkout locks.
- **Loyalty Token Ledger:** Atomic ledger (`tbltokenledger`) awards 5 tokens per completed trip, validates minimum 15-token redemptions (`5 tokens = ₹100`), and automatically refunds redeemed tokens on cancellation or expiry.
- **Checkout & Receipt Pipeline:** Server-computed booking totals with Razorpay order/signature verification and an automatic local/test gateway (`UPI QR`, card, wallet, netbanking) plus idempotency key deduplication.
- **Smart Fleet Search & Assistant:** Parses natural-language vehicle queries (seats, fuel type, transmission, brand, max daily budget, hill/city use) using an optional LLM provider (`AI_API_KEY` or `AI_LOCAL_URL`) or the built-in PHP rule engine.
- **Security Controls:** Argon2/bcrypt password verification (with seamless legacy hash upgrade), 6-digit hashed OTP password resets, CSRF tokens on state-changing requests, DB-backed rate limiting, MIME/magic-byte upload validation, and CMS HTML sanitization.

## Local Setup (XAMPP / PHP + MariaDB)

1. Configure environment variables:
   ```bash
   cp .env.example .env
   ```
   Update `DB_HOST`, `DB_USER`, `DB_PASS`, and `DB_NAME` in `.env`. SMTP, Razorpay, Cloudinary, and AI keys are optional.

2. Initialize database and sample data:
   ```bash
   mysql -u root < database/carrental.template.sql
   php database/migrate.php
   php database/seed.php
   ```

3. Build CSS and run tests:
   ```bash
   npm install
   npm run build:css
   php tests/run.php
   ```

## Deploying to Vercel

### Preview Mode (Embedded SQLite)
Connect the repository to Vercel and deploy with no database configuration required. When `VERCEL` is set without an external `DB_HOST`, the application automatically boots [DemoDatabase](src/Core/DemoDatabase.php) in `/tmp` using [CookieSessionHandler](src/Core/CookieSessionHandler.php) and displays **Demo Sign In** shortcuts on the customer and admin login forms.

> Note: `/tmp` SQLite data resets when serverless containers recycle. Set a random `APP_KEY` environment variable if you want session cookie signatures shared across instances.

### Production Database (Cloud MySQL)
To persist live bookings across users and deployments:
1. Run `database/carrental.template.sql`, `php database/migrate.php`, and `php database/seed.php` against your Cloud MySQL database (TiDB, Aiven, Railway, etc.).
2. Configure `APP_ENV=production`, `SESSION_DRIVER=database`, `APP_KEY`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, and `DB_SSL=true` in Vercel Environment Variables.
3. Optionally set `CLOUDINARY_*`, `RAZORPAY_*`, `AI_*`, and `SMTP_*` variables.
