# Deployment Runbook — iC.edu Assessment Platform (IAP)

## Overview
This runbook details zero-downtime deployment procedures for the IAP production environment.

## Prerequisites
- Docker & Docker Compose
- Nginx & Supervisor
- SSL Certificate configured (HTTPS)

## Step-by-Step Deployment
1. SSH into production server.
2. Execute `./scripts/deploy.sh`.
3. Verify `/health` and `/ready` endpoints return HTTP 200 `healthy`.
