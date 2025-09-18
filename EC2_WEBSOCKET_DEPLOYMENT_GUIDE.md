# 🚀 EC2 Production Deployment Guide - WebSocket Fix

## 🔧 Issues Found & Solutions

### 1. Environment Configuration Issue
**Problem**: Laravel Echo is trying to connect to `localhost` instead of your domain.
**Root Cause**: Using wrong environment variable format and missing production config.

### 2. Reverb Server Not Running  
**Problem**: "Application does not exist" error (code 4001).
**Root Cause**: Reverb server not properly configured or not running on EC2.

## 📋 Step-by-Step Fix

### Step 1: Update Environment Configuration on EC2

Replace your `.env` file on EC2 with these critical changes:

```bash
# Core App Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://the-thinking-course.com

# Reverb Production Configuration  
REVERB_APP_ID=913761
REVERB_APP_KEY=ez8fmlurx5ekx7vdiocj
REVERB_APP_SECRET=lvdxbxymwtwuiplditwd
REVERB_HOST="the-thinking-course.com"
REVERB_PORT=443
REVERB_SCHEME=https

# Server Configuration (Internal)
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# Frontend/Vite Configuration
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"  
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Step 2: Recompile Assets on EC2

After updating the environment variables, you MUST recompile assets:

```bash
cd /var/www
npm install
npm run production
```

### Step 3: Start Reverb Server on EC2

The Reverb server must be running. Start it with:

```bash
cd /var/www
php artisan reverb:start --host=127.0.0.1 --port=8080 --hostname=the-thinking-course.com
```

For production, use a process manager like Supervisor:

#### Create Supervisor Config:
```bash
sudo nano /etc/supervisor/conf.d/reverb.conf
```

Add this content:
```ini
[program:reverb]
command=php /var/www/artisan reverb:start --host=127.0.0.1 --port=8080 --hostname=the-thinking-course.com
directory=/var/www
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

### Step 4: Verify Apache Configuration

Your Apache config looks mostly correct, but ensure this block is active:

```apache
# WebSocket Proxy for Laravel Reverb
<Location "/app/">
    ProxyPass "ws://127.0.0.1:8080/app/"
    ProxyPassReverse "ws://127.0.0.1:8080/app/"
    ProxyPassReverse "wss://the-thinking-course.com/app/"
</Location>
```

Restart Apache:
```bash
sudo systemctl restart httpd
```

### Step 5: Test the Configuration

1. **Check Reverb is running**:
   ```bash
   netstat -tulpn | grep :8080
   ```

2. **Test WebSocket connection**:
   Visit: `https://the-thinking-course.com/websocket-ssl-test.html`

3. **Check logs**:
   ```bash
   tail -f /var/log/reverb.log
   tail -f /var/log/httpd/learningjourneys_ssl_error.log
   ```

## 🔍 Debugging Commands

If still having issues, run these on your EC2 instance:

```bash
# Check if Reverb is running
ps aux | grep reverb

# Check port 8080
sudo netstat -tulpn | grep :8080

# Test local WebSocket connection
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Origin: https://the-thinking-course.com" -H "Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==" -H "Sec-WebSocket-Version: 13" http://127.0.0.1:8080/app/ez8fmlurx5ekx7vdiocj

# Check Laravel Echo configuration
cd /var/www && php artisan tinker
>>> config('reverb')

# Clear and cache config
php artisan config:clear
php artisan config:cache
```

## 🏆 Expected Result

After these fixes:
- ✅ WebSocket connects to `wss://the-thinking-course.com/app/ez8fmlurx5ekx7vdiocj`
- ✅ No "Application does not exist" error
- ✅ Laravel Echo works properly in browser
- ✅ Real-time features work

## 🚨 Common Gotchas

1. **Assets not recompiled**: Environment changes require `npm run production`
2. **Reverb not running**: Must start Reverb server on EC2
3. **Wrong environment variables**: Use `VITE_*` not `MIX_*`
4. **Apache not restarted**: Config changes need Apache restart
5. **Supervisor not configured**: Reverb needs to stay running

Let me know the results after following these steps!