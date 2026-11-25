# LeadX

SMM panel built with Laravel 12 admin, employee, and team web portals, paired with the Flutter employee mobile app on the `app` branch.

## Requirements

- PHP >= 8.2
- MySQL 8.0+ or MariaDB 10.5+
- Composer
- Node.js & npm (for assets)

## Installation

1. Clone the repository and enter the project directory:
   ```bash
   git clone <repository-url> leadx
   cd leadx
   ```
2. Install PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Copy the environment file:
   ```bash
   cp .env.example .env
   ```
4. Point your web server document root to `<repo>/public`, then open `/install` in your browser and follow the setup wizard (it configures `.env`, runs database migrations, and creates the initial Super Admin account).

## Documentation

- [Installation Guide](docs/LEADX_INSTALLATION_GUIDE.md)
- [Phase 16 Production Deployment Guide](docs/LEADX_PHASE_16_DEPLOYMENT_GUIDE.md)
- [Phase 17 Deployment Checklist](docs/LEADX_PHASE_17_DEPLOYMENT_CHECKLIST.md)

## Mobile App

The Flutter employee app lives on the `app` branch, which shares no git history with `main` — they are maintained separately.
