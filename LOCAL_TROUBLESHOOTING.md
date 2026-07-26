# Local Troubleshooting Guide — iC.edu Assessment Platform (IAP)

## Common Issues on macOS

### 1. Node / npm Not Found
- Ensure `/usr/local/bin` is in your `PATH`:
  ```bash
  export PATH=$PATH:/usr/local/bin
  ```

### 2. SQLite Database File Permission Denied
- Ensure `database/database.sqlite` is writable:
  ```bash
  touch database/database.sqlite
  chmod 666 database/database.sqlite
  ```

### 3. Missing Storage Symlink
- Run:
  ```bash
  php artisan storage:link
  ```
