@echo off
REM Dipanggil oleh Windows Task Scheduler (task: PRIMA_Notifikasi_KIM) setiap hari.
REM Menjalankan pengecekan & pengiriman notifikasi KIM otomatis (lihat email-notifikasi.php).
"C:\xampp\php\php.exe" "c:\xampp\htdocs\SISTEM ADMINISTRASI HSSE\email-notifikasi.php" --cron >> "c:\xampp\htdocs\SISTEM ADMINISTRASI HSSE\logs\notifikasi-kim-cron.log" 2>&1
