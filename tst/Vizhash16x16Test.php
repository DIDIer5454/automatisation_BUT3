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
use PrivateBin\Vizhash16x16;

/**
 * @internal
 * @coversNothing
 */
final class Vizhash16x16Test extends TestCase
{
    private $_file;

    private $_path;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        $this->_file = $this->_path.DIRECTORY_SEPARATOR.'vizhash.png';
        ServerSalt::setStore(new Filesystem(['dir' => $this->_path]));
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        chmod($this->_path, 0700);
        Helper::rmDir($this->_path);
    }

    public function testVizhashGeneratesUniquePngsPerIp(): void
    {
        $vz = new Vizhash16x16();
        $pngdata = $vz->generate(hash('sha512', '127.0.0.1'));
        file_put_contents($this->_file, $pngdata);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        static::assertSame('image/png', $finfo->file($this->_file));
        static::assertNotSame($pngdata, $vz->generate(hash('sha512', '2001:1620:2057:dead:beef::cafe:babe')));
        static::assertSame($pngdata, $vz->generate(hash('sha512', '127.0.0.1')));
    }
}
