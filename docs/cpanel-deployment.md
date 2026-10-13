# SB ISPGM cPanel deployment

Deploy a tested release to a staging subdomain before inviting pilot users. Keep the Laravel application and personal data outside the domain's document root. The release package contains code and map reference layers; it does not contain the development database, resident uploads, credentials, or signed consent records.

## Hosting requirements

Use PHP 8.2 or newer with Composer support and the extensions checked by `php artisan sbispgm:deployment-check`. Match the web PHP version to the terminal PHP version. Enable GD for image/signature processing, PDO MySQL, cURL, DOM/XML, Fileinfo, Mbstring, OpenSSL, and ZIP. Enable Apache rewrite rules. Obtain a valid HTTPS certificate for the chosen domain. GPS and service workers require a secure browser context on real devices.

Ask the provider about terminal/SSH access, MySQL database limits, request/upload limits, writable private storage, SMTP delivery, and scheduled backups. If terminal access is unavailable, arrange for the provider to run the installation commands. Do not publish a web route or temporary PHP installer to run Artisan commands.

Local Ollama and the Python prediction service require runtimes on the same host. The cPanel environment template disables them. Database assistant responses remain available; migration estimates use the three-month moving average when sufficient source events exist. Predictions remain provisional until validated against verified history. Use a host that supports these runtimes if the defense requires demonstrating the local model online. Do not point loopback endpoints to the development PC or upload resident data to a cloud model.

## Prepare the release

Composer's platform setting resolves dependencies for PHP 8.2 even when the development terminal uses PHP 8.5. Do not bypass Composer's platform check. The web server and CLI must both meet the requirements; changing the terminal PATH alone does not change Apache's PHP runtime.

For this local Laragon installation, `scripts/fix-laragon-php.ps1` configures the project to use the existing PHP 8.5 runtime through FastCGI. It writes `C:/laragon/etc/apache2/sites-enabled/zz-capstone-php.conf`, checks Apache configuration, and restarts Apache. This avoids mixing PHP's newer libraries with the older Apache process. It preserves the original global PHP selection. Review the script and approve its external configuration changes before running it. This local repair is not part of cPanel installation.

Run the following on the development machine. The test command uses an in-memory SQLite database and enables the CLI extensions needed for tests.

```powershell
powershell.exe -NoProfile -File scripts/test-system.ps1
composer audit --locked
npm.cmd audit
python scripts/build-cpanel-release.py stage
composer install --working-dir=storage/app/cpanel-release/sbispgm --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
python scripts/build-cpanel-release.py zip
```

The ZIP is `storage/app/sbispgm-cpanel-release.zip`. Staging refuses to overwrite an existing release folder. Rename the previous staging folder before rebuilding. Keep the ZIP outside publicly served folders.

## Install on cPanel

1. In cPanel, create a dedicated MySQL database and database user with access only to that database. Record the full cPanel-prefixed database/user names. Create the HTTPS domain or staging subdomain.
2. Back up any existing site, database, and private uploads before replacing files. Extract the ZIP into a private staging directory under the account home. Review the paths before moving files into place.
3. Place `sbispgm` outside the web root, for example `/home/ACCOUNT/sbispgm`. Put only the contents of the package's `public_html` directory in the domain's document root. Its `index.php` expects the private application in `../sbispgm`. For a different relative layout, adjust its three private paths. Keep `$app->usePublicPath(__DIR__)` so assets and frontend manifests resolve in the actual public directory.
4. For CLI commands, set `APP_PUBLIC_PATH=/home/ACCOUNT/public_html` in the server environment file. If the domain uses a different document root, use that absolute path instead. Confirm that the web server cannot expose `/sbispgm`, `.env`, `vendor`, `resources`, or private storage.
5. Copy the private `.env.production.example` to `.env`. Fill the actual HTTPS root domain, database credentials, SMTP account, sender address, and a randomly generated barangay registration code of at least 24 characters. Keep debug disabled and secure cookies enabled. Set the registration code privately; do not use the development defaults.
6. Grant the account/web process write access to `storage` and `bootstrap/cache` using the host's ownership and permission settings. Avoid world-writable permissions. Configure upload limits sufficient for the application's allowed receipt/signature files. Keep the private `storage/app` directory outside the public root; never link it wholesale into `public_html`.

