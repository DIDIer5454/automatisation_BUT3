<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Google\Auth\HttpHandler\HttpHandlerFactory;
use GuzzleHttp\Client;
use PrivateBin\Data\GoogleCloudStorage;
use PrivateBin\Persistence\ServerSalt;
use PrivateBin\Persistence\TrafficLimiter;

require_once 'ControllerTest.php';

/**
 * @internal
 * @coversNothing
 */
final class ControllerWithGcsTest extends ControllerTest
{
    private static $_client;
    private static $_bucket;
    private $_options = [];

    public static function setUpBeforeClass(): void
    {
        $httpClient = new Client(['debug' => false]);
        $handler = HttpHandlerFactory::build($httpClient);

        $name = 'pb-';
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        for ($i = 0; $i < 29; ++$i) {
            $name .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        self::$_client = new StorageClientStub([]);
        self::$_bucket = self::$_client->createBucket($name);
    }

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        $this->_options = [
            'bucket' => self::$_bucket->name(),
            'prefix' => 'pastes',
        ];
        $this->_data = new GoogleCloudStorage($this->_options);
        ServerSalt::setStore($this->_data);
        TrafficLimiter::setStore($this->_data);
        $this->reset();
    }

    public function reset(): void
    {
        parent::reset();
        // but then inject a db config
        $options = parse_ini_file(CONF, true);
        $options['model'] = [
            'class' => 'GoogleCloudStorage',
        ];
        $options['model_options'] = $this->_options;
        Helper::createIniFile(CONF, $options);
    }
}
