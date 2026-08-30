@echo off
setlocal EnableExtensions

cd /d "%~dp0"

set "LARAGON_ROOT=C:\laragon"
if defined RBIM_LARAGON_ROOT set "LARAGON_ROOT=%RBIM_LARAGON_ROOT%"

set "MYSQL_HOME=%LARAGON_ROOT%\bin\mysql\mysql-8.0.30-winx64"
set "MYSQL_SERVER=%MYSQL_HOME%\bin\mysqld.exe"
set "MYSQL_ADMIN=%MYSQL_HOME%\bin\mysqladmin.exe"
set "MYSQL_CONFIG=%MYSQL_HOME%\my.ini"

if not exist "%MYSQL_SERVER%" (
    echo RBIM could not find MySQL at:
    echo   %MYSQL_SERVER%
    echo.
    echo Set RBIM_LARAGON_ROOT if Laragon is installed somewhere else.
    pause
    exit /b 1
)

call :mysql_ready
if errorlevel 1 (
    echo Starting the RBIM database...
    start "RBIM MySQL" /min "%MYSQL_SERVER%" --defaults-file="%MYSQL_CONFIG%"
    call :wait_for_mysql
    if errorlevel 1 (
        echo.
        echo MySQL did not become ready. Open Laragon and click Start All,
        echo then run this launcher again.
        pause
        exit /b 1
    )
)

if /I "%~1"=="--check" (
    echo Database ready.
    exit /b 0
)

echo Database ready. Starting RBIM at http://127.0.0.1:8000 ...
php artisan serve --host=127.0.0.1 --port=8000
exit /b %errorlevel%

:wait_for_mysql
for /L %%I in (1,1,30) do (
    call :mysql_ready
    if not errorlevel 1 exit /b 0
    timeout /t 1 /nobreak >nul
)
exit /b 1

:mysql_ready
"%MYSQL_ADMIN%" --host=127.0.0.1 --port=3306 --user=root --connect-timeout=1 ping --silent >nul 2>&1
exit /b %errorlevel%
