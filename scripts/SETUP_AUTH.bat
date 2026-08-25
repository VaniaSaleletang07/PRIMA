@echo off
echo ============================================
echo   Setup Autentikasi E-KIM System
echo   Pertamina Patra Niaga
echo ============================================
echo.

REM Configuration
set MYSQL_USER=root
set MYSQL_PASS=
set DB_NAME=checklist_ekim
set SQL_FILE=database_auth.sql

echo [1/3] Checking MySQL service...
sc query MySQL | find "RUNNING" >nul
if errorlevel 1 (
    echo ERROR: MySQL service is not running!
    echo Please start XAMPP MySQL first.
    pause
    exit /b 1
)
echo OK - MySQL is running

echo.
echo [2/3] Applying authentication schema...
echo Running: %SQL_FILE%

if "%MYSQL_PASS%"=="" (
    mysql -u %MYSQL_USER% %DB_NAME% < %SQL_FILE%
) else (
    mysql -u %MYSQL_USER% -p%MYSQL_PASS% %DB_NAME% < %SQL_FILE%
)

if errorlevel 1 (
    echo ERROR: Failed to apply SQL updates!
    echo Check if database '%DB_NAME%' exists.
    pause
    exit /b 1
)

echo OK - Schema updated successfully

echo.
echo [3/3] Verifying tables...
mysql -u %MYSQL_USER% %DB_NAME% -e "SHOW TABLES LIKE 'user_%%';" -s

echo.
echo ============================================
echo   Setup Complete!
echo ============================================
echo.
echo Default Admin Credentials:
echo   Username: admin
echo   Password: admin123
echo.
echo Next Steps:
echo 1. Open: http://localhost/ChecklistUpdateE-KIM/login.php
echo 2. Login with admin credentials
echo 3. Change admin password immediately!
echo.
echo For detailed guide, see: AUTHENTICATION_SETUP.md
echo ============================================
pause
