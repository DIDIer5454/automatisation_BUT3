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
use PrivateBin\Persistence\TrafficLimiter;

/**
 * @internal
 * @coversNothing
 */
final class TrafficLimiterTest extends TestCase
{
    private $_path;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'trafficlimit';
        $store = new Filesystem(['dir' => $this->_path]);
        ServerSalt::setStore($store);
        TrafficLimiter::setStore($store);
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        Helper::rmDir($this->_path.DIRECTORY_SEPARATOR);
    }

    public function testHtaccess(): void
    {
        $htaccess = $this->_path.DIRECTORY_SEPARATOR.'.htaccess';
        @unlink($htaccess);
        $_SERVER['REMOTE_ADDR'] = 'foobar';
        TrafficLimiter::canPass();
        static::assertFileExists($htaccess, 'htaccess recreated');
    }

    public function testTrafficGetsLimited(): void
    {
        TrafficLimiter::setLimit(4);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        static::assertTrue(TrafficLimiter::canPass(), 'first request may pass');
        sleep(1);

        try {
            static::assertFalse(TrafficLimiter::canPass(), 'expected an exception');
        } catch (Exception $e) {
            static::assertSame($e->getMessage(), 'Please wait 4 seconds between each post.', 'second request is to fast, may not pass');
        }
        sleep(4);
        static::assertTrue(TrafficLimiter::canPass(), 'third request waited long enough and may pass');
        $_SERVER['REMOTE_ADDR'] = '2001:1620:2057:dead:beef::cafe:babe';
        static::assertTrue(TrafficLimiter::canPass(), 'fourth request has different ip and may pass');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            static::assertFalse(TrafficLimiter::canPass(), 'expected an exception');
        } catch (Exception $e) {
            static::assertSame($e->getMessage(), 'Please wait 4 seconds between each post.', 'fifth request is to fast, may not pass');
        }
    }

    public function testTrafficLimitExempted(): void
    {
        TrafficLimiter::setExempted('1.2.3.4,10.10.10/24,2001:1620:2057::/48');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        static::assertTrue(TrafficLimiter::canPass(), 'first request may pass');

        try {
            static::assertFalse(TrafficLimiter::canPass(), 'expected an exception');
        } catch (Exception $e) {
            static::assertSame($e->getMessage(), 'Please wait 4 seconds between each post.', 'not exempted');
        }
        $_SERVER['REMOTE_ADDR'] = '10.10.10.10';
        static::assertTrue(TrafficLimiter::canPass(), 'IPv4 in exempted range');
        static::assertTrue(TrafficLimiter::canPass(), 'request is to fast, but IPv4 in exempted range');
        $_SERVER['REMOTE_ADDR'] = '2001:1620:2057:dead:beef::cafe:babe';
        static::assertTrue(TrafficLimiter::canPass(), 'IPv6 in exempted range');
        static::assertTrue(TrafficLimiter::canPass(), 'request is to fast, but IPv6 in exempted range');
        TrafficLimiter::setExempted('127.*,foobar');
        static::assertTrue(TrafficLimiter::canPass(), 'first cached request may pass');

        try {
            static::assertFalse(TrafficLimiter::canPass(), 'expected an exception');
        } catch (Exception $e) {
            static::assertSame($e->getMessage(), 'Please wait 4 seconds between each post.', 'request is too fast, invalid range');
        }
        $_SERVER['REMOTE_ADDR'] = 'foobar';
        static::assertTrue(TrafficLimiter::canPass(), 'non-IP address');
        static::assertTrue(TrafficLimiter::canPass(), 'request is too fast, but non-IP address matches exempted range');
    }

    public function testTrafficLimitCreators(): void
    {
        TrafficLimiter::setCreators('1.2.3.4,10.10.10/24,2001:1620:2057::/48');
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            static::assertFalse(TrafficLimiter::canPass(), 'expected an exception');
        } catch (Exception $e) {
            static::assertSame($e->getMessage(), 'Your IP is not authorized to create pastes.', 'not a creator');
        }
        $_SERVER['REMOTE_ADDR'] = '10.10.10.10';
        static::assertTrue(TrafficLimiter::canPass(), 'IPv4 in creator range');
        static::assertTrue(TrafficLimiter::canPass(), 'request is too fast, but IPv4 in creator range');
        $_SERVER['REMOTE_ADDR'] = '2001:1620:2057:dead:beef::cafe:babe';
        static::assertTrue(TrafficLimiter::canPass(), 'IPv6 in creator range');
        static::assertTrue(TrafficLimiter::canPass(), 'request is too fast, but IPv6 in creator range');
        TrafficLimiter::setCreators('127.*,foobar');

        try {
            static::assertFalse(TrafficLimiter::canPass(), 'expected an exception');
        } catch (Exception $e) {
            static::assertSame($e->getMessage(), 'Your IP is not authorized to create pastes.', 'request is to fast, not a creator');
        }
        $_SERVER['REMOTE_ADDR'] = 'foobar';
        static::assertTrue(TrafficLimiter::canPass(), 'non-IP address');
        static::assertTrue(TrafficLimiter::canPass(), 'request is to fast, but non-IP address matches creator');
    }
}
