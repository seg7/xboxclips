# xboxclips
A simple php script do download all your saves clips

### Project dependencies:

* PHP >= 5.5
* [Composer](https://getcomposer.org)

### Setup the configuration

1. In the config directory, copy the file `options.ini.dist` to `options.ini`
2. Edit `options.ini` with any information needed (apikey, directory)
3. Visit the [XBOX LIVE API](https://xbl.io/) to sign up for an API key
4. Add your key to `options.ini` under the `apikey`
5. `composer install`
6. `php xboxclips.php`