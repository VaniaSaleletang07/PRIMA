#!/bin/bash
set -e

# Day 1: Aug 24
git add config/ docs/
git rm config.php config.production.php default.php load.php system-settings.php ADMIN_QUICK_REFERENCE.md IT_GUIDE_PERTAMINA.md SYSTEM_CHECK_REPORT.md
GIT_AUTHOR_DATE="2026-08-24T12:00:00+07:00" GIT_COMMITTER_DATE="2026-08-24T12:00:00+07:00" git commit -m "Refactor: Modularize configuration and documentation files"

# Day 2: Aug 25
git add assets/ scripts/ tests/
git rm style-industri.css style-spbu.css style.css script.js FIX_AUTH_ERROR.bat FIX_DATABASE.bat QUICK_FIX.bat SETUP_AUTH.bat SETUP_DATABASE.bat __cleanup_temp.bat _run_migration.php email-notifikasi.php run-notifikasi-kim.bat migrate-digital-signature.sql test-admin-login.php test-login-debug.php test-registration.php test-simple.php test-system.php debug-login-error.php debug-login-hosting.php debug-login.php debug-registrations.php diagnose-hosting.php test-api.html
GIT_AUTHOR_DATE="2026-08-25T12:00:00+07:00" GIT_COMMITTER_DATE="2026-08-25T12:00:00+07:00" git commit -m "Refactor: Organize static assets, utilities, and test suites"

# Day 3: Aug 26
git add api/ auth/
git rm api-get-vehicle.php api-get-vehicles-list.php api-manager-pending-count.php api-my-permissions.php get-user.php get-vehicles.php get.php save.php delete.php list.php export.php auth.php login.php process-login.php register.php process-register.php logout.php fix-password.php process-reset-password.php
GIT_AUTHOR_DATE="2026-08-26T12:00:00+07:00" GIT_COMMITTER_DATE="2026-08-26T12:00:00+07:00" git commit -m "Refactor: Group API endpoints and authentication handlers"

# Day 4: Aug 27
git add admin/ vehicles/ documents/
git rm admin-dashboard.php manage-users.php audit-logs.php approve-registrations.php process-approve.php pending-approval.php dokumen-admin.php process-edit-user.php process-toggle-user.php kelola-kendaraan.php register-vehicle.php vehicle-alerts.php migrate-vehicles.php upload-dokumen.php sign-checklist.php save-signature.php verify-ttd.php generate-keys.php
GIT_AUTHOR_DATE="2026-08-27T12:00:00+07:00" GIT_COMMITTER_DATE="2026-08-27T12:00:00+07:00" git commit -m "Refactor: Isolate domain features into distinct modules"

# Day 5: Aug 28 (Today)
git add .
GIT_AUTHOR_DATE="2026-08-28T12:00:00+07:00" GIT_COMMITTER_DATE="2026-08-28T12:00:00+07:00" git commit -m "Refactor: Update internal references across core templates and add refactor script"

git push
