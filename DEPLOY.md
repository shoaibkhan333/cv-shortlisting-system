# Deploy live for FREE (no debit card)

Your app is **PHP + MySQL**. Vercel cannot run it. These hosts are **free and do not require a debit card**.

---

## Best option: InfinityFree (recommended)

**Website:** https://www.infinityfree.com  
**Includes:** PHP, MySQL, FTP, free subdomain, SSL — **no credit/debit card**

### Step 1 — Create account
1. Sign up at https://www.infinityfree.com
2. Create a hosting account (pick a free subdomain, e.g. `cvshortlisting.infinityfreeapp.com`)

### Step 2 — Create MySQL database
1. Open **Control Panel (hPanel)** → **MySQL Databases**
2. Create a new database (note **hostname**, **database name**, **username**, **password**)

### Step 3 — Import your SQL
1. Open **phpMyAdmin** from hPanel
2. Select your database
3. **Import** → choose `database/schema.sql` from this project
4. Click **Go**

### Step 4 — Upload project files
Use **File Manager** or **FTP** (FileZilla):

| Upload to | Files |
|-----------|--------|
| `htdocs/` (or `public_html/`) | Everything **except** `.git`, `.vercel`, `Dockerfile`, `render.yaml` |

Upload these folders/files:
- `auth/`, `users/`, `manager/`, `includes/`, `uploads/`, `database/`
- `index.html`, `.htaccess`, `config.js`

### Step 5 — Configure database
1. Edit `includes/db_config.php` on the server (or upload `includes/db_config.example.php` as `db_config.php`)
2. Set your InfinityFree MySQL details:

```php
define('DB_HOST', 'sqlXXX.infinityfree.com'); // from hPanel
define('DB_USER', 'epiz_XXXXXX');
define('DB_PASS', 'your_password');
define('DB_NAME', 'epiz_XXXXXX_cvshort');
```

### Step 6 — Set uploads folder permissions
In File Manager, set `uploads/` folder to **755** or **777** (writable).

### Step 7 — Test
- Home: `https://YOUR-SITE.infinityfreeapp.com/`
- Login: `https://YOUR-SITE.infinityfreeapp.com/auth/login.php`
- Manager: `manager@gmail.com` / `Manager@123` (from schema.sql)

---

## Other free alternatives (no card)

| Host | PHP | MySQL | Notes |
|------|-----|-------|-------|
| [InfinityFree](https://infinityfree.com) | Yes | Yes | Best for this project |
| [000webhost](https://www.000webhost.com) | Yes | Yes | 300MB limit |
| [AwardSpace](https://www.awardspace.com) | Yes | Yes | Smaller free plan |

Avoid for this project: **Vercel, Netlify, GitHub Pages** (static only, no PHP).

---

## Optional: keep Vercel landing page

If you still want Vercel for the homepage only:

1. Deploy full app on **InfinityFree** first (get your URL)
2. Edit `config.js` in GitHub:

```javascript
window.APP_URL = 'https://YOUR-SITE.infinityfreeapp.com';
```

3. Push → Vercel redeploys → Login/Register buttons go to InfinityFree

Or skip Vercel and use **only your InfinityFree URL** for everything (simplest).

---

## Run locally (always works, no hosting)

```powershell
cd "c:\Users\SHOAIB KHAN\Desktop\CV1"
C:\xampp\php\php.exe -S localhost:8000 -t .
```

Open: http://localhost:8000/auth/login.php  
(Need XAMPP MySQL running with database `cv_shortlisting1`)

---

## Temporary public demo (no hosting signup)

Use **ngrok** to share localhost with a public link (free, no card):

1. Download https://ngrok.com (free account, no card for basic)
2. Run your PHP server on port 8000
3. Run: `ngrok http 8000`
4. Share the `https://xxxx.ngrok-free.app` link

Good for demos; link changes each time on free ngrok.

---

## Manager test account

After importing `database/schema.sql`:

- **Email:** manager@gmail.com  
- **Password:** Manager@123  

Candidate signup requires **@gmail.com** email and strong password.
