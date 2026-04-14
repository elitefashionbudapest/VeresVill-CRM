# Google Naptár + Google Sheets beállítási útmutató

Az integráció **új iránya (2026-04-14-től)**:
- **Google Naptár → CRM (read-only)**: a GCal-ban lévő foglalt időket letöltjük az admin naptárba, hogy ne tudjunk ütköző időpontot kiadni ajánlatkéréskor.
- **CRM → Google Sheets**: amikor egy ügyfél elfogad egy időpontot, új sor kerül egy meglévő Google Sheet táblázatba.
- **CRM → Google Naptár**: **megszűnt**. A CRM már nem ír vissza eseményeket a Google Naptárba.

---

## 1. Google Cloud Console — projekt

1. Nyisd meg: https://console.cloud.google.com/
2. Ha még nincs: hozz létre projektet — **"VeresVill CRM"**
3. Menj: **APIs & Services → Library**
4. Engedélyezd az alábbi API-kat (mindkettőt!):
   - **Google Calendar API**
   - **Google Sheets API**

## 2. OAuth consent screen

1. Menj: **APIs & Services → OAuth consent screen**
2. User Type: **External** (ha még nincs beállítva)
3. Töltsd ki:
   - App name: `VeresVill CRM`
   - User support email: a te emailed
   - Developer contact: a te emailed
4. **Save and Continue**
5. **Scopes** — kattints **Add or remove scopes**, keresd és pipáld be:
   - `https://www.googleapis.com/auth/calendar.readonly` — csak olvasás a naptárból
   - `https://www.googleapis.com/auth/spreadsheets` — Sheets olvasás+írás
   - (Ha korábban már `https://www.googleapis.com/auth/calendar` scope volt engedve, vedd ki — most már csak readonly kell.)
6. **Save and Continue**
7. **Test users**: add meg a saját Google emailed (ha még nincs) → **Save**

> ⚠️ Scope változás után a meglévő CRM-es Google csatlakozást **újra kell engedélyezni**: Admin panel → Beállítások → Google Naptár lecsatlakoztatása, majd újra csatlakoztatás. E nélkül a Sheets írás 403-at fog dobni.

## 3. OAuth2 Credentials

1. Menj: **APIs & Services → Credentials**
2. Ha még nincs: **Create Credentials → OAuth 2.0 Client ID**
3. Application type: **Web application**
4. Name: `VeresVill CRM`
5. **Authorized redirect URIs**:
   ```
   https://visualbyadam.hu/veresvill_crm/api/google/callback
   ```
   (Élesben majd: `https://veresvill.hu/api/google/callback`)
6. **Create** → másold ki a **Client ID** és **Client Secret** értékeket

## 4. Google Sheet előkészítése

1. Nyisd meg a táblázatot, amelybe az elfogadott időpontokat szeretnéd írni
2. A Google felhasználód, amivel a CRM csatlakozik, legyen **szerkesztő jogú** a táblázaton (a scope akkor is kell, de jog nélkül az írás elbukik)
3. Másold ki a táblázat URL-jéből az ID-t:
   ```
   https://docs.google.com/spreadsheets/d/EZ_AZ_ID_HIÁNYZIK_IDE/edit
   ```
4. Jegyezd fel a **munkalap nevét** is (a fül alján, pl. `Sheet1`, `Megrendelések`)
5. Első sor legyen fejléc (a CRM ezt a sort nem bántja), vagy hagyd üresen — a CRM a következő üres sorhoz fog appendelni

## 5. .env beállítás

Szerkeszd a szerveren a `.env` fájlt:

```
# Google OAuth
GOOGLE_CLIENT_ID=123456789-abcdefg.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxx

# Google Sheets — elfogadott időpontok export
GOOGLE_SHEET_ID=EZ_AZ_ID_A_TÁBLÁZAT_URL_ÉBŐL
GOOGLE_SHEET_TAB=Sheet1
```

## 6. SQL migráció

Ha még nem futtattad, phpMyAdmin-ban futtasd a `database/migration_google_calendar.sql` tartalmát.

## 7. Csatlakoztatás (vagy újracsatlakoztatás)

1. Nyisd meg az admin panelt → **Beállítások**
2. Ha már csatlakoztatva volt: **lecsatlakoztatás** először (a scope-ok miatt)
3. **Google Naptár csatlakoztatása**
4. Jelentkezz be azzal a Google fiókkal, amelyik **mindkettőhöz** (naptár + Sheet) hozzáfér
5. Engedélyezd a hozzáférést — most már Sheets scope-ot is kérünk
6. Kész

## 8. Cron job (opcionális, automata háttér szinkron)

cPanel → Cron Jobs → Add:
- Minden 10 percben: `*/10 * * * *`
- Parancs: `php /home/elitediv/public_html/veresvill_crm/api/cron/google_sync.php`

Ez a böngésző nélkül is letölti a háttérben a Google Naptár eseményeit az admin naptárba.

---

## Élesítéskor

1. Google Cloud Console → Credentials → OAuth Client szerkesztése
2. Redirect URI cseréje: `https://visualbyadam.hu/veresvill_crm/api/google/callback` → `https://veresvill.hu/api/google/callback`
3. `.env`:
   - `APP_URL=https://veresvill.hu`
   - `GOOGLE_SHEET_ID` — ha éles táblázat más, cseréld
4. Admin panelben lecsatlakozás + újracsatlakozás (új redirect URI)
