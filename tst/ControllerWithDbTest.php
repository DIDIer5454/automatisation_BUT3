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

use PrivateBin\Data\Database;
use PrivateBin\Persistence\ServerSalt;
use PrivateBin\Persistence\TrafficLimiter;

require_once 'ControllerTest.php';

/**
 * @internal
 * @coversNothing
 */
final class ControllerWithDbTest extends ControllerTest
{
    private $_options = [
        'usr' => null,
        'pwd' => null,
        'opt' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => true,
        ],
    ];

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        $this->_options['dsn'] = 'sqlite:'.$this->_path.DIRECTORY_SEPARATOR.'tst.sq3';
        $this->_data = new Database($this->_options);
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
            'class' => 'Database',
        ];
        $options['model_options'] = $this->_options;
        Helper::createIniFile(CONF, $options);
    }
}
