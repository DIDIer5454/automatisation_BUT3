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
use PrivateBin\Persistence\PurgeLimiter;

/**
 * @internal
 * @coversNothing
 */
final class PurgeLimiterTest extends TestCase
{
    private $_path;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        PurgeLimiter::setStore(
            new Filesystem(['dir' => $this->_path])
        );
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        Helper::rmDir($this->_path);
    }

    public function testLimit(): void
    {
        // initialize it
        PurgeLimiter::setLimit(1);
        PurgeLimiter::canPurge();

        // try setting it
        static::assertFalse(PurgeLimiter::canPurge());
        sleep(2);
        static::assertTrue(PurgeLimiter::canPurge());

        // disable it
        PurgeLimiter::setLimit(0);
        PurgeLimiter::canPurge();
        static::assertTrue(PurgeLimiter::canPurge());
    }
}
