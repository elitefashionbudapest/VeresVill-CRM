# Google Naptár beállítási útmutató

## 1. Google Cloud Console

1. Nyisd meg: https://console.cloud.google.com/
2. Hozz létre egy új projektet: **"VeresVill CRM"**
3. Menj: **APIs & Services → Library**
4. Keresd meg és engedélyezd: **Google Calendar API**

## 2. OAuth consent screen

1. Menj: **APIs & Services → OAuth consent screen**
2. Válaszd: **External**
3. Töltsd ki:
   - App name: `VeresVill CRM`
   - User support email: a te emailed
   - Developer contact: a te emailed
4. **Save and Continue** → Scopes részt kihagyhatod → **Save and Continue**
5. Test users: add meg a saját Google emailed → **Save**

## 3. OAuth2 Credentials

1. Menj: **APIs & Services → Credentials**
2. Kattints: **Create Credentials → OAuth 2.0 Client ID**
3. Application type: **Web application**
4. Name: `VeresVill CRM`
5. **Authorized redirect URIs** — add hozzá:
   ```
   https://visualbyadam.hu/veresvill_crm/api/google/callback
   ```
   (Élesben majd: `https://veresvill.hu/api/google/callback`)
6. **Create** → Másold ki a **Client ID** és **Client Secret** értékeket

## 4. .env beállítás

Szerkeszd a szerveren a `.env` fájlt, add hozzá:

```
GOOGLE_CLIENT_ID=123456789-abcdefg.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxx
```

## 5. SQL migráció

Ha még nem futtattad, phpMyAdmin-ban futtasd a `database/migration_google_calendar.sql` tartalmát.

## 6. Csatlakoztatás

1. Nyisd meg az admin panelt → Beállítások
2. Kattints: **Google Naptár csatlakoztatása**
3. Jelentkezz be a Google fiókoddal
4. Engedélyezd a hozzáférést
5. Kész! A szinkron automatikusan elindul.

## 7. Cron job (opcionális, automata háttér szinkron)

cPanel → Cron Jobs → Add:
- Minden 10 percben: `*/10 * * * *`
- Parancs: `php /home/elitediv/public_html/veresvill_crm/api/cron/google_sync.php`

Ez a böngésző nélkül is szinkronizálja a naptárat a háttérben.

## Élesítéskor

A redirect URI-t módosítsd:
- Google Cloud Console → Credentials → szerkeszd az OAuth Client-et
- Cseréld ki: `https://visualbyadam.hu/veresvill_crm/api/google/callback` → `https://veresvill.hu/api/google/callback`
- `.env` → `APP_URL=https://veresvill.hu`