Run in the private application directory, using the selected PHP binary:

```sh
php artisan package:discover
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan sbispgm:create-admin
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan sbispgm:deployment-check
```

`key:generate` is for a new installation only. Preserve the existing key when moving an existing database or upgrading a deployment. The production seeder initializes the 29 barangays without demo users. The administrator command asks for a private password and refuses to overwrite an existing account. Register secretary accounts using the private access code, then approve and assign them through the municipal account.

For upgrades, preserve `.env`, the application key, database, and private uploads. Enter maintenance mode with `php artisan down`, install the new release, apply forward migrations, regenerate caches, verify checks, then use `php artisan up`. Do not run `migrate:fresh` or automatically roll back migrations on resident data. Review migration reversals before using them. Restore a tested backup if a database change cannot safely be reversed.

## Confirm the hosted system

Record browser, device, tester role, expected/actual result, and evidence for each check. Passing automated tests does not establish host readiness.

| Check | Required result |
| --- | --- |
| Private URLs | Requests to `/.env`, `/composer.json`, `/vendor/autoload.php`, `/storage/app`, and private application paths return 403/404 without revealing content. |
| Session and approval | Correct login works; bad login fails; pending/rejected accounts cannot access operational data; logout prevents access on refresh. |
| Role and barangay boundaries | Residents see their own requests; secretaries modify only their assigned barangay; municipal users retain intended monitoring permissions. |
| RBI lifecycle | Save, edit, consolidate, submit, sign, and download synthetic household/monthly reports without duplicates or lost members. |
| Population and migration reports | Compare sampled counts with synthetic source rows; open exported PDF/Excel files and confirm period and jurisdiction. |
| GIS and offline capture | On an HTTPS phone, capture GPS, verify its location, save disconnected, reconnect, sync, retry without duplicates, and clear local data after confirming records reached the server. |
| Device privacy | Denied login refresh clears downloaded names; authorized offline use is on a controlled, locked device. Confirm clearing local data warns about unsynced records. |
| Document requests and payments | Submit and process requests. Use synthetic payment evidence; proof stays pending until authorized staff verify it. Free/disabled-payment paths work. Do not require real payments during the pilot. |
| Email | A real test account receives a password-reset link with the correct HTTPS domain; the token works once and expires as configured. |
| Backup and restore | Restore the database and private uploads into an isolated staging copy; reconcile record counts and open sampled private files. Keep the key securely with the backup. |
| Load and compatibility | Test expected simultaneous users, slow connections, phone/desktop layouts, printing, and timeout behavior. Record actual results. |

Use daily host backups plus a separately secured copy according to the institution's retention policy. Back up the database, private uploads, and application key together. Keep backups outside the public root; restrict access and prove restoration before live records are entered.

## Pilot authorization

Obtain the host's written permission and participants' voluntary consent before the pilot. Explain tasks, time, recorded feedback, privacy handling, and withdrawal. Use synthetic accounts and records during orientation. Keep signed permission/consent forms separate from evaluation responses. Use participant codes in results and redact resident information in defense screenshots. Permission and consent must be obtained from the actual host and people; deployment cannot grant them.

## Sources

The framework upgrade preserves the existing project structure according to [Laravel's upgrade guide](https://laravel.com/docs/11.x/upgrade). Server setup and production caching follow [Laravel deployment guidance](https://laravel.com/docs/12.x/deployment). Consent and participant information should be reviewed with the host against the [National Privacy Commission guidelines on consent](https://privacy.gov.ph/wp-content/uploads/2023/11/NPC-Circular-No.-2023-04_Guidelines-on-Consent_07Nov2023.pdf).
