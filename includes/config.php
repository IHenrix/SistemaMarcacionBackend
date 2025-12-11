<?php

define('DB_HOST', 'localhost');
define('DB_PORT', '3307');
define('DB_NAME', 'sistema_marcaciones');
define('DB_USER', 'root');
define('DB_PASS', '1234');

/*define('DB_HOST', 'shortline.proxy.rlwy.net');
define('DB_PORT', '41356');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'dYKeUdEmXOryFZzUsmEOHpOgeZYWRLcj');*/

define('DB_CHARSET', 'utf8mb4');
define('JWT_SECRET', 'jwt1254');
define('JWT_EXPIRATION', 86400);
define('APP_NAME', 'Sistema de Marcaciones');
define('APP_VERSION', '1.0.0');

// SMTP (Gmail) - completa con tus credenciales de app password
define('SMTP_GMAIL_USER', 'dalton.vicecityz@gmail.com');
define('SMTP_GMAIL_PASS', 'efld onee yjkx bkxd');
define('SMTP_GMAIL_FROM', 'enrique.pdg@gmail.com');
define('SMTP_GMAIL_FROM_NAME', 'Sistema de Marcaciones');
define('SMTP_GMAIL_TO', 'juanjosemora2131@gmail.com'); 

date_default_timezone_set('America/Lima');

error_reporting(E_ALL);
ini_set('display_errors', 1);
