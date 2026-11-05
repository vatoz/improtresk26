# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Website and registration system for **Improtřesk**, a small improv-theatre festival (about 100 participants, one admin). It is reused from year to year. The UI, content, DB data, comments and commit messages are mostly in **Czech**; keep user-facing text in Czech. `README.md` holds the original specification.

Stack: plain PHP (no framework) + MySQL/MariaDB, with AltoRouter, Twig 3, vlucas/phpdotenv, PHPMailer, and `4vplacek/qr-platba` for Czech QR payment codes. PSR-4 namespace `App\` → `src/`, and `src/helpers.php` is autoloaded. There are no tests, linter or build step.

## Commands

```bash
composer install
cp .env.example .env               # then fill in DB, SMTP, Fio token, secrets

php database/migrate.php           # apply pending migrations
php database/seed.php              # sample data (admin@improtresk.cz / password)
php database/setup.php             # migrate + seed in one go

php -S localhost:8000 -t public    # dev server (production uses Apache + public/.htaccess)

php bin/fio-sync.php               # pull Fio bank transactions into transaction_lists
php bin/send-test-mail.php         # SMTP check
php bin/generate-static.php        # static HTML mirror of public pages → _static/
```

## Architecture

- **Front controller:** `public/index.php` defines **all routes** (`'Controller#action'` strings) and calls `new App\Controllers\X($twig, $db)`. To add a page, add a route there, a controller method, and a template.
- **Bootstrap:** `src/bootstrap.php` loads `.env`, creates the global `$db` (PDO, exceptions, FETCH_ASSOC, real prepared statements) and `$twig`, registers the Twig filters `cz_nbsp` (non-breaking spaces after Czech one-letter prepositions) and `trimStartZero`, and starts the session. `bin/generate-static.php` repeats this setup without the session, so a new Twig filter has to be added in both files.
- **Controllers** (`src/Controllers`) extend `BaseController`, which provides `requireAuth()`, `requireAdmin()` (checks `$_SESSION['user']['role'] === 'admin'`) and `getSessionMessages()` for flash messages (`$_SESSION['success'|'error'|'info']`). POST handlers end with a redirect via `header('Location: …'); exit;`. CSRF uses `csrf_token($key)` / `csrf_validate($key, $token)` from `helpers.php`. `AdminController` is large (~1500 lines) and covers every `/admin/*` page.
- **Models** (`src/Models`) are thin static helpers that take a `PDO $db` (for example `Workshop::…($db, …)`). Many controllers also run raw SQL inline.
- **Templates:** `templates/base.twig` is the layout. `pages/*.twig` are full pages rendered by controllers and usually include a matching `sections/*.twig` partial. `inlines/` holds small reusable fragments and `emails/` holds mail templates. Editable text snippets live in the `static_blocks` DB table (fetched by name or prefix, such as `wizard*`) and are managed at `/admin/static-blocks`. Uploaded images go to `public/img/` (gitignored) and are matched to records by filename (`{prefix}{id}.jpg|png`; see `loadImages()`).

### Registration and payment flow

- **Registration pages** (there is no wizard): `/workshops` (`WorkshopController`) shows all workshops on one page grouped by timeslot (= workshop length); logged-in users register/unregister via AJAX POST to `/workshops/register`. `/tickets` (standard tickets grouped by `tickets.date`) and `/merch` live in `ShopController`. DětskýTřesk tickets (`tickets.type` child/adult/family) are added on `/children` – the "Vstupenky" ribbon expands an inline form, shown only to logged-in users, posting to `/children/add-to-cart`. `/cart` (`DashboardController::cart`, cart icon in the nav with count from the `cart_count` Twig global set in `bootstrap.php`; any action that changes the cart must call `BaseController::markCartChanged()` so the next page load animates the icon via `cartChanged()` in `public/js/app.js` – AJAX handlers call `window.cartChanged(newCount)` directly) lists all unpaid items with remove buttons (forms send `_back=cart`, see `BaseController::backUrl()`) and the total. The "Chci zaplatit" form POSTs to `/cart/pay`: unanswered required questions must be filled in right there before any payment (tickets, merch and workshops can be ordered without them – `/cart` is the only place they are enforced); the "Pošli mi připomínku mailem" checkbox sets `users.awaiting_payment` (= total, else 0), which triggers the reminder mail. Then `GET /cart/pay` shows the QR code and bank details. Registering for workshops requires `REGISTRATION_START` to have passed (admins bypass; `BaseController::isRegistrationOpen()`). Registrations have a priority (`registrations.priority`). Tickets and merch are stored as `purchases`.
- **Variable symbol = user id.** A single payment covers all of a user's items.
- `registrations.payment_status` enum: `pending, approved, paid, notpaid, upgradable, cancelled, refunded, skipped`. The migrations 017–019 and 035 explain what each status means. `cancelled` and `skipped` don't count toward capacity.
- Bank transactions come from the Fio API (`FioService`) into `transaction_lists`, and the raw XML is logged to `var/log/`. The admin pairs transactions with users and registrations at `/admin/pairing`. Some older auto-matching code is stale: `FastbuyService` assumes a different VS format, `UnpaidService` references a nonexistent `RollService`, and `AdminController::markPaid` starts with `die()`. Check before relying on any of these.

### Background jobs (pseudo-cron)

- There is no system cron requirement. `public/index.php` runs `CronService::run()` on about 2% of requests after the response is flushed. `/cron` (protected by `CRON_SECRET`) triggers it explicitly.
- Each job's last-run time is stored in the `settings` key/value table: mail queue every 60 s (4 mails per run), Fio sync every hour, payment reminders every 60 s.
- **Email is never sent inline.** Insert into `mail_queue` (see `MailQueue` model) with either a rendered `body` or a `template` name plus JSON `vars`. `MailService` renders `templates/emails/<template>` and sends via SMTP. Admins can also save reusable texts in `mail_templates`.

### Database migrations

- Plain SQL files in `database/migrations/NNN_description.sql`. The runner applies every file whose number is greater than `settings.db_version` and then updates that version. Files are never re-run, so add a new numbered file instead of editing an applied one.
- `database/SCHEMA.md` and `database/README.md` only describe the early tables; the migrations are the source of truth.

## Notes

- Config comes from `.env` through `$_ENV` or `env()`. See `.env.example` for the keys: `REGISTRATION_START`, payment IBAN, `HERO_SECRET`, `WA_*` group links, and others.
- `public/adminer.php`, `public/editor.php` and `public/tresk.php` are untracked local tools. Don't commit them.
- Do not commit code, i will review and test it.
