# SB ISPGM internal release verification

Recorded on 10 October 2026 for the cPanel release. These results describe the local code and package, not an installed production host or completed pilot evaluation.

## Results

| Verification | Result |
| --- | --- |
| PHP application suite | 265 tests, 3,735 assertions passed using PHP 8.5 with in-memory SQLite, GD, and PDO SQLite. |
| Offline household JavaScript suite | 15 tests passed. |
| Python prediction API suite | 4 tests passed using the project's ML virtual environment. |
| Frontend production build | Passed with Vite 8 and Laravel Vite plugin 3. |
| Dependency audits | Initial Composer and npm audits reported zero known advisories after the framework update. A fresh Composer audit after the PHP compatibility correction was blocked by DNS failure for Packagist; rerun it with working network access before deployment. |
| Laravel caches | Route and view compilation passed. |
| Production package | Production dependencies installed without development packages; public frontend manifest resolved. |
| Package route smoke tests | Home, login, offline capture, and offline fallback returned 200; the Laravel request to `/.env` returned 404. Actual web-server file exposure still needs checking. |

Local evidence is in `storage/logs/capstone-final-tests.txt`, `capstone-phpunit.xml`, `capstone-offline-tests.txt`, `capstone-prediction-tests.txt`, and `capstone-release-install.txt`. Logs are local artifacts and are excluded from the release.

## Changes supporting deployment

Updated the framework and vulnerable dependency versions; strengthened private-response cache headers, registration scope, and password-change session/token handling; added production configuration, administrator creation, and a read-only deployment checker. Production seeding excludes demo accounts. Restored the registry activity table and added controlled-device offline data clearing and barangay assignment protection.

The local Apache runtime mismatch was corrected with PHP 8.5 FastCGI for this project. A direct module switch was reverted after a cURL/Apache DLL conflict. Dependencies are resolved with Composer's PHP 8.2 platform setting so a newer terminal does not silently raise the minimum PHP requirement.

The optional development formatter is locked to the previously cached Pint 1.20.0 because newer archive downloads failed. It is excluded from the production package. Symfony dependencies now use the PHP 8.2-compatible versions from the corrected lock file; Composer regenerated the platform check without bypassing it.

Existing navigation assertions were updated for the current RBI history, resident forms, approvals, and report labels. Passing the revised tests does not establish that every manuscript requirement is implemented; untested workflows require recorded manual checks.

The cPanel archive separates `public_html` from the private application and excludes the local environment, database, uploads, logs, tests, and development dependencies. Shared-hosting configuration disables the optional local AI and Python services; the documented application fallbacks remain available.

## Required before pilot access

Follow [the installation and hosted verification guide](cpanel-deployment.md). Configure the real domain, HTTPS, MySQL, SMTP, application key, registration code, and administrator. Run `php artisan sbispgm:deployment-check` on the host and resolve failures. The development configuration intentionally does not pass production checks.

Verify MySQL migrations and workflows on staging, phone GPS and offline synchronization over HTTPS, real password-reset delivery, role boundaries, document/payment handling, and a backup restoration. Automated SQLite tests do not prove MySQL or hosting compatibility. Permission and informed consent must be obtained from the actual institution and participants before the pilot.
