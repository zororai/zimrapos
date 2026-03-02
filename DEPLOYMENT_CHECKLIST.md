# 🚀 Hostinger Deployment Checklist - chekuleftpos.co.zw

Quick reference checklist for deploying ZIMRA POS to production.

---

## ☑️ Pre-Deployment

- [ ] Backup local database
- [ ] Test all features locally
- [ ] Update `.gitignore` (exclude `.env`, `node_modules`, `vendor`)
- [ ] Commit and push latest changes to Git
- [ ] Create Hostinger MySQL database
- [ ] Note database credentials
- [ ] Enable SSH access on Hostinger
- [ ] Install SSL certificate (Let's Encrypt)

---

## ☑️ File Upload

- [ ] Upload project files via FTP/SSH (or Git clone)
- [ ] Verify `.htaccess` exists in root directory
- [ ] Verify `public/.htaccess` exists
- [ ] Upload complete (exclude: `node_modules`, `vendor`, `.git`, `.env`)

---

## ☑️ Server Configuration

- [ ] Create `.env` file from `env.production.template`
- [ ] Update database credentials in `.env`
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_URL=https://chekuleftpos.co.zw`
- [ ] Update `ZIMRA_BASE_URL` (production or test)
- [ ] Configure mail settings (SMTP)
- [ ] Run: `composer install --no-dev --optimize-autoloader`
- [ ] Run: `php artisan key:generate`
- [ ] Run: `npm install --production && npm run build` (if applicable)

---

## ☑️ Permissions

```bash
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/logs
chmod -R 775 storage/framework
chmod -R 775 storage/app
chmod 644 .htaccess
chmod 644 public/.htaccess
```

- [ ] Storage directory writable
- [ ] Bootstrap cache writable
- [ ] Logs directory writable
- [ ] `.htaccess` readable

---

## ☑️ Database Setup

- [ ] Run: `php artisan migrate --force`
- [ ] Run: `php artisan storage:link`
- [ ] Verify all tables created
- [ ] Seed initial data (if needed)

---

## ☑️ Cache & Optimization

- [ ] Run: `php artisan config:cache`
- [ ] Run: `php artisan route:cache`
- [ ] Run: `php artisan view:cache`
- [ ] Run: `composer dump-autoload --optimize`

---

## ☑️ Cron Jobs Setup

### Cron Job 1: Laravel Scheduler
```
* * * * * /usr/bin/php8.2 /home/u123456789/domains/chekuleftpos.co.zw/public_html/artisan schedule:run >> /dev/null 2>&1
```

### Cron Job 2: Queue Worker
```
* * * * * /usr/bin/php8.2 /home/u123456789/domains/chekuleftpos.co.zw/public_html/artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

- [ ] Scheduler cron job created
- [ ] Queue worker cron job created
- [ ] Verify PHP path is correct (`which php`)
- [ ] Update paths with actual username/directory

---

## ☑️ Testing & Verification

### Basic Tests
- [ ] Visit: https://chekuleftpos.co.zw (loads without errors)
- [ ] Check HTTPS is working (green padlock)
- [ ] Test login functionality
- [ ] Check browser console (no JS errors)
- [ ] Test database connection: `php artisan tinker` → `DB::connection()->getPdo();`

### ZIMRA-Specific Tests
- [ ] Verify ZIMRA device registration
- [ ] Test receipt generation
- [ ] Check ZIMRA ping in logs (wait 5-10 minutes)
- [ ] Test fiscal day open/close
- [ ] Verify certificate files exist in `storage/app/zimra/`

### Queue & Scheduler Tests
- [ ] Run: `php artisan schedule:list` (verify ZIMRA ping scheduled)
- [ ] Run: `php artisan queue:work --once` (test queue processing)
- [ ] Check logs: `tail -f storage/logs/laravel.log`
- [ ] Wait 5 minutes, verify ping executed

---

## ☑️ Security Hardening

- [ ] `.env` file not accessible via browser
- [ ] Directory listing disabled
- [ ] HTTPS enforced (redirect HTTP to HTTPS)
- [ ] Strong database password set
- [ ] File permissions correct (no 777)
- [ ] Error reporting disabled in production (`APP_DEBUG=false`)

---

## ☑️ Monitoring Setup

- [ ] Check error logs: `storage/logs/laravel.log`
- [ ] Set up log rotation (if needed)
- [ ] Configure email notifications for critical errors
- [ ] Bookmark Hostinger hPanel
- [ ] Document admin credentials securely

---

## ☑️ Post-Deployment

- [ ] Test all major features
- [ ] Create test sale and receipt
- [ ] Verify email notifications work
- [ ] Check ZIMRA FDMS integration
- [ ] Monitor logs for 24 hours
- [ ] Document any issues
- [ ] Create backup schedule
- [ ] Train users on production system

---

## 📝 Important Commands Reference

```bash
# Navigate to project
cd /home/u123456789/domains/chekuleftpos.co.zw/public_html

# Clear all cache
php artisan optimize:clear

# Rebuild cache
php artisan optimize

# Check scheduler
php artisan schedule:list

# Test queue
php artisan queue:work --once

# View logs
tail -50 storage/logs/laravel.log

# Maintenance mode ON
php artisan down

# Maintenance mode OFF
php artisan up
```

---

## 🆘 Emergency Contacts

- **Hostinger Support**: https://www.hostinger.com/contact
- **Developer**: _____________
- **ZIMRA Support**: _____________

---

## 📊 Deployment Info

| Item | Value |
|------|-------|
| **Production URL** | https://chekuleftpos.co.zw |
| **Server** | Hostinger Shared Hosting |
| **PHP Version** | 8.2 |
| **Database** | MySQL (u123456789_zimra) |
| **ZIMRA Environment** | Production / Test |
| **Deployment Date** | _____________ |
| **Deployed By** | _____________ |

---

✅ **All items checked?** Your ZIMRA POS is ready for production!

For detailed instructions, see: `HOSTINGER_DEPLOYMENT_GUIDE.md`
