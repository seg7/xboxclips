# xboxclips
A simple php script do download all your saved clips from Xbox Live. It picks up next time you run it, good for cron jobs.

### Project dependencies:

* PHP >= 8.2
* [Composer](https://getcomposer.org)
* [FFMPG](https://www.ffmpeg.org/)

### Setup the configuration & run locally

1. In the config directory, copy the file `options.ini.dist` to `options.ini`
2. Edit `options.ini` with any information needed (apikey, directory)
3. Visit the [XBOX LIVE API](https://xbl.io/) to sign up for an API key
4. Add your key to `options.ini` under the `apikey`
5. `composer install`
6. `php src/xboxclips.php`

### Alternatively using Docker (contains all the requirements)
1. In the config directory, copy the file `options.ini.dist` to `options.ini`
2. Edit `options.ini` with any information needed (apikey)
3. Visit the [XBOX LIVE API](https://xbl.io/) to sign up for an API key
4. Add your key to `options.ini` under the `apikey`
5. Update `docker-compose.yml` under `volumes` update the local directory where to put the downloaded files (linked to /app/out in the container)
6. `docker compose build`
7. `docker compose run -it php_xboxclips composer install`
8. `docker compose run -it php_xboxclips src/xboxclips.php`

![Output](https://github.com/seg7/xboxclips/blob/master/ouput.png?raw=true)