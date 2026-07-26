# Backup & Restore Manual — iC.edu Assessment Platform (IAP)

## Automated Database Backup
Run `./scripts/backup-db.sh` via Cron daily at midnight:
```cron
0 0 * * * /var/www/html/scripts/backup-db.sh > /dev/null 2>&1
```

## Restoration Procedure
```bash
gunzip < ./storage/backups/iap_backup_YYYYMMDD_HHMMSS.sql.gz | mysql -u iap_user -p iap_db
```
