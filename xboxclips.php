#!/usr/bin/php
<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

include __DIR__ . '/includes/requirements.php';

try {
    $Psr16Adapter = new phpFastCache\Helper\Psr16Adapter('files', [ //Init cache
        'path'       => __DIR__ . '/cache/',
        'defaultTtl' => 186624000,
    ]);

    $log = new Logger('name');
    $log->pushHandler(new StreamHandler(__DIR__ . '/log/xboxclips.log', Logger::DEBUG));

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
        $log->info('Downloading file list');

        if(null === $gameClips) {
            $log->error('No valid JSON');
            sleep(5);
            $retries++;
            $log->info('Retrying');
        }
    } while(--$retries > 0);

    $gameClips = null === $gameClips ? die('No valid JSON.') : array_reverse($gameClips);

    $hash = md5(json_encode(array_column($gameClips, 'gameClipId'))); //Request Hash

    $cache = [ //Get Cache
        'hash'        => $Psr16Adapter->get('hash', ''),
        'gameClipIds' => $Psr16Adapter->get('gameClipIds', []),
        'counter'     => $Psr16Adapter->get('counter', 0),
    ];

    $log->info('Cache', $cache);

    $log->info('Hash', [
        'cached' => $cache['hash'],
        'hash'   => $hash,
    ]);

    $cache['hash'] = $hash === $cache['hash']
        ? die('Nothing to update!')
        : $hash;

    foreach ($gameClips as $gameClip) {
        if (!in_array($gameClip['gameClipId'], $cache['gameClipIds'], false) &&
            (in_array($gameClip['titleName'], $options['xbox']['gameClipId'], false)
                || in_array('*', $options['xbox']['gameClipId'], false))
        ) {
            $cache['counter']++;
            $retries = $options['download']['retries'];
            $destination = $options['download']['file_format'] === 'original'
                ? "{$gameClip['gameClipId']}.mp4"
                : sprintf("{$options['download']['file_format']}.mp4", $cache['counter']);
            $uri = $gameClip['gameClipUris']['0']['uri'];
            echo "{$gameClip['gameClipId']}->${destination}...";
            $log->info("{$gameClip['gameClipId']}->${destination}");
            if (!file_exists($options['download']['destination'])
                && !mkdir($options['download']['destination'], 0777, true)
                && !is_dir($options['download']['destination'])
            ) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $options['download']['destination']));
            }
            if (!file_exists($options['download']['destination'] . '/' . $destination)) {
                download:
                $handle = fopen($uri, 'rb');
                $transfer = file_put_contents($options['download']['destination'] . '/' . $destination, $handle);
                //Verify file consistency
                $output = shell_exec(
                        "{$options['gpac']['bin']} ".implode(' ', $options['gpac']['args'])." {$options['download']['destination']}/{$destination} 2>&1"
                );
                if(strpos($output, $options['gpac']['search']) !== false) {
                    if(file_exists($options['download']['destination'] . '/' . $destination)) {
                        unlink($options['download']['destination'] . '/' . $destination);
                    }
                    echo 'error...';
                    $log->error('mp4 file inconsistent');
                    if($retries--) {
                        echo "retrying...";
                        $log->info('Retrying');
                        goto download;
                    }
                    $cache['hash'] = 'error';
                    $cache['counter']--;
                } else {
                    echo 'ok...';
                    $log->info('File downloaded');
                    $cache['gameClipIds'][] = $gameClip['gameClipId'];
                }
            }
            echo "\n";
        }
    }

    $Psr16Adapter->setMultiple([ //Save Cache
        'hash'        => $cache['hash'],
        'gameClipIds' => $cache['gameClipIds'],
        'counter'     => $cache['counter'],
    ]);
    $log->info('Saving Cache', $cache);
} catch (Exception | GuzzleHttp\Exception\GuzzleException $e) {
    $log->error($e->getMessage());
    die($e->getMessage());
}