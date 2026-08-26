# Logs Folder

This folder stores application error logs and system logs.

## File Permissions

- Linux: `chmod 755 logs/`
- Files: Auto-created with 644

## Log Files

- `php_errors.log` - PHP errors and warnings
- `audit.log` - User activity audit trail
- `access.log` - Access patterns (optional)

## Log Rotation

Configured in `/etc/logrotate.d/ekim` (Linux)

- Daily rotation
- Keep 30 days
- Compress old logs

## Monitoring

Check logs regularly:

```bash
tail -f logs/php_errors.log
```
