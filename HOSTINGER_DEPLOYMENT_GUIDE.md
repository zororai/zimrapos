# Hostinger Deployment Guide - ZIMRA POS System

Complete guide for deploying the ZIMRA POS system to **chekuleftpos.co.zw** on Hostinger.

---

## 📋 Pre-Deployment Checklist

- [ ] Hostinger account with SSH access enabled
- [ ] Domain configured: **chekuleftpos.co.zw**
- [ ] MySQL database created on Hostinger
- [ ] SSL certificate installed (Let's Encrypt via hPanel)
- [ ] Git repository access (optional, for deployment)

---

## 🚀 Step 1: Prepare Files for Upload

### Option A: Direct Upload via File Manager

1. **Compress your project** (exclude these):
   ```
   /node_modules
   /vendor
   /.git
   /.env
   /storage/logs/*
   /storage/framework/cache/*
   /storage/framework/sessions/*
   /storage/framework/views/*
   ```

2. **Upload to Hostinger**:
   - Login to hPanel → File Manager
   - Navigate to `/domains/chekuleftpos.co.zw/public_html/`
   - Upload and extract the zip file

### Option B: Git Deployment (Recommended)

```bash
# SSH into Hostinger
ssh u123456789@yourdomain.com

# Navigate to public_html
cd domains/chekuleftpos.co.zw/public_html

# Clone repository
git clone https://github.com/yourusername/zimra.git .

# Or pull latest changes
git pull origin main
```

---

## 🗄️ Step 2: Database Setup

### Create Database via hPanel

1. **Go to**: Databases → MySQL Databases
2. **Create new database**: `u123456789_zimra`
3. **Create user**: `u123456789_zimra_user`
4. **Set strong password** and save it
5. **Grant all privileges** to the user

### Note Database Credentials

```
DB_HOST=localhost
DB_DATABASE=u123456789_zimra
DB_USERNAME=u123456789_zimra_user
DB_PASSWORD=your_secure_password
```

---

## ⚙️ Step 3: Configure Environment

### Create Production .env File

SSH into server and create `.env`:

```bash
cd /home/u123456789/domains/chekuleftpos.co.zw/public_html
cp .env.example .env
nano .env
```

### Production .env Configuration

```env
APP_NAME="ZIMRA POS"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://chekuleftpos.co.zw

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# Database - Use Hostinger credentials
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u123456789_zimra
DB_USERNAME=u123456789_zimra_user
DB_PASSWORD=YOUR_SECURE_PASSWORD_HERE

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.chekuleftpos.co.zw

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=noreply@chekuleftpos.co.zw
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@chekuleftpos.co.zw"
MAIL_FROM_NAME="${APP_NAME}"

# Panier API Configuration (if applicable)
PANIER_API_BASE_URL=http://localhost:3000/api/v1
PANIER_APP_ID=
PANIER_API_KEY=
PANIER_RATE_LIMIT=500
PANIER_RATE_LIMIT_MINUTES=5

# ZIMRA Device Registration
# PRODUCTION: Use production FDMS URL
ZIMRA_BASE_URL=https://fdmsapi.zimra.co.zw
ZIMRA_DEVICE_MODEL=Server
ZIMRA_DEVICE_VERSION=v1
```

**Important**: Change `ZIMRA_BASE_URL` from test to production when ready!

---

## 📦 Step 4: Install Dependencies

### Via SSH

```bash
# Navigate to project
cd /home/u123456789/domains/chekuleftpos.co.zw/public_html

# Install Composer dependencies (production only)
composer install --no-dev --optimize-autoloader

# Generate application key
php artisan key:generate

# Install NPM dependencies (if needed)
npm install --production
npm run build
```

### If Composer is not available

Download dependencies locally, then upload the `/vendor` folder via FTP.

---

## 🔧 Step 5: Set Permissions

```bash
# Set correct permissions
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
chmod -R 775 storage/framework
chmod -R 775 storage/app

# Ensure .htaccess is readable
chmod 644 .htaccess
chmod 644 public/.htaccess
```

---

## 🗃️ Step 6: Run Migrations

```bash
# Run database migrations
php artisan migrate --force

# Create storage symlink
php artisan storage:link

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## ⏰ Step 7: Setup Cron Jobs

### Via hPanel → Advanced → Cron Jobs

**Cron Job 1: Laravel Scheduler** (Every minute)
```
* * * * * /usr/bin/php8.2 /home/u123456789/domains/chekuleftpos.co.zw/public_html/artisan schedule:run >> /dev/null 2>&1
```

**Cron Job 2: Queue Worker** (Every minute, with stop-when-empty)
```
* * * * * /usr/bin/php8.2 /home/u123456789/domains/chekuleftpos.co.zw/public_html/artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

### Find Your PHP Path

Common Hostinger PHP paths:
- PHP 8.2: `/usr/bin/php8.2`
- PHP 8.1: `/usr/bin/php8.1`
- PHP 8.0: `/usr/bin/php8.0`

To verify, run via SSH:
```bash
which php
php -v
```

---

## 🔒 Step 8: Security Hardening

### Verify .htaccess is Working

The `.htaccess` file in the root redirects all traffic to `/public`:

```apache
<IfModule mod_rewrite.c>
    Rewriteengine On
    RewriteRule ^(.*)$ public/$1 [L]
    RewriteRule ^storage-images/(.*)$ /storage/app/public/$1 [L]
</IfModule>
```

### Additional Security

1. **Disable directory listing** (should be default)
2. **Ensure .env is not accessible** via browser
3. **Enable HTTPS** (Let's Encrypt via hPanel)
4. **Set up firewall rules** if available

---

## ✅ Step 9: Verification Checklist

### Test Basic Functionality

1. **Visit**: https://chekuleftpos.co.zw
   - Should load without errors
   - Check browser console for JS errors

2. **Test Database Connection**:
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

3. **Check Logs**:
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Verify Cron Jobs**:
   - Wait 5-10 minutes
   - Check logs for: `ZIMRA Ping Successful` or `ZimraPingJob`

5. **Test Queue Processing**:
   ```bash
   # Dispatch a test job
   php artisan queue:work --once
   ```

### ZIMRA-Specific Tests

1. **Check ZIMRA Configuration**:
   - Login to admin panel
   - Navigate to ZIMRA settings
   - Verify device registration status

2. **Test Receipt Generation**:
   - Create a test sale
   - Verify receipt is sent to ZIMRA
   - Check FDMS response in logs

3. **Verify Fiscal Day Operations**:
   - Test opening fiscal day
   - Test closing fiscal day
   - Check counter synchronization

---

## 🐛 Troubleshooting

### Common Issues

**500 Internal Server Error**
```bash
# Check logs
tail -50 storage/logs/laravel.log

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# Regenerate autoload
composer dump-autoload
```

**Database Connection Failed**
- Verify DB credentials in `.env`
- Check if database exists in hPanel
- Ensure user has correct privileges

**Cron Jobs Not Running**
- Verify PHP path is correct
- Check cron job syntax
- Look for email notifications from cron
- Test manually: `php artisan schedule:run`

**Queue Jobs Not Processing**
- Ensure `QUEUE_CONNECTION=database` in `.env`
- Check `jobs` table exists: `php artisan migrate`
- Verify cron job for queue worker is running
- Test manually: `php artisan queue:work --once`

**ZIMRA Ping Failing**
- Check `ZIMRA_BASE_URL` in `.env`
- Verify device certificates exist in `storage/app/zimra/`
- Check if device is registered
- Review logs: `grep "ZIMRA Ping" storage/logs/laravel.log`

**File Upload Issues**
- Check storage permissions: `chmod -R 775 storage`
- Verify symlink: `php artisan storage:link`
- Check disk space: `df -h`

---

## 📊 Monitoring

### Set Up Log Monitoring

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# Search for errors
grep "ERROR" storage/logs/laravel.log

# Check ZIMRA-specific logs
grep "ZIMRA" storage/logs/laravel.log
```

### Performance Optimization

```bash
# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize Composer autoloader
composer dump-autoload --optimize
```

---

## 🔄 Updating the Application

### Via SSH

```bash
# Navigate to project
cd /home/u123456789/domains/chekuleftpos.co.zw/public_html

# Put in maintenance mode
php artisan down

# Pull latest changes
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and rebuild cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Exit maintenance mode
php artisan up
```

---

## 📞 Support Resources

- **Hostinger Support**: https://www.hostinger.com/contact
- **Laravel Docs**: https://laravel.com/docs
- **ZIMRA FDMS API**: Check your API documentation

---

## 🎯 Quick Reference

### Important Paths

```
Project Root: /home/u123456789/domains/chekuleftpos.co.zw/public_html
Public Dir:   /home/u123456789/domains/chekuleftpos.co.zw/public_html/public
Storage:      /home/u123456789/domains/chekuleftpos.co.zw/public_html/storage
Logs:         /home/u123456789/domains/chekuleftpos.co.zw/public_html/storage/logs
```

### Essential Commands

```bash
# Clear all cache
php artisan optimize:clear

# Rebuild cache
php artisan optimize

# Check queue
php artisan queue:work --once

# Check scheduler
php artisan schedule:list

# View logs
tail -f storage/logs/laravel.log
```

---

**Deployment Date**: _____________  
**Deployed By**: _____________  
**Production URL**: https://chekuleftpos.co.zw  
**Database**: u123456789_zimra  

---

✅ **Deployment Complete!** Your ZIMRA POS system is now live on Hostinger.
