# Local AI operations

The assistant uses local Ollama for optional report explanations. Laravel computes the authoritative population and migration totals and enforces the user's barangay scope. The model receives selected aggregate facts, not resident names or raw user prompts. Report reads do not update RBI records.

## Start and stop

From the project directory in PowerShell:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/start-local-ai.ps1
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/start-local-ai.ps1 -CheckOnly
powershell.exe -NoProfile -ExecutionPolicy Bypass -File scripts/stop-local-ai.ps1
```

The execution-policy option applies to these processes only. The start script launches a hidden process, binds to `127.0.0.1:11434`, sets `OLLAMA_NO_CLOUD=1`, and limits concurrent model execution to one request. Runtime files, models, PID, and server logs live under ignored private `storage/app/local-ai`. The script refuses to reuse an unrelated process on the same port. Start it again after reboot; no Windows startup task is installed.

The pilot runtime is Ollama v0.34.0, with CPU libraries and executable taken from the official Windows archive. The partial installation verifies extracted ZIP-entry CRCs and records SHA256 hashes in `cpu-install-manifest.json`; it does not claim verification against the SHA256 of the entire archive. GPU libraries were omitted to reduce download size. For a replacement installation, obtain the official Windows standalone archive from https://docs.ollama.com/windows and extract its runtime into `storage/app/local-ai/runtime`.

## Configure the application

```dotenv
LOCAL_AI_ENABLED=true
LOCAL_AI_URL=http://127.0.0.1:11434
LOCAL_AI_MODEL=qwen3:0.6b
LOCAL_AI_TIMEOUT=25
```

Only enable after the model is installed and evaluated. Run `php artisan config:clear` after changing configuration. To disable generation while keeping report answers available, set `LOCAL_AI_ENABLED=false` and clear the configuration cache. No external AI key is needed.

The initial model download requires internet. Subsequent local generation uses the installed model. Keep the local endpoint private; the browser accesses authenticated Laravel routes, not Ollama directly. Server logs can include runtime information but request-body logging is not enabled by the project script.

## Validate

```powershell
php scripts/check-local-ai.php
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --filter='LocalAssistantTest|AssistantChatTest|PopulationReportTest|MigrationReportTest'
```

The first command uses synthetic aggregate cases without querying or changing registry records and reports real model latency and accepted explanations. It is a smoke test, not proof of general accuracy. The second runs isolated in-memory database tests, including authorization, fallback, and preservation of record contents.

The current query router recognizes supported English/Cebuano keywords. Generated explanations are requested in English. This is not unrestricted natural-language querying, completed behavioral analysis, or forecasting. A small model may produce poor explanations; rejected, unavailable, or timed-out generation leaves the database answer available. Accepted numeric values do not prove semantic correctness. Validate with barangay staff before production use.
