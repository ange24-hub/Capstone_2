# Hostinger VPS deployment with Dokploy

This repository includes a production `Dockerfile`, `docker-compose.yml`, and `.env.dokploy.example`. Use a **Hostinger VPS** with Dokploy, rather than a shared hosting account. Hostinger provides an Ubuntu template with Dokploy preinstalled; see [Hostinger's setup guide](https://support.hostinger.com/en/articles/9822596-how-to-use-the-dokploy-vps-template).

The stack runs PHP 8.3 with Apache on container port **80** and MySQL 8.4. Composer installs locked production dependencies and Node 24 builds the locked Vite assets. Node and Composer are absent from the final image. Startup rebuilds Laravel caches from runtime environment variables. Logs go to Dokploy; Docker rotates the log files.

## 1. Prepare the server and repository

1. Open Dokploy on the VPS and configure your administrator account and HTTPS for the Dokploy dashboard.
2. Point your application domain's DNS A record to the VPS IP. Add an AAAA record only if IPv6 is configured on the VPS.
3. Push these deployment files and the application to your Git repository. Keep real `.env` files, credentials, uploads, and database dumps out of Git. `.dockerignore` excludes local storage, browser profiles, logs, development dependencies, Android files, and secrets from Docker builds. Uploads and the development database are never copied into the image.
4. Run the existing local checks before releasing:

   ```powershell
   powershell.exe -NoProfile -File scripts/test-system.ps1
   ```

## 2. Create the Dokploy Compose service

1. Create a project and add a **Docker Compose** service.
2. Select your Git provider, repository, and release branch. Use repository root as the build context and `docker-compose.yml` as the Compose path.
3. Select **Docker Compose**, not Docker Stack; this configuration builds the image on the server.
4. Enable **Isolated Deployments** so Dokploy manages a network for this deployment. Keep the declared `backend` network connecting `app` and `database`. In **Preview Compose**, verify both services retain that shared network and `app` also has the network used by Traefik. Never expose the database through a domain or publish port 3306.
5. In **Environment**, paste `.env.dokploy.example` and fill every empty value. The Compose file explicitly passes application variables to `app`; the MySQL root password goes only to `database`. Environment values are not Docker build arguments.

Dokploy's [Compose documentation](https://docs.dokploy.com/docs/core/docker-compose) explains environment interpolation and volume persistence. Its [domain documentation](https://docs.dokploy.com/docs/core/docker-compose/domains) describes domain routing and isolated deployment networks.

### Required values

| Variable | Value |
| --- | --- |
| `APP_URL` | Your actual HTTPS domain root, for example `https://portal.your-domain.com`. |
| `APP_KEY` | A permanent Laravel encryption key. Generate once using the command below; retain the existing key when moving an existing installation. |
| `DB_DATABASE`, `DB_USERNAME` | Dedicated database and non-root application user; defaults are `sbispgm`. |
| `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD` | Different strong passwords. |
| `BARANGAY_REGISTRATION_CODE`, `MUNICIPAL_LGU_REGISTRATION_CODE` | Different private random values, each at least 24 characters. |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Your SMTP account and real sender address for password resets. |

Generate a new installation's key on your local machine without editing the local `.env`:

```powershell
php artisan key:generate --show
```

Alternatively, after building the image on a Docker host:

```sh
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
```

For the alternative command, Compose still requires its environment values to be filled; use a temporary nonempty `APP_KEY` for that command, then replace it with the generated key before starting the application. Never generate a new key on every deploy. Preserve it with your backups.

Use SMTP port 587 with `MAIL_ENCRYPTION=tls`, or your provider's supplied settings. In Dokploy environment text, quote values containing special characters; single quotes preserve literal dollar signs. When using the shell directly, add `--env-file .env.dokploy` before the Compose subcommand so the Laragon `.env` is not used. No root password is sent to the web container.

## 3. Configure the HTTPS domain and deploy

In the service's **Domains** tab, add your domain with service **app**, container port **80**, path **/**, HTTPS enabled, and a Let's Encrypt certificate. Dokploy supplies the Traefik routing labels. The Compose file uses `expose`, so it does not bind the application or database to public host ports.

`TRUSTED_PROXIES=*` lets Laravel recognize the HTTPS scheme and client address forwarded by Dokploy. Keep the container reachable only through the proxy. If your deployment has fixed proxy addresses, replace `*` with comma-separated IPs or CIDR ranges. Local development retains its default proxy behavior unless this variable is configured.

Leave `RUN_MIGRATIONS=false` for the first deployment and deploy. The MySQL health check waits for the application account to be usable. The application `/up` endpoint checks Laravel and database connectivity without opening a browser session or exposing diagnostics; it does not assert that migrations or administrator setup have finished.

## 4. Initialize a new database

Open the **app** container terminal in Dokploy and run:

```sh
gosu www-data php artisan migrate --force
gosu www-data php artisan db:seed --force
gosu www-data php artisan sbispgm:create-admin
gosu www-data php artisan sbispgm:deployment-check
```

The existing production seeder initializes the 29 barangays and skips demo accounts. Administrator creation asks privately for the initial password. Run the full deployment check after initialization; it checks migrations, SMTP configuration, the administrator, frontend assets, storage permissions, and production settings. Startup never seeds or creates an administrator automatically.

Uploads, receipts, signatures, sessions, and file caches live in the `app_storage` volume mounted at `/var/www/html/storage`. Only `storage/app/public` is linked into the public directory. Private files remain available through the app's authorized download controllers. PHP execution in public uploads is denied.

The application currently uses synchronous queues and has no scheduled jobs, so this deployment does not require a queue worker or scheduler.

## 5. Move an existing installation

For existing records, transfer a reviewed MySQL dump and the matching contents of `storage/app` privately. Keep the source `APP_KEY` and approved document fee values. Put the application in maintenance mode while migrating data and files; do not run the fresh-install seed/admin workflow over an imported installation without reviewing its existing accounts.

A full phpMyAdmin export with `CREATE TABLE` statements requires an empty target database. Importing it into the already migrated/seeded database fails with "table already exists". Back up the hosted database first. Prefer restoring into a separate staging database, checking record counts, granting the application user access, and then switching `DB_DATABASE` in Dokploy and redeploying. Retain the previous database for rollback. Do not delete the database volume to make room for an import.

To prepare a private transfer package from a local phpMyAdmin export:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/prepare-hosting-transfer.ps1 -SourceDump "C:\path\to\backup.sql"
```

The package is created under ignored `storage/app/hosting-transfer`. It includes the unchanged SQL export, a checksum/count manifest, and available files from the application's upload directories. It excludes local browser/tooling profiles and environment files. This is a static review, not a tested MySQL restore or a live upload. Preserve the package privately and verify the actual target database and files before switching the application.

After selecting an empty target database, use the **database** container terminal to import a dump staged privately inside that container (the command below targets `MYSQL_DATABASE`; choose the staging database name explicitly if restoring there):

```sh
MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE" < /tmp/reviewed-backup.sql
```

Then run `php artisan migrate --force` as `www-data` in the application container. Copy the source private/public uploads into the mounted `storage/app`, restore ownership to `www-data`, and run `sbispgm:deployment-check`. Never place dumps or backups under `public`.

## 6. Redeploy, back up, and verify

Before schema changes, back up MySQL, the storage volume, and the stable `APP_KEY` to a private destination. Keep the Dokploy Compose project identity and volume names unchanged across releases. Do not use `docker compose down -v`, remove volumes, or prune Docker volumes holding application data.

For a controlled update, run these commands in the **app** terminal around a deployment:

```sh
gosu www-data php artisan down --retry=60
# Deploy the reviewed release in Dokploy, then open the new app terminal.
gosu www-data php artisan migrate --force
gosu www-data php artisan sbispgm:deployment-check
gosu www-data php artisan up
```

During maintenance mode `/up` returns 503 and the container will be marked unhealthy; bring the app up after verification. `RUN_MIGRATIONS=true` can apply pending migrations on startup for a reviewed release with a backup; failures stop startup. Return it to `false` afterward. Never use `migrate:fresh` or automatic demo seeding on production.

Use Dokploy volume backups for `app_storage` and a consistent logical MySQL dump or a database-aware backup for `database_data`; a raw copy of a running MySQL volume alone is not a proven database backup. Test restoring both the database and uploads into staging.

Before live use, check:

- `/up` responds with `{"status":"ok"}` over HTTPS.
- Login, registration, password reset email links, and logout work on the actual domain.
- RBI/PDF/Excel exports, signatures, private receipt downloads, and approved document fees work.
- `.env`, `/vendor`, `/database`, and `/storage/*.php` cannot be downloaded or executed.
- `/vendor/leaflet/leaflet.js`, `/vendor/leaflet/leaflet.css`, and the Leaflet images return HTTP 200. Open Household map as an approved staff user and confirm the map and GIS layers load. Apache permits these bundled browser assets while blocking other vendor URLs. Changes to `docker/apache.conf` require rebuilding and redeploying the app image.
- Phone GPS, service-worker updates, and offline synchronization work over HTTPS.
- A redeployment preserves users and uploaded files.

The optional chatbot question interpreter can now use the private Ollama service below. The older report narrator/concern classifier (`LOCAL_AI_ENABLED`) and Python prediction service remain disabled in this stack. Their settings are separate from the chatbot interpreter. The database assistant and moving-average migration fallback remain available without AI.

## Enable the Ollama chatbot on your VPS

The Compose path stays **`docker-compose.yml`**. The `ai` profile adds a CPU Ollama container on a shared network with `app`, with a persistent `ollama_models` volume. It has no published port and needs no domain or external API key. Cloud features are disabled. The default container budget is 2 CPUs and 3 GiB RAM; leave additional capacity for MySQL, PHP, Dokploy and Docker builds. Actual response time and memory use must be checked on your VPS before enabling for users.

1. Push this change, then add these values in your existing **Dokploy Compose service → Environment** (keep your existing application/database/mail settings):

   ```dotenv
   COMPOSE_PROFILES=ai
   ASSISTANT_AI_ENABLED=true
   ASSISTANT_AI_MODEL=qwen2.5:1.5b
   ASSISTANT_AI_TIMEOUT=20
   OLLAMA_CPUS=2
   OLLAMA_MEMORY_LIMIT=3g
   ```

   The Compose file supplies `ASSISTANT_AI_URL=http://ollama:11434` to `app`. Do not use `127.0.0.1` for another container. `LOCAL_AI_ENABLED=true` alone does not enable this chatbot. The Ollama image is pinned to `ollama/ollama:0.40.3`; `OLLAMA_IMAGE` can override it for a reviewed upgrade.

2. Deploy and verify that `app`, `database`, and `ollama` are running. Dokploy writes Environment values to the Compose `.env`; Compose activates the profile through `COMPOSE_PROFILES`. If a customized deployment command overrides profiles, retain its existing project/file arguments and add `--profile ai` before `up`.

3. Open the **`ollama` container terminal** and run the one-time download:

   ```sh
   ollama pull qwen2.5:1.5b
   ollama list
   ```

   The model name must exactly match `ASSISTANT_AI_MODEL`. The first download needs network access and disk space. It is preserved across redeployments in `ollama_models`. No model download happens during web requests or application startup.

4. Open the **`app` container terminal** and check the integration:

   ```sh
   php artisan assistant:check --probe
   ```

   This checks connectivity, the local model, and a synthetic question without reading resident records. If using a host terminal instead, run `docker compose --env-file .env.dokploy --profile ai exec ollama ollama pull qwen2.5:1.5b` and `docker compose --env-file .env.dokploy exec app php artisan assistant:check --probe` from your own checkout. On Dokploy's server, prefer its container terminal so you use the correct deployed project.

5. Test as an approved barangay user: “Pila kabuok mga hamtong sa among barangay?”, then “ug babaye?”. Test a resident with “Nahuman na ba ang akong gi-request?” and “Unsay buhaton para makakuha ug sertipiko?”. Check the displayed interpretation and the actual database answer. Try a different barangay as barangay staff and confirm access is denied.

The AI chooses from supported read operations; Laravel validates that selection, retains explicitly named barangays and dates, applies the existing role checks, and supplies the final answer. Explicit population metrics and document-status filters take priority over conflicting model choices. No SQL, counts, URLs, or free-form model answers are executed/displayed. The model receives the question (which may contain text the user typed), not fetched resident records, credentials, or database access. A low-confidence result asks for clarification. Scope checks protect both AI and offline chat: outside topics and rule overrides are declined, while unsupported filters ask for clarification instead of returning broader counts. An offline, missing, or malformed model response falls back to the built-in assistant with a visible notice and a short retry backoff. Chat always uses application templates for final report wording and does not call the older report narrator, even when `LOCAL_AI_ENABLED=true`.

This is a constrained system assistant, not unrestricted general conversation. The small default model may still misunderstand a phrase. Test your users' real wording; a successful connection check does not establish answer quality for every question. Follow-up scope/month is supported for population and migration reports, not arbitrary chat history.

To disable AI, set `ASSISTANT_AI_ENABLED=false` and redeploy. To stop its container as well, stop `ollama` in Dokploy and clear `COMPOSE_PROFILES`; preserve the model volume. Do not remove database or application volumes.

For local Laragon use, install/start Ollama locally, pull the same model, and set `ASSISTANT_AI_ENABLED=true`, `ASSISTANT_AI_URL=http://127.0.0.1:11434`, and `ASSISTANT_AI_MODEL=qwen2.5:1.5b` in your local `.env`. Then run `php artisan config:clear` and `php artisan assistant:check --probe`.

References: [Ollama structured output](https://docs.ollama.com/capabilities/structured-outputs), [Ollama Docker](https://docs.ollama.com/docker), [Ollama local-only mode](https://docs.ollama.com/faq#how-do-i-disable-ollama-cloud-features), [Compose profiles](https://docs.docker.com/compose/how-tos/profiles/), and [Dokploy environment handling](https://docs.dokploy.com/docs/core/docker-compose).

## Optional local Docker check

Local validation for this deployment change passed: 12 focused deployment/safety tests, 15 offline JavaScript tests, four Python prediction tests, the production Vite build, Laravel configuration/route/view caching, shell syntax, and Compose schema/environment checks. The full PHP suite ran 279 tests with 18 failures in existing navigation/label assertions; all 18 reproduced when using the original proxy middleware and route provider. Review those application test failures before treating the entire system as release-verified.

Copy `.env.dokploy.example` to the ignored `.env.dokploy` and fill the values. Use the following on a machine with Docker Engine and Compose installed:

```sh
docker compose --env-file .env.dokploy config --quiet
docker compose --env-file .env.dokploy build --pull app
docker compose --env-file .env.dokploy up -d
docker compose --env-file .env.dokploy exec app gosu www-data php artisan migrate --force
docker compose --env-file .env.dokploy exec app gosu www-data php artisan db:seed --force
docker compose --env-file .env.dokploy exec app gosu www-data php artisan sbispgm:create-admin
docker compose --env-file .env.dokploy exec app curl --fail http://127.0.0.1/up
docker compose --env-file .env.dokploy exec app gosu www-data php artisan sbispgm:deployment-check
docker compose --env-file .env.dokploy ps
```

There are no published host ports by default. For browser testing, place a local HTTPS proxy in front of `app:80`; for a quick health check, use the container `curl` command above. `config --quiet` validates without printing interpolated secrets. A real container build/start check is still required on a Docker host; PHP tests and a local Vite build cannot verify Linux image installation, Apache, volume permissions, or Traefik routing.

Runtime and caching choices follow [Laravel's deployment guidance](https://laravel.com/docs/12.x/deployment).
