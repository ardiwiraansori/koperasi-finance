@echo off
setlocal
cd /d %~dp0

echo ==================================================
echo   KOPERASI ONE - SETUP DEMO LARAVEL 13
echo ==================================================
echo.

where php >nul 2>nul || (echo [ERROR] PHP tidak ditemukan. Pastikan Herd sudah aktif.& exit /b 1)
where composer >nul 2>nul || (echo [ERROR] Composer tidak ditemukan. Buka terminal baru setelah install Herd.& exit /b 1)

if not exist .env (
  copy .env.example .env >nul
  echo [OK] .env dibuat dari .env.example
  echo [INFO] Pastikan DB_DATABASE, DB_USERNAME dan DB_PASSWORD sudah benar.
  echo [INFO] Database koperasi_demo harus sudah dibuat di MySQL.
  pause
)

echo [1/4] Composer install...
call composer install || exit /b 1

echo [2/4] Memastikan APP_KEY...
findstr /C:"APP_KEY=base64:" .env >nul 2>nul
if errorlevel 1 php artisan key:generate --force || exit /b 1

echo [3/4] Migration dan seeder demo...
php artisan migrate:fresh --seed || exit /b 1

echo [4/4] Clear cache...
php artisan optimize:clear || exit /b 1

echo.
echo ==================================================
echo SELESAI. Buka: http://koperasi-finance.test
echo ==================================================
endlocal
