# GenifyAI Bridge - cPanel Deployment Guide

## 📦 **Part 1: Laravel Deploy to cPanel**

### Step 1: Prepare Laravel Files
1. Run these commands in your local project:
```bash
# Clear cache
php artisan optimize:clear

# Generate optimized cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Install production dependencies (if needed)
composer install --no-dev --optimize-autoloader
```

### Step 2: Upload to cPanel
1. Login to cPanel → File Manager → **public_html**
2. Upload ALL Laravel files (EXCEPT node_modules, .env, storage/logs/*)
3. OR use Git:
   - Go to cPanel → Git Version Control
   - Clone your repo
   - Checkout to main branch

### Step 3: cPanel Document Root Setup
**IMPORTANT:** cPanel hosting එකේ Laravel run කරන්න public folder එක document root විදියට set කරන්න ඕනේ.

**Option A: Change Document Root (Recommended)**
1. cPanel → **Domains** → **minneriyasafari.online** (or your domain)
2. Click on your domain
3. Set **Document Root** to: `public_html/public`
4. Save

**Option B: Use .htaccess (If Option A not available)**
Create this `.htaccess` in public_html:

```apache
RewriteEngine On
RewriteRule ^(.*)$ public/$1 [L]
```

### Step 4: Environment Setup
1. Copy `.env.example` to `.env` in public_html
2. Update these values:

```env
APP_NAME=GenifyAI Bridge
APP_ENV=production
APP_DEBUG=false
APP_URL=https://whatsappautomate.online.minneriyasafari.online

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_cpanel_db_name
DB_USERNAME=your_cpanel_db_user
DB_PASSWORD=your_cpanel_db_password

# OpenAI
OPENAI_API_KEY=your_openai_key_here

# WhatsApp Cloud API
WEBHOOK_VERIFY_TOKEN=my_secret_token_123

# Node Bridge
NODE_BRIDGE_URL=https://whatsappautomate.online.minneriyasafari.online:3000
NODE_BRIDGE_SECRET_KEY=genify-node-bridge-secret-2026

# Python Service (if used)
PYTHON_SERVICE_API_KEY=SuperBridge#99!Admin

# Google OAuth
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URL=https://whatsappautomate.online.minneriyasafari.online/auth/google/callback

# App Key (generate with: php artisan key:generate)
APP_KEY=base64_your_generated_key_here
```

### Step 5: Database Setup
1. cPanel → **MySQL Databases**
2. Create a new database
3. Create a database user
4. Add user to database with ALL PRIVILEGES
5. Run migrations:
```bash
# Via cPanel Terminal or SSH
cd public_html
php artisan migrate
```

### Step 6: Storage Link
```bash
php artisan storage:link
```

### Step 7: Queue Setup
For queue workers, create a cron job:
```bash
# cPanel → Cron Jobs → Add:
* * * * * /usr/local/bin/php /home/username/public_html/artisan schedule:run >> /dev/null 2>&1
```

---

## 📱 **Part 2: Node.js Bridge Deploy**

### ⚠️ **cPanel Limitations for Node.js**
Most shared cPanel hosting **does NOT support** running Node.js permanently.
You have these options:

### **Option A: Use a Free Node.js Hosting (Recommended)**
Use **Railway.app**, **Render.com**, or **Fly.io** (free tiers):

#### **Railway.app (Easiest)**
1. Go to https://railway.app
2. Sign up with GitHub
3. Click **New Project** → **Deploy from GitHub repo**
4. Select your `node-bridge` folder
5. Add these environment variables:
   ```
   PORT=3000
   LARAVEL_URL=https://whatsappautomate.online.minneriyasafari.online
   LARAVEL_API_KEY=genify-node-bridge-secret-2026
   ```
6. Railway will give you a URL like: `https://genify-bridge.up.railway.app`
7. Update Laravel `.env`:
   ```
   NODE_BRIDGE_URL=https://genify-bridge.up.railway.app
   ```

#### **Render.com (Free)**
1. Go to https://render.com
2. New **Web Service**
3. Connect GitHub repo with `node-bridge` folder
4. Start Command: `npm start`
5. Add environment variables
6. Get URL and update Laravel `.env`

### **Option B: Use VPS (DigitalOcean / Linode)**
If cPanel has **Terminal** access:
```bash
# Install Node.js via cPanel's Setup Node.js App
# Or use SSH:
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Go to node-bridge folder
cd node-bridge
npm install

# Run with PM2 (stays alive after logout)
npm install -g pm2
pm2 start server.js --name genify-bridge
pm2 save
pm2 startup
```

### **Option C: Use cPanel's Node.js Selector (If Available)**
1. cPanel → **Setup Node.js App**
2. Create new app
3. Point to your `node-bridge` folder
4. Entry point: `server.js`
5. Start the app
6. Note the URL (usually port 3000 or similar)

---

## 🔗 **Step 3: Connect Everything**

### After deploying both:

1. **Update Laravel `.env`:**
```
NODE_BRIDGE_URL=https://your-node-bridge-url.com
NODE_BRIDGE_SECRET_KEY=genify-node-bridge-secret-2026
```

2. **Update Node.js `bridge_config.env`:**
```
LARAVEL_URL=https://whatsappautomate.online.minneriyasafari.online
LARAVEL_API_KEY=genify-node-bridge-secret-2026
```

3. **Test connection:**
```bash
curl https://your-node-bridge-url.com/health
```

---

## ✅ **Quick Checklist**

- [ ] Laravel uploaded to public_html
- [ ] Document Root set to `public_html/public`
- [ ] `.env` configured with correct database
- [ ] Database created and migrations run
- [ ] `APP_KEY` generated
- [ ] Storage linked (`php artisan storage:link`)
- [ ] Google OAuth redirect URL updated
- [ ] Node.js deployed on Railway/Render/VPS
- [ ] `NODE_BRIDGE_URL` updated in Laravel `.env`
- [ ] OpenAI API key set
- [ ] Queue worker running (cron job)
- [ ] Google Sheets service account uploaded: `storage/app/service_account.json`

---

## 🔒 **Security Notes**
- Never commit `.env` to Git
- Keep `NODE_BRIDGE_SECRET_KEY` secret
- Use HTTPS everywhere
- Restrict database access to localhost only
- Set `APP_DEBUG=false` in production
