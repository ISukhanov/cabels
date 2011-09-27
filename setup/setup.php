<?php


setlocale(LC_ALL, 'ru_RU.UTF-8');
setlocale(LC_NUMERIC, 'en_US.UTF-8');

$cmsConfig = array ();

// основные конфиги
$cmsConfig['dataEncoding']   = 'utf-8';
$cmsConfig['cookieLifeTime'] = 3600 * 24 * 7; // one week

$cmsConfig['hostName']      = 'pre.vitrine.dev';

// пути
$cmsConfig['stylesPath']    = '/styles';
$cmsConfig['documentRoot']  = dirname(__FILE__) . '/..';
$cmsConfig['templatesPath'] = $cmsConfig['documentRoot'] . '/templates';
$cmsConfig['modulePath']    = $cmsConfig['documentRoot'] . '/modules';
$cmsConfig['modulePrefix']  = 'Module_';


$cmsConfig['mailerSender'] = '';
$cmsConfig['mailerEmail'] = '';

// базы данных
$cmsConfig['db'] = array ();
$cmsConfig['db']['main'] = array ();
$cmsConfig['db']['main']['host'] = 'vitrine.mysql.dev';
$cmsConfig['db']['main']['port'] = 3306;
$cmsConfig['db']['main']['user'] = 'orsnadm';
$cmsConfig['db']['main']['pass'] = 'orsnadm-vitrine';
$cmsConfig['db']['main']['name'] = 'vitrine';
$cmsConfig['db']['main']['timeout'] = 1;

$cmsConfig['db']['alter'] = array ();
$cmsConfig['db']['alter']['host'] = 'vitrine.mysql.dev';
$cmsConfig['db']['alter']['port'] = 3306;
$cmsConfig['db']['alter']['user'] = 'orsnadm';
$cmsConfig['db']['alter']['pass'] = 'orsnadm-vitrine';
$cmsConfig['db']['alter']['name'] = 'vitrine';
$cmsConfig['db']['alter']['timeout'] = 1;


define('ERRORS_LEVEL_LIGHT', 0);
define('ERRORS_LEVEL_MID', 1);
define('ERRORS_LEVEL_HARD', 2);

define('LOGGING_ENABLED', true);

define('MD5_SALT', '');

$cmsConfig['errorsLevel'] = ERRORS_LEVEL_HARD;

$cmsConfig['debugMode'] = 1;

?>
