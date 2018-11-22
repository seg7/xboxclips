<?php
date_default_timezone_set('Europe/Lisbon');
error_reporting(E_ALL & ~E_USER_DEPRECATED);

if (!file_exists(__DIR__ . '/../vendor')) {
    die('Please use `composer install`.');
}

if (!file_exists(__DIR__ . '/../config/options.ini')) {
    die('In the config directory, copy the file `options.ini.dist` to `options.ini`.');
}

$options = parse_ini_file(__DIR__ . '/../config/options.ini', true);

if ($options['xbl.io']['apikey'] === 'APIKEY') {
    die('Add your key to options.ini under the apikey (APIKEY).');
}

if ($options['download']['destination'] === 'DIRECTORY') {
    die('Edit options.ini with the output path (DIRECTORY).');
}

if (!file_exists($options['gpac']['bin'])) {
    die('Please install GPAC requirement or update option.ini with the correct location.');
}

require __DIR__ . '/../vendor/autoload.php';