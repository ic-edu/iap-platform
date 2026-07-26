# First Time Developer Setup Guide — iC.edu Assessment Platform (IAP)

Welcome to the iC.edu Assessment Platform! Follow these steps for an effortless initial setup.

## Step 1: Install System Dependencies (macOS Homebrew)
```bash
brew install php composer node
```

## Step 2: Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

## Step 3: Initialize Database & Compile Assets
```bash
php artisan migrate:fresh --seed --force
npm install && npm run build
```

## Step 4: Run Application
```bash
php artisan serve
```
Open `http://127.0.0.1:8000` in your browser. Log in with seeded admin credentials:
- **Admin Email**: `admin@icedu.org`
- **Password**: `password`
