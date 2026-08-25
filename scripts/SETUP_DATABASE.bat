@echo off
echo ========================================
echo Setup Database Checklist E-KIM
echo Pertamina Patra Niaga
echo ========================================
echo.

echo [1/4] Creating database...
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS checklist_ekim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo [2/4] Importing database structure...
C:\xampp\mysql\bin\mysql.exe -u root checklist_ekim < database.sql

echo [3/4] Adding jenis_kendaraan column...
C:\xampp\mysql\bin\mysql.exe -u root checklist_ekim < database_update_jenis.sql

echo [4/4] Verifying setup...
C:\xampp\mysql\bin\mysql.exe -u root checklist_ekim -e "SHOW TABLES;"

echo.
echo ========================================
echo Database setup COMPLETE!
echo ========================================
echo.
echo Silakan buka: http://localhost/ChecklistUpdateE-KIM/test.php
echo untuk test koneksi database
echo.
pause
