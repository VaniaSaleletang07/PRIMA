# Backup Folder

This folder stores database backups and file backups.

## Automated Backups

- Schedule: Daily at 2:00 AM
- Retention: 30 days
- Format: `db_backup_YYYYMMDD_HHMMSS.sql.gz`

## File Permissions

- Linux: `chmod 755 backup/`
- Files: 640 (rw-r-----)

## Backup Script

Located at: `backup/backup.sh`

## Manual Backup

```bash
mysqldump -u ekim_backup -p checklist_ekim > backup/manual_backup_$(date +%Y%m%d).sql
gzip backup/manual_backup_$(date +%Y%m%d).sql
```

## Restore Procedure

```bash
gunzip backup/db_backup_YYYYMMDD_HHMMSS.sql.gz
mysql -u ekim_user -p checklist_ekim < backup/db_backup_YYYYMMDD_HHMMSS.sql
```

## Important Notes

- ⚠️ Test backups regularly!
- ⚠️ Store offsite backup copies
- ⚠️ Monitor backup size and success
