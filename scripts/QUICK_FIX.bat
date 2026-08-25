@echo off
echo ==========================================
echo   QUICK FIX DATABASE
echo ==========================================
echo.
echo This script will:
echo 1. Stop MySQL
echo 2. Delete corrupt database folder
echo 3. Restart MySQL
echo 4. Import fresh database
echo.
pause

echo.
echo [1/4] Stopping MySQL...
net stop MySQL
timeout /t 2 /nobreak >nul

echo [2/4] Deleting corrupt folder...
if exist "C:\xampp\mysql\data\checklist_ekim" (
    rmdir /s /q "C:\xampp\mysql\data\checklist_ekim"
    echo     ✓ Deleted
) else (
    echo     ! Folder not found
)

echo [3/4] Starting MySQL...
net start MySQL
timeout /t 3 /nobreak >nul

echo [4/4] Importing database...
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS checklist_ekim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
type "FIX_TABLES.sql" | C:\xampp\mysql\bin\mysql.exe -u root

echo.
echo ==========================================
echo   ✓ DONE!
echo ==========================================
echo.
echo You can now access:
echo - phpMyAdmin: http://localhost/phpmyadmin
echo - Login: http://localhost/ChecklistUpdateE-KIM/login.php
echo.
echo Admin credentials:
echo Username: admin
echo Password: admin123
echo.
pause
