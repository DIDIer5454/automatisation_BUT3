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
use PrivateBin\Data\Filesystem;
use PrivateBin\Persistence\ServerSalt;

/**
 * @internal
 * @coversNothing
 */
final class ServerSaltTest extends TestCase
{
    private $_path;

    private $_invalidPath;

    private $_otherPath;

    private $_invalidFile;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        ServerSalt::setStore(
            new Filesystem(['dir' => $this->_path])
        );

        $this->_otherPath = $this->_path.DIRECTORY_SEPARATOR.'foo';

        $this->_invalidPath = $this->_path.DIRECTORY_SEPARATOR.'bar';
        if (!is_dir($this->_invalidPath)) {
            mkdir($this->_invalidPath);
        }
        $this->_invalidFile = $this->_invalidPath.DIRECTORY_SEPARATOR.'salt.php';
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        chmod($this->_invalidPath, 0700);
        Helper::rmDir($this->_path);
    }

    public function testGeneration(): void
    {
        // generating new salt
        ServerSalt::setStore(
            new Filesystem(['dir' => $this->_path])
        );
        $salt = ServerSalt::get();

        // try setting a different path and resetting it
        ServerSalt::setStore(
            new Filesystem(['dir' => $this->_otherPath])
        );
        static::assertNotSame($salt, ServerSalt::get());
        ServerSalt::setStore(
            new Filesystem(['dir' => $this->_path])
        );
        static::assertSame($salt, ServerSalt::get());
    }

    public function testPathShenanigans(): void
    {
        // try setting an invalid path
        chmod($this->_invalidPath, 0000);
        $store = new Filesystem(['dir' => $this->_invalidPath]);
        ServerSalt::setStore($store);
        $salt = ServerSalt::get();
        ServerSalt::setStore($store);
        static::assertNotSame($salt, ServerSalt::get());
    }

    public function testFileRead(): void
    {
        // try setting an invalid file
        chmod($this->_invalidPath, 0700);
        file_put_contents($this->_invalidFile, '');
        chmod($this->_invalidFile, 0000);
        $store = new Filesystem(['dir' => $this->_invalidPath]);
        ServerSalt::setStore($store);
        $salt = ServerSalt::get();
        ServerSalt::setStore($store);
        static::assertNotSame($salt, ServerSalt::get());
    }

    public function testFileWrite(): void
    {
        // try setting an invalid file
        chmod($this->_invalidPath, 0700);
        if (is_file($this->_invalidFile)) {
            chmod($this->_invalidFile, 0600);
            unlink($this->_invalidFile);
        }
        file_put_contents($this->_invalidPath.DIRECTORY_SEPARATOR.'.htaccess', '');
        chmod($this->_invalidPath, 0500);
        $store = new Filesystem(['dir' => $this->_invalidPath]);
        ServerSalt::setStore($store);
        $salt = ServerSalt::get();
        ServerSalt::setStore($store);
        static::assertNotSame($salt, ServerSalt::get());
    }

    public function testPermissionShenanigans(): void
    {
        // try creating an invalid path
        chmod($this->_invalidPath, 0000);
        ServerSalt::setStore(
            new Filesystem(['dir' => $this->_invalidPath.DIRECTORY_SEPARATOR.'baz'])
        );
        $store = new Filesystem(['dir' => $this->_invalidPath]);
        ServerSalt::setStore($store);
        $salt = ServerSalt::get();
        ServerSalt::setStore($store);
        static::assertNotSame($salt, ServerSalt::get());
    }
}
