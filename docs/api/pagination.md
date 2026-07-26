# API Pagination Documentation — iC.edu Assessment Platform (IAP)

Collection endpoints support standard 1-based page query pagination.

## Parameters
- `page`: Page number (default `1`).
- `per_page`: Number of items per page (default `15`).

## Meta Format
```json
"meta": {
  "current_page": 1,
  "last_page": 4,
  "total": 60
}
```
