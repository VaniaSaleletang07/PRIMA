@echo off
chcp 65001 >nul
echo ============================================
echo   FIX AUTHENTICATION ERROR
echo   PRIMA (Pertamina Checklist Mobil Tangki)
echo ============================================
echo.

REM Configuration
set MYSQL_USER=root
set MYSQL_PASS=
set DB_NAME=checklist_ekim

echo [STEP 1/5] Checking MySQL...
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo ❌ ERROR: MySQL tidak running!
    echo    Buka XAMPP Control Panel dan Start MySQL
    pause
    exit /b 1
)
echo ✅ MySQL is running

echo.
echo [STEP 2/5] Checking if database exists...
mysql -u %MYSQL_USER% -e "SHOW DATABASES LIKE '%DB_NAME%';" -s -N > temp_db_check.txt 2>&1

findstr /C:"%DB_NAME%" temp_db_check.txt >nul
if errorlevel 1 (
    echo ⚠️  Database '%DB_NAME%' tidak ditemukan
    echo 📦 Creating database...
    mysql -u %MYSQL_USER% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if errorlevel 1 (
        echo ❌ Gagal create database!
        del temp_db_check.txt
        pause
        exit /b 1
    )
    echo ✅ Database created
) else (
    echo ✅ Database exists
)
del temp_db_check.txt

echo.
echo [STEP 3/5] Setup base tables (database.sql)...
echo    Importing database.sql...

mysql -u %MYSQL_USER% %DB_NAME% < database.sql 2>error_base.log
if errorlevel 1 (
    echo ⚠️  Warning: Some errors in database.sql
    echo    This is OK if tables already exist
    type error_base.log
) else (
    echo ✅ Base tables ready
)

echo.
echo [STEP 4/5] Setup authentication (database_auth.sql)...
echo    Importing database_auth.sql...

if not exist database_auth.sql (
    echo ❌ ERROR: database_auth.sql tidak ditemukan!
    echo    File ini diperlukan untuk sistem autentikasi
    pause
    exit /b 1
)

mysql -u %MYSQL_USER% %DB_NAME% < database_auth.sql 2>error_auth.log
if errorlevel 1 (
    echo ⚠️  Warning: Some errors in database_auth.sql
    type error_auth.log
) else (
    echo ✅ Authentication tables ready
)

echo.
echo [STEP 5/5] Verifying setup...

REM Check admin user
mysql -u %MYSQL_USER% %DB_NAME% -e "SELECT COUNT(*) as count FROM users WHERE username='admin';" -s -N > admin_check.txt 2>&1
set /p ADMIN_COUNT=<admin_check.txt
del admin_check.txt

if "%ADMIN_COUNT%"=="0" (
    echo ⚠️  Admin user tidak ditemukan!
    echo 📝 Creating admin user...
    mysql -u %MYSQL_USER% %DB_NAME% -e "INSERT INTO users (username, password, full_name, email, role, status, approved_by, approved_at) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@pertamina.com', 'admin', 'active', 1, NOW());"
    if errorlevel 1 (
        echo ❌ Gagal create admin user!
        pause
        exit /b 1
    )
    echo ✅ Admin user created
) else (
    echo ✅ Admin user exists
)

REM Check required tables
echo    Checking tables...
mysql -u %MYSQL_USER% %DB_NAME% -e "SHOW TABLES;" -s > tables.txt
findstr /C:"user_registrations" tables.txt >nul
if errorlevel 1 (
    echo ❌ Table user_registrations tidak ditemukan!
    del tables.txt
    pause
    exit /b 1
)
findstr /C:"user_sessions" tables.txt >nul
if errorlevel 1 (
    echo ❌ Table user_sessions tidak ditemukan!
    del tables.txt
    pause
    exit /b 1
)
del tables.txt
echo ✅ All required tables exist

echo.
echo ============================================
echo   ✅ SETUP COMPLETE!
echo ============================================
echo.
echo 🎯 Credentials untuk login:
echo    Username: admin
echo    Password: admin123
echo.
echo 📋 Next Steps:
echo    1. Buka: http://localhost/ChecklistUpdateE-KIM/debug-login.php
echo    2. Pastikan semua test PASSED
echo    3. Buka: http://localhost/ChecklistUpdateE-KIM/login.php
echo    4. Login dengan credentials di atas
echo.
echo ⚠️  Jika masih error, screenshot hasil debug-login.php
echo.
pause

REM Cleanup
if exist error_base.log del error_base.log
if exist error_auth.log del error_auth.log
