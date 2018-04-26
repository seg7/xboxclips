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
use phpFastCache\Helper\Psr16Adapter;

try {
    $Psr16Adapter = new Psr16Adapter('files', [ //Init cache
        'path'       => BASE_PATH . '/cache/',
        'defaultTtl' => 86400,
    ]);

    echo "Downloading file list...\n";

    $client = new GuzzleHttp\Client(['base_uri' => $options['xbl.io']['base_uri']]);

    $retries = $options['xbl.io']['retries'];

    do {
        $request = $client->request('GET', 'dvr/gameclips', [
            'headers' => [
                'Accept'          => 'application/json',
                'X-Authorization' => $options['xbl.io']['apikey'],
            ]
        ]);

        $gameClips = @json_decode($request->getBody(), true)['gameClips'];

        if(null === $gameClips) {
            sleep(5);
            $retries++;
        }
    } while(--$retries > 0);

    $gameClips = null === $gameClips ? die('No valid JSON.') : array_reverse($gameClips);

    $cache = [ //Get Cache
        'hash'        => $Psr16Adapter->get('hash', ''),
        'gameClipIds' => $Psr16Adapter->get('gameClipIds', []),
        'counter'     => $Psr16Adapter->get('counter', 0),
    ];

    $cache['hash'] = md5(json_encode(array_column($gameClips, 'gameClipId'))) === $cache['hash']
        ? die('Nothing to update!')
        : md5(json_encode(array_column($gameClips, 'gameClipId')));

    foreach ($gameClips as $gameClip) {
        if (!in_array($gameClip['gameClipId'], $cache['gameClipIds'], false) &&
            (in_array($gameClip['titleName'], $options['xbox']['gameClipId'], false)
                || in_array('*', $options['xbox']['gameClipId'], false))
        ) {
            $cache['counter']++;
            $destination = $options['xbox']['file_format'] === 'original'
                ? "{$gameClip['gameClipId']}.mp4"
                : sprintf("{$options['xbox']['file_format']}.mp4", $cache['counter']);
            $cache['gameClipIds'][] = $gameClip['gameClipId'];
            $uri = $gameClip['gameClipUris']['0']['uri'];
            echo "{$gameClip['gameClipId']}->${destination}...\n";
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

    $Psr16Adapter->setMultiple([ //Save Cache
        'hash'        => $cache['hash'],
        'gameClipIds' => $cache['gameClipIds'],
        'counter'     => $cache['counter'],
    ], $date);
} catch (
    Exception | GuzzleException $e) {
    die($e->getMessage());
}