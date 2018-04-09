#!/usr/bin/php
<?php
	date_default_timezone_set ( "Europe/Lisbon" );
	$date = new DateTime("2077-07-07T07:07:07Z");
	error_reporting(E_ALL & ~E_USER_DEPRECATED);

	define('BASE_PATH',realpath(dirname(realpath(__FILE__))  ));

    if(!file_exists(BASE_PATH . '/vendor'))
        die('Please use `composer install`.');

    if(!file_exists(BASE_PATH . '/config/options.ini'))
        die('In the config directory, copy the file `options.ini.dist` to `options.ini`.');

    $options = parse_ini_file ( BASE_PATH . '/config/options.ini', true );

    if($options['xbl.io']['apikey'] == 'APIKEY')
        die('Add your key to options.ini under the apikey (APIKEY).');

    if($options['xbox']['destination'] == 'DIRECTORY')
        die('Edit options.ini with the output path (DIRECTORY).');

	require realpath(dirname(__FILE__) . '/vendor/autoload.php');

	use phpFastCache\CacheManager;

	CacheManager::setup(array(
    	"path" => BASE_PATH . '/cache/',
	));

	$InstanceCache = CacheManager::getInstance('files');

	echo "Downloading file list...\n";

	try {
		$client = new GuzzleHttp\Client(['base_uri' => $options['xbl.io']['base_uri']]);

		$request = $client->request('GET', 'dvr/gameclips', [
	    	'headers' => [
	        	'Accept'          => 'application/json',
	        	'X-Authorization' => $options['xbl.io']['apikey'],
	    	]
		]);

		$json = @json_decode($request->getBody(), true)['gameClips'];
	} catch (Exception $e) {
		die($e->getMessage());
	}

	$json = is_null($json) ? [] : array_reverse($json);

    $cache['json'] = $InstanceCache->getItem('json');
    $json_cached = (is_null($cache['json']->get()) ? [] : $cache['json']->get());
    if(array_column($json, 'gameClipId') == $json_cached || empty($json))
        die('Nothing to update!');

	$cache['gameClipIds'] = $InstanceCache->getItem('gameClipIds');
	$cache['counter'] = $InstanceCache->getItem('counter');

	$gameClipIds_cached = (is_null($cache['gameClipIds']->get()) ? [] : $cache['gameClipIds']->get());
    $counter = (is_null($cache['counter']->get()) ? 0 : $cache['counter']->get());

    $gameClipIds = $gameClipIds_cached;

	foreach ($json as $item) {
		if ((in_array($item['titleName'], $options['xbox']['gameClipId'])
            || in_array('*', $options['xbox']['gameClipId']))
			&& !in_array($item['gameClipId'], $gameClipIds_cached)
		){
            $counter++;
            $destination = $options['xbox']['file_format'] == 'original'
                ? "{$item['gameClipId']}.mp4"
                : sprintf("{$options['xbox']['file_format']}.mp4", $counter);
            $gameClipIds[] = $item['gameClipId'];
			$uri = $item['gameClipUris']['0']['uri'];
            echo "{$item['gameClipId']}->${destination}...\n";
            if(!file_exists($options['xbox']['destination']))
                mkdir($options['xbox']['destination'], 0777, true);
            if(!file_exists($options['xbox']['destination'] . '/' . $destination))
			    file_put_contents($options['xbox']['destination'] . '/' . $destination, fopen($uri, 'rb'));
		}
	}

    $cache['gameClipIds']->set($gameClipIds)->expiresAt($date);
    $InstanceCache->save($cache['gameClipIds']);

    $cache['counter']->set($counter)->expiresAt($date);
    $InstanceCache->save($cache['counter']);

    $cache['json']->set(array_column($json, 'gameClipId'))->expiresAt($date);
    $InstanceCache->save($cache['json']);

?>