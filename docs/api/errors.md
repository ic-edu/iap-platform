# API Error Handling Documentation — iC.edu Assessment Platform (IAP)

Errors follow the uniform failure format:

```json
{
  "success": false,
  "message": "Validation failed for request parameters.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## HTTP Status Codes
- `400 Bad Request`: Invalid parameters.
- `401 Unauthorized`: Missing or invalid Bearer token.
- `403 Forbidden`: Insufficient permissions.
- `404 Not Found`: Resource does not exist.
- `422 Unprocessable Entity`: Form validation error.
- `429 Too Many Requests`: Rate limit exceeded.
- `500 Internal Error`: Unexpected server error.
