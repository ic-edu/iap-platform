# Infrastructure Scaling Guide — iC.edu Assessment Platform (IAP)

## Horizontal Scaling Strategy
1. **Stateless Web Application**: Session storage backed by Redis / MySQL database.
2. **Database Read Replicas**: Configure read/write connection split in `config/database.php`.
3. **Queue Workers**: Scale Supervisor processes (`numprocs=4`) to handle high CBT test submission volume.
