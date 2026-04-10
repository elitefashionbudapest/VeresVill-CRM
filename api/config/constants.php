<?php
/**
 * Alkalmazás konstansok
 */

// Megrendelés státuszok
const ORDER_STATUS_NEW           = 'uj';
const ORDER_STATUS_QUOTE_SENT    = 'ajanlat_kuldve';
const ORDER_STATUS_ACCEPTED      = 'elfogadva';
const ORDER_STATUS_TIME_SELECTED = 'idopont_kivalasztva';
const ORDER_STATUS_DONE          = 'elvegezve';

const ORDER_STATUSES = [
    ORDER_STATUS_NEW           => 'Új',
    ORDER_STATUS_QUOTE_SENT    => 'Árajánlat küldve',
    ORDER_STATUS_ACCEPTED      => 'Elfogadva',
    ORDER_STATUS_TIME_SELECTED => 'Időpont kiválasztva',
    ORDER_STATUS_DONE          => 'Elvégezve',
];

// Státusz átmenetek (melyikből melyikbe lehet lépni)
const ORDER_STATUS_TRANSITIONS = [
    ORDER_STATUS_NEW           => [ORDER_STATUS_QUOTE_SENT],
    ORDER_STATUS_QUOTE_SENT    => [ORDER_STATUS_ACCEPTED, ORDER_STATUS_NEW],
    ORDER_STATUS_ACCEPTED      => [ORDER_STATUS_TIME_SELECTED],
    ORDER_STATUS_TIME_SELECTED => [ORDER_STATUS_DONE, ORDER_STATUS_ACCEPTED],
    ORDER_STATUS_DONE          => [],
];

// Felhasználói szerepkörök
const ROLE_ADMIN  = 'admin';
const ROLE_WORKER = 'worker';

// Naptár esemény típusok
const EVENT_APPOINTMENT = 'appointment';
const EVENT_BLOCK       = 'block';
const EVENT_TRAVEL      = 'travel';

// Ingatlan típusok
const PROPERTY_TYPES = [
    'tarsashazi-lakas' => 'Társasházi lakás (6+ lakásos)',
    'csaladi-haz'      => 'Családi ház',
    'ikerhaz'          => 'Ikerház',
    'sorhaz'           => 'Sorház',
    'kis-tarsashaz'    => 'Kis társasház (2-5 lakás)',
    'uzlet'            => 'Üzlet / Iroda',
    'egyeb'            => 'Egyéb',
];

// Sürgősség szintek
const URGENCY_LABELS = [
    'normal'   => 'Normál (24 órán belül)',
    'same-day' => 'Még ma (aznap)',
    'express'  => 'Sürgős (24 órán belül)',
];

// Push notification platformok
const PUSH_PLATFORM_WEB = 'web';
const PUSH_PLATFORM_IOS = 'ios';

// Token lejárati idők
const AUTH_TOKEN_EXPIRY_DAYS  = 30;
const QUOTE_TOKEN_EXPIRY_DAYS = 7;

// Rate limiting
const LOGIN_MAX_ATTEMPTS     = 5;
const LOGIN_LOCKOUT_MINUTES  = 15;
