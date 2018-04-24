#!/usr/bin/php
<?php
date_default_timezone_set('Europe/Lisbon');
$date = new DateTime('2077-07-07T07:07:07Z');
error_reporting(E_ALL & ~E_USER_DEPRECATED);

define('BASE_PATH', realpath(dirname(realpath(__FILE__))));

if (!file_exists(BASE_PATH . '/vendor')) {
    die('Please use `composer install`.');
}

if (!file_exists(BASE_PATH . '/config/options.ini')) {
    die('In the config directory, copy the file `options.ini.dist` to `options.ini`.');
}

$options = parse_ini_file(BASE_PATH . '/config/options.ini', true);

if ($options['xbl.io']['apikey'] === 'APIKEY') {
    die('Add your key to options.ini under the apikey (APIKEY).');
}

if ($options['xbox']['destination'] === 'DIRECTORY') {
    die('Edit options.ini with the output path (DIRECTORY).');
}

require BASE_PATH . '/vendor/autoload.php';

use GuzzleHttp\Exception\GuzzleException;
use phpFastCache\CacheManager;

try {
    CacheManager::setDefaultConfig([
        'path' => BASE_PATH . '/cache/',
    ]);

    $InstanceCache = CacheManager::getInstance('files');

    echo "Downloading file list...\n";

    $client = new GuzzleHttp\Client(['base_uri' => $options['xbl.io']['base_uri']]);

    $retries = $options['xbl.io']['retries'];

    do {

        $request = $client->request('GET', 'dvr/gameclips', [
            'headers' => [
                'Accept' => 'application/json',
                'X-Authorization' => $options['xbl.io']['apikey'],
            ]
        ]);

        $json = @json_decode($request->getBody(), true)['gameClips'];

        if(null === $json) {
            sleep(5);
            $retries++;
        }

    } while(--$retries > 0);

    $json = null === $json ? die('No valid JSON.') : array_reverse($json);

    $cache = [
        'json' => $InstanceCache->getItem('json'),
        'gameClipIds' => $InstanceCache->getItem('gameClipIds'),
        'counter' => $InstanceCache->getItem('counter'),
    ];

    $json_cached = $cache['json']->get() ?? [];

    $gameClipIds_cached = $cache['gameClipIds']->get() ?? [];

    $counter = $cache['counter']->get() ?? 0;

    $gameClipIds = $gameClipIds_cached;

    if (array_column($json, 'gameClipId') === $json_cached) {
        die('Nothing to update!');
    }

} catch (\phpFastCache\Exceptions\phpFastCacheDriverCheckException | GuzzleException $e) {
    die($e->getMessage());
}

foreach ($json as $item) {
    if (!in_array($item['gameClipId'], $gameClipIds_cached, false) &&
        (in_array($item['titleName'], $options['xbox']['gameClipId'], false)
            || in_array('*', $options['xbox']['gameClipId'], false))
    ) {
        $counter++;
        $destination = $options['xbox']['file_format'] === 'original'
            ? "{$item['gameClipId']}.mp4"
            : sprintf("{$options['xbox']['file_format']}.mp4", $counter);
        $gameClipIds[] = $item['gameClipId'];
        $uri = $item['gameClipUris']['0']['uri'];
        echo "{$item['gameClipId']}->${destination}...\n";
        if (!file_exists($options['xbox']['destination'])
            && !mkdir($options['xbox']['destination'], 0777, true)
            && !is_dir($options['xbox']['destination'])
        ) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $options['xbox']['destination']));
        }
        if (!file_exists($options['xbox']['destination'] . '/' . $destination)) {
            file_put_contents($options['xbox']['destination'] . '/' . $destination, fopen($uri, 'rb'));
        }
    }
}

$cache['gameClipIds']->set($gameClipIds)->expiresAt($date);
$InstanceCache->save($cache['gameClipIds']);

$cache['counter']->set($counter)->expiresAt($date);
$InstanceCache->save($cache['counter']);

$cache['json']->set(array_column($json, 'gameClipId'))->expiresAt($date);
$InstanceCache->save($cache['json']);