# NORMI deployment checklist

A short, ordered checklist for putting NORMI on a real server. The details behind each step are in `SYSTEM_DOCUMENTATION.md`; open items are in `docs/BUG_LOG.md`. Tick every box before the first real student uses it.

## 1. Before you start (decisions, not code)

- [ ] The Data Protection Officer has approved the student privacy notice text. Replace the placeholder in `lang/en/student_device.php`.
- [ ] The guardian-consent procedure for students under 18 is agreed (it happens outside the system).
- [ ] `AI_PROVIDER` is chosen: `rule_based` (nothing leaves the server), or `claude` only after the DPO approves sending scores and thresholds (no identity) to Anthropic.
- [ ] An **APP_KEY custodian** is named, with where the offline copy is kept.

## 2. Server and network

- [ ] **HTTPS** for the whole site, with a certificate the staff and student PCs trust. It's required for `SESSION_SECURE_COOKIE=true`, and it keeps students' answers off the network in clear text.
- [ ] Each student PC has a fixed IP or DHCP reservation, and its address is in `REMOTE_ASSESSMENT_ALLOWED_IPS`.
- [ ] A firewall rule allows the web port only from the school network. There's no Wi-Fi client isolation between the student PC and the server.
- [ ] PHP 8.2+ with the `intl`, `gd`, `pdo_mysql` and `openssl` extensions; MySQL 8; Composer; Node (build only).

## 3. Install

1. `git clone`, then `composer install --no-dev --optimize-autoloader`.
2. `cp .env.production.example .env`, then fill in the blanks: DB user, URLs, allowlist, mail. **Never commit `.env`.**
3. `php artisan key:generate`, then **immediately** store the new `APP_KEY` with the custodian (step 1).
4. `npm ci && npm run build`. Make sure `public/hot` does **not** exist; never use `npm run dev` on the server.
5. `php artisan migrate --force`.
6. `php artisan db:seed --force`, with `ADMIN_DEFAULT_PASSWORD` set for this one run only. Then remove it from `.env`.
7. `php artisan storage:link` (profile photos).
8. `php artisan config:cache && php artisan route:cache && php artisan view:cache`. Re-run after any `.env` change.
9. Log in as the seeded Psychometrician and **change the password at once**.

## 4. Scheduler (expired student-device drafts)

The prune job is registered in `routes/console.php`; something must call it every minute.

- **Windows Task Scheduler** (one line, administrator Command Prompt):
  `schtasks /Create /TN "NORMI scheduler" /SC MINUTE /MO 1 /TR "cmd /c cd /d C:\path\to\Mycapstone && php artisan schedule:run" /F`
- **Linux cron:** `* * * * * cd /path/to/Mycapstone && php artisan schedule:run >> /dev/null 2>&1`
- The LAN launcher (`start-normi.ps1`) doesn't start it. Use the task above, or keep `php artisan schedule:work` running in a second window.

## 5. Backups (and the key)

- [ ] A nightly `mysqldump` of the database to an **encrypted** location (BitLocker drive, or a 7-Zip AES archive), with a few days kept.
- [ ] The APP_KEY is stored **separately** from the backups. A backup without its key can't decrypt scores, answers or counseling notes.
- [ ] A **restore test** into a scratch database, then opening one assessment and one counseling session to confirm they decrypt. Repeat after any key change.
- [ ] Old unencrypted dumps are deleted or moved into the encrypted store.

## 6. Logs

- [ ] `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning` (as in `.env.production.example`).
- [ ] `storage/logs` is readable only by the web server account and the administrators.

## 7. Smoke test (with a test student, then delete them)

- [ ] Staff login, the logout confirmation, and the single session (a second browser is refused).
- [ ] New Assessment: the code is shown on the live page with the HTTPS address, never 127.0.0.1.
- [ ] On the student PC: `/s` → code → privacy notice → details → 21 answers → Done; Submit → Step 3 → Confirm & Save.
- [ ] From a PC **not** on the allowlist, `/s` shows only "Not available".
- [ ] A Severe Depression result notifies the Guidance Counselor (and never the Psychometrician).
- [ ] The printed report and PDF open.
- [ ] `APP_DEBUG=false`: a wrong URL shows a plain 404, never a stack trace.
