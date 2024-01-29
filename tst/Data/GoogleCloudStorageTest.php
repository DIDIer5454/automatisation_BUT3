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
use PHPUnit\Framework\TestCase;
use PrivateBin\Data\GoogleCloudStorage;

/**
 * @internal
 * @coversNothing
 */
final class GoogleCloudStorageTest extends TestCase
{
    private static $_client;
    private static $_bucket;

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

    public static function tearDownAfterClass(): void
    {
        self::$_bucket->delete();
    }

    protected function setUp(): void
    {
        ini_set('error_log', stream_get_meta_data(tmpfile())['uri']);
        $this->_model = new GoogleCloudStorage(
            [
                'bucket' => self::$_bucket->name(),
                'prefix' => 'pastes',
            ]
        );
    }

    protected function tearDown(): void
    {
        foreach (self::$_bucket->objects() as $object) {
            $object->delete();
        }
    }

    public function testFileBasedDataStoreWorks(): void
    {
        $this->_model->delete(Helper::getPasteId());

        // storing pastes
        $paste = Helper::getPaste(2, ['expire_date' => 1344803344]);
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste does not yet exist');
        static::assertTrue($this->_model->create(Helper::getPasteId(), $paste), 'store new paste');
        static::assertTrue($this->_model->exists(Helper::getPasteId()), 'paste exists after storing it');
        static::assertFalse($this->_model->create(Helper::getPasteId(), $paste), 'unable to store the same paste twice');
        static::assertSame($paste, $this->_model->read(Helper::getPasteId()));

        // storing comments
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment does not yet exist');
        static::assertTrue($this->_model->createComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId(), Helper::getComment()), 'store comment');
        static::assertTrue($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment exists after storing it');
        static::assertFalse($this->_model->createComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId(), Helper::getComment()), 'unable to store the same comment twice');
        $comment = Helper::getComment();
        $comment['id'] = Helper::getCommentId();
        $comment['parentid'] = Helper::getPasteId();
        static::assertSame(
            [$comment['meta']['created'] => $comment],
            $this->_model->readComments(Helper::getPasteId())
        );

        // deleting pastes
        $this->_model->delete(Helper::getPasteId());
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste successfully deleted');
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment was deleted with paste');
        static::assertFalse($this->_model->read(Helper::getPasteId()), 'paste can no longer be found');
    }

    /**
     * pastes a-g are expired and should get deleted, x never expires and y-z expire in an hour.
     */
    public function testPurge(): void
    {
        $expired = Helper::getPaste(2, ['expire_date' => 1344803344]);
        $paste = Helper::getPaste(2, ['expire_date' => time() + 3600]);
        $keys = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'x', 'y', 'z'];
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = hash('fnv164', $key);
            static::assertFalse($this->_model->exists($ids[$key]), "paste {$key} does not yet exist");
            if (in_array($key, ['x', 'y', 'z'], true)) {
                static::assertTrue($this->_model->create($ids[$key], $paste), "store {$key} paste");
            } elseif ('x' === $key) {
                static::assertTrue($this->_model->create($ids[$key], Helper::getPaste()), "store {$key} paste");
            } else {
                static::assertTrue($this->_model->create($ids[$key], $expired), "store {$key} paste");
            }
            static::assertTrue($this->_model->exists($ids[$key]), "paste {$key} exists after storing it");
        }
        $this->_model->purge(10);
        foreach ($ids as $key => $id) {
            if (in_array($key, ['x', 'y', 'z'], true)) {
                static::assertTrue($this->_model->exists($id), "paste {$key} exists after purge");
                $this->_model->delete($id);
            } else {
                static::assertFalse($this->_model->exists($id), "paste {$key} was purged");
            }
        }
    }

    public function testErrorDetection(): void
    {
        $this->_model->delete(Helper::getPasteId());
        $paste = Helper::getPaste(2, ['expire' => "Invalid UTF-8 sequence: \xB1\x31"]);
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste does not yet exist');
        static::assertFalse($this->_model->create(Helper::getPasteId(), $paste), 'unable to store broken paste');
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste does still not exist');
    }

    public function testCommentErrorDetection(): void
    {
        $this->_model->delete(Helper::getPasteId());
        $comment = Helper::getComment(1, ['nickname' => "Invalid UTF-8 sequence: \xB1\x31"]);
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste does not yet exist');
        static::assertTrue($this->_model->create(Helper::getPasteId(), Helper::getPaste()), 'store new paste');
        static::assertTrue($this->_model->exists(Helper::getPasteId()), 'paste exists after storing it');
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment does not yet exist');
        static::assertFalse($this->_model->createComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId(), $comment), 'unable to store broken comment');
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment does still not exist');
    }

    /**
     * @throws Exception
     */
    public function testKeyValueStore(): void
    {
        $salt = bin2hex(random_bytes(256));
        $this->_model->setValue($salt, 'salt', '');
        $storedSalt = $this->_model->getValue('salt', '');
        static::assertSame($salt, $storedSalt);
        $this->_model->purgeValues('salt', time() + 60);
        static::assertSame('', $this->_model->getValue('salt', 'master'));

        $client = hash_hmac('sha512', '127.0.0.1', $salt);
        $expire = time();
        $this->_model->setValue((string) $expire, 'traffic_limiter', $client);
        $storedExpired = $this->_model->getValue('traffic_limiter', $client);
        static::assertSame((string) $expire, $storedExpired);

        $this->_model->purgeValues('traffic_limiter', time() - 60);
        static::assertSame($storedExpired, $this->_model->getValue('traffic_limiter', $client));
        $this->_model->purgeValues('traffic_limiter', time() + 60);
        static::assertSame('', $this->_model->getValue('traffic_limiter', $client));

        $purgeAt = $expire + (15 * 60);
        $this->_model->setValue((string) $purgeAt, 'purge_limiter', '');
        $storedPurgedAt = $this->_model->getValue('purge_limiter', '');
        static::assertSame((string) $purgeAt, $storedPurgedAt);
        $this->_model->purgeValues('purge_limiter', $purgeAt + 60);
        static::assertSame('', $this->_model->getValue('purge_limiter', ''));
        static::assertSame('', $this->_model->getValue('purge_limiter', 'at'));
    }

    /**
     * @throws Exception
     */
    public function testKeyValuePurgeTrafficLimiter(): void
    {
        $salt = bin2hex(random_bytes(256));
        $client = hash_hmac('sha512', '127.0.0.1', $salt);
        $expire = time();
        $this->_model->setValue((string) $expire, 'traffic_limiter', $client);
        $storedExpired = $this->_model->getValue('traffic_limiter', $client);
        static::assertSame((string) $expire, $storedExpired);

        $this->_model->purgeValues('traffic_limiter', time() - 60);
        static::assertSame($storedExpired, $this->_model->getValue('traffic_limiter', $client));

        $this->_model->purgeValues('traffic_limiter', time() + 60);
        static::assertSame('', $this->_model->getValue('traffic_limiter', $client));
    }
}
