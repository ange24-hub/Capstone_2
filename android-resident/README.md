# RBIM Resident for Android

Native Android application for resident accounts, connected to the existing Laravel database through `/api/resident/v1`. Barangay and municipal users continue using the website. No database credentials are included in the app.

## Local Wi-Fi testing

1. Start Laragon and MySQL on the PC. The existing Laravel database and migrations must be ready.
2. From the project root, run `powershell -ExecutionPolicy Bypass -File scripts/start-resident-server.ps1`.
3. Connect the Android phone and PC to the same trusted Wi-Fi. Use the **Wi-Fi IPv4 address** printed by the script, not the `.test` hostname or `localhost`.
4. Install the debug APK on an Android 8.0+ phone. Android may ask you to allow installation from the app used to open the APK.
5. Open **RBIM Resident Test → Set server address** and enter `http://YOUR-PC-IP:8000` (example: `http://192.168.1.10:8000`).
6. Sign in using an existing resident account, or create a resident account and have the barangay approve it through the website.

The server must keep running while testing. If the phone cannot connect, open `http://YOUR-PC-IP:8000/api/resident/v1/barangays` in its browser. Confirm both devices use the same network, guest/client isolation is off, and Windows Firewall allows PHP on the private network. Do not disable the firewall. The Android emulator uses `http://10.0.2.2:8000` instead.

## Build

Open this directory in Android Studio. Install Android SDK Platform 35 and Build Tools 35.0.0, and select JDK 17 or newer compatible with Gradle 8.11.1. This project uses Android Gradle Plugin 8.9.2 and platform Java views.

```powershell
.\gradlew.bat assembleDebug lintDebug testDebugUnitTest
```

The APK is generated at `app/build/outputs/apk/debug/app-debug.apk`. `local.properties` can specify `sdk.dir=C:/path/to/android-sdk`. Both this file and build outputs are ignored by Git. The official Gradle wrapper JAR is included; Gradle downloads its pinned distribution on the first build.

On this workspace, `scripts/build-resident-android.ps1` also reuses the portable tools under `storage/app/android-toolchain` and copies a successful build to `public/downloads/rbim-resident-debug.apk`. With the local server running, the phone can download it from `http://YOUR-PC-IP:8000/downloads/rbim-resident-debug.apk`.

For an eventual production build, supply your deployed HTTPS server and configure your own signing key through Android Studio:

```powershell
.\gradlew.bat assembleRelease -PresidentServer=https://your-rbim-domain.example
```

The production variant blocks cleartext traffic and has no server editor. A release build without a configured server fails. Do not distribute the debug build as a public production app.

## Included workflows

- Resident-only registration and login, pending/rejected approval screens, status refresh, logout.
- Home summary using actual account records.
- Document creation and paginated request history, barangay remarks and release/payment status.
- Official barangay GCash details/QR and private receipt upload for staff verification. Uploading a receipt does not transfer money or automatically mark payment paid.
- Concern submission with optional JPG/PNG photo, paginated history, public updates and follow-up replies.
- Profile email/password updates requiring the current password. Verified name, role, and barangay cannot be changed from the app. Account changes revoke all API sessions.

The server checks the resident role and token permission on every protected request, and checks current approval and barangay assignment on service endpoints. Lists and details are scoped to the signed-in resident and current barangay. Internal notes, AI metadata, and file storage paths are not returned. Seven-day tokens are encrypted with Android Keystore on the device; passwords are not saved. Network errors do not automatically retry submissions. If a submission times out, check the list before submitting again.

This version requires a connection to the PC and refreshes updates on navigation or manual refresh. It does not provide push notifications or offline submission. Android device/emulator acceptance testing should cover registration, approval, login, upload, keyboard/back navigation, font scaling, and denied/expired sessions before wider distribution.

## Backend verification

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd vendor/phpunit/phpunit/phpunit --filter="ResidentMobileApiTest|ResidentConcernTest|DocumentPaymentTest|AuthRbacTest"
```

The tests use an isolated SQLite database. They cover role restrictions, pending approvals, expired/revoked tokens, record ownership, public-only concern messages, payment validation, and protected profile changes.

References: [Android build compatibility](https://developer.android.com/build/releases/agp-8-9-0-release-notes), [Laravel mobile authentication](https://laravel.com/docs/10.x/sanctum#mobile-application-authentication).
