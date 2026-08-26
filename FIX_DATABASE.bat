@echo off
chcp 65001 >nul
echo =====================================================
echo   FIX DATABASE CHECKLIST E-KIM
echo   Menghapus database corrupt dan rebuild
echo =====================================================
echo.

echo [PERINGATAN] Script ini akan:
echo 1. Stop MySQL
echo 2. Hapus folder database lama (SEMUA DATA AKAN HILANG!)
echo 3. Start MySQL
echo 4. Rebuild database baru
echo.
echo Tekan Ctrl+C untuk batalkan, atau
pause

echo.
echo [1/5] Stopping MySQL...
net stop MySQL
if errorlevel 1 (
    echo ❌ Gagal stop MySQL. Pastikan Anda run as Administrator!
    pause
    exit /b 1
)
echo ✅ MySQL stopped

echo.
echo [2/5] Menghapus folder database corrupt...
if exist "C:\xampp\mysql\data\checklist_ekim" (
    rmdir /s /q "C:\xampp\mysql\data\checklist_ekim"
    echo ✅ Folder database lama dihapus
) else (
    echo ⚠️ Folder tidak ditemukan (skip)
)

echo.
echo [3/5] Starting MySQL...
net start MySQL
if errorlevel 1 (
    echo ❌ Gagal start MySQL
    pause
    exit /b 1
)
echo ✅ MySQL started

echo.
echo Menunggu MySQL siap...
timeout /t 3 /nobreak >nul

echo.
echo [4/5] Membuat database baru...
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE checklist_ekim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 (
    echo ❌ Gagal membuat database
    pause
    exit /b 1
)
echo ✅ Database created

echo.
echo [5/5] Import struktur tabel dan data...
type "FIX_TABLES.sql" | C:\xampp\mysql\bin\mysql.exe -u root
if errorlevel 1 (
    echo ❌ Gagal import tabel
    pause
    exit /b 1
)

echo.
echo =====================================================
echo ✅ DATABASE BERHASIL DIPERBAIKI!
echo =====================================================
echo.
echo Kredensial Login:
echo Username: admin
echo Password: admin123
echo.
echo Akses phpMyAdmin: http://localhost/phpmyadmin
echo Akses Login: http://localhost/ChecklistUpdateE-KIM/login.php
echo.
pause
