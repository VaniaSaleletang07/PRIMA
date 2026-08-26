@echo off
set D=c:\xampp\htdocs\ChecklistE-KIM(hosting)
del /f /q "%D%\fix-password.php"
del /f /q "%D%\diagnose-hosting.php"
del /f /q "%D%\debug-login.php"
del /f /q "%D%\debug-login-error.php"
del /f /q "%D%\debug-login-hosting.php"
del /f /q "%D%\debug-registrations.php"
del /f /q "%D%\test-admin-login.php"
del /f /q "%D%\test-login-debug.php"
del /f /q "%D%\test-registration.php"
del /f /q "%D%\test-simple.php"
del /f /q "%D%\test-system.php"
del /f /q "%D%\test.php"
del /f /q "%D%\test-api.html"
del /f /q "%D%\__cleanup_temp.bat"
echo SELESAI
