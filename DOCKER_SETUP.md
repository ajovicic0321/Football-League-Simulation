# Docker Setup Guide

## Overview
This project uses Docker for consistent development and deployment environment.

## Services
- **app**: Laravel PHP 8.1 application with FPM
- **webserver**: Nginx web server (port 8000)
- **db**: MySQL 8.0 database (port 3308)
- **node**: Node.js 18 for Vue.js development (port 5173)

## Environment Configuration

### Required .env Changes for Docker
Update your `.env` file with these database settings:

```env
APP_NAME="Football League Simulation"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=football_league
DB_USERNAME=laravel
DB_PASSWORD=password
```

## Docker Commands

### Build and Start Services
```bash
docker-compose up -d --build
```

### Stop Services
```bash
docker-compose down
```

### View Logs
```bash
docker-compose logs -f
```

### Run Laravel Commands
```bash
docker-compose exec app php artisan migrate
docker-compose exec app php artisan key:generate
```

### Access MySQL Database
```bash
docker-compose exec db mysql -u laravel -p football_league
```

## Port Mappings
- Laravel App: http://localhost:8000
- Vue.js Dev Server: http://localhost:5173
- MySQL Database: localhost:3308

## Volumes
- Application code is mounted at `/var/www` in the app container
- MySQL data persists in `dbdata` volume
- Node modules are managed within the node container