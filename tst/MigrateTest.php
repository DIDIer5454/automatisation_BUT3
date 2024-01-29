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

use PHPUnit\Framework\TestCase;
use PrivateBin\Data\Database;
use PrivateBin\Data\Filesystem;

/**
 * @internal
 * @coversNothing
 */
final class MigrateTest extends TestCase
{
    protected $_model_1;

    protected $_model_2;

    protected $_path;

    protected $_path_instance_1;

    protected $_path_instance_2;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        $this->_path_instance_1 = $this->_path.DIRECTORY_SEPARATOR.'instance_1';
        $this->_path_instance_2 = $this->_path.DIRECTORY_SEPARATOR.'instance_2';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        mkdir($this->_path_instance_1);
        mkdir($this->_path_instance_1.DIRECTORY_SEPARATOR.'cfg');
        mkdir($this->_path_instance_2);
        mkdir($this->_path_instance_2.DIRECTORY_SEPARATOR.'cfg');
        $options = parse_ini_file(CONF_SAMPLE, true);
        $options['purge']['limit'] = 0;
        $options['model_options']['dir'] = $this->_path_instance_1.DIRECTORY_SEPARATOR.'data';
        $this->_model_1 = new Filesystem($options['model_options']);
        Helper::createIniFile($this->_path_instance_1.DIRECTORY_SEPARATOR.'cfg'.DIRECTORY_SEPARATOR.'conf.php', $options);

        $options['model'] = [
            'class' => 'Database',
        ];
        $options['model_options'] = [
            'dsn' => 'sqlite:'.$this->_path_instance_2.DIRECTORY_SEPARATOR.'test.sq3',
            'usr' => null,
            'pwd' => null,
            'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        ];
        $this->_model_2 = new Database($options['model_options']);
        Helper::createIniFile($this->_path_instance_2.DIRECTORY_SEPARATOR.'cfg'.DIRECTORY_SEPARATOR.'conf.php', $options);
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        Helper::rmDir($this->_path);
    }

    public function testMigrate(): void
    {
        $this->_model_1->delete(Helper::getPasteId());
        $this->_model_2->delete(Helper::getPasteId());

        // storing paste & comment
        $this->_model_1->create(Helper::getPasteId(), Helper::getPaste());
        $this->_model_1->createComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId(), Helper::getComment());

        // migrate files to database
        $output = null;
        $exit_code = 255;
        exec('php '.PATH.'bin'.DIRECTORY_SEPARATOR.'migrate --delete-after '.$this->_path_instance_1.DIRECTORY_SEPARATOR.'cfg '.$this->_path_instance_2.DIRECTORY_SEPARATOR.'cfg', $output, $exit_code);
        static::assertSame(0, $exit_code, 'migrate script exits 0');
        static::assertFalse($this->_model_1->exists(Helper::getPasteId()), 'paste removed after migrating it');
        static::assertFalse($this->_model_1->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment removed after migrating it');
        static::assertTrue($this->_model_2->exists(Helper::getPasteId()), 'paste migrated');
        static::assertTrue($this->_model_2->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment migrated');

        // migrate back to files
        $exit_code = 255;
        exec('php '.PATH.'bin'.DIRECTORY_SEPARATOR.'migrate '.$this->_path_instance_2.DIRECTORY_SEPARATOR.'cfg '.$this->_path_instance_1.DIRECTORY_SEPARATOR.'cfg', $output, $exit_code);
        static::assertSame(0, $exit_code, 'migrate script exits 0');
        static::assertTrue($this->_model_1->exists(Helper::getPasteId()), 'paste migrated back');
        static::assertTrue($this->_model_1->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment migrated back');
    }
}
