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
use PrivateBin\Controller;
use PrivateBin\Data\Database;
use PrivateBin\Data\Filesystem;
use PrivateBin\Persistence\ServerSalt;

/**
 * @internal
 * @coversNothing
 */
final class DatabaseTest extends TestCase
{
    private $_model;

    private $_path;

    private $_options = [
        'dsn' => 'sqlite::memory:',
        'usr' => null,
        'pwd' => null,
        'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    ];

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        $this->_model = new Database($this->_options);
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        if (is_dir($this->_path)) {
            Helper::rmDir($this->_path);
        }
    }

    public function testSaltMigration(): void
    {
        ServerSalt::setStore(new Filesystem(['dir' => 'data']));
        $salt = ServerSalt::get();
        $file = 'data'.DIRECTORY_SEPARATOR.'salt.php';
        static::assertFileExists($file, 'ServerSalt got initialized and stored on disk');
        static::assertNotSame($salt, '');
        ServerSalt::setStore($this->_model);
        ServerSalt::get();
        static::assertFileDoesNotExist($file, 'legacy ServerSalt got removed');
        static::assertSame($salt, ServerSalt::get(), 'ServerSalt got preserved & migrated');
    }

    public function testDatabaseBasedDataStoreWorks(): void
    {
        $this->_model->delete(Helper::getPasteId());

        // storing pastes
        $paste = Helper::getPaste();
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste does not yet exist');
        static::assertTrue($this->_model->create(Helper::getPasteId(), $paste), 'store new paste');
        static::assertTrue($this->_model->exists(Helper::getPasteId()), 'paste exists after storing it');
        static::assertFalse($this->_model->create(Helper::getPasteId(), $paste), 'unable to store the same paste twice');
        static::assertSame($paste, $this->_model->read(Helper::getPasteId()));

        // storing comments
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'v1 comment does not yet exist');
        static::assertTrue(false !== $this->_model->createComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId(), Helper::getComment(1)), 'store v1 comment');
        static::assertTrue($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'v1 comment exists after storing it');
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getPasteId()), 'v2 comment does not yet exist');
        static::assertTrue(false !== $this->_model->createComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getPasteId(), Helper::getComment(2)), 'store v2 comment');
        static::assertTrue($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getPasteId()), 'v2 comment exists after storing it');
        $comment1 = Helper::getComment(1);
        $comment1['id'] = Helper::getCommentId();
        $comment1['parentid'] = Helper::getPasteId();
        $comment2 = Helper::getComment(2);
        $comment2['id'] = Helper::getPasteId();
        $comment2['parentid'] = Helper::getPasteId();
        static::assertSame(
            [
                $comment1['meta']['postdate'] => $comment1,
                $comment2['meta']['created'].'.1' => $comment2,
            ],
            $this->_model->readComments(Helper::getPasteId())
        );

        // deleting pastes
        $this->_model->delete(Helper::getPasteId());
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste successfully deleted');
        static::assertFalse($this->_model->existsComment(Helper::getPasteId(), Helper::getPasteId(), Helper::getCommentId()), 'comment was deleted with paste');
        static::assertFalse($this->_model->read(Helper::getPasteId()), 'paste can no longer be found');
    }

    public function testDatabaseBasedAttachmentStoreWorks(): void
    {
        // this assumes a version 1 formatted paste
        $this->_model->delete(Helper::getPasteId());
        $original = $paste = Helper::getPasteWithAttachment(1, ['expire_date' => 1344803344]);
        $paste['meta']['burnafterreading'] = $original['meta']['burnafterreading'] = true;
        $paste['meta']['attachment'] = $paste['attachment'];
        $paste['meta']['attachmentname'] = $paste['attachmentname'];
        unset($paste['attachment'], $paste['attachmentname']);
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste does not yet exist');
        static::assertTrue($this->_model->create(Helper::getPasteId(), $paste), 'store new paste');
        static::assertTrue($this->_model->exists(Helper::getPasteId()), 'paste exists after storing it');
        static::assertFalse($this->_model->create(Helper::getPasteId(), $paste), 'unable to store the same paste twice');
        static::assertSame($original, $this->_model->read(Helper::getPasteId()));
    }

    /**
     * pastes a-g are expired and should get deleted, x never expires and y-z expire in an hour.
     */
    public function testPurge(): void
    {
        $this->_model->delete(Helper::getPasteId());
        $expired = Helper::getPaste(2, ['expire_date' => 1344803344]);
        $paste = Helper::getPaste(2, ['expire_date' => time() + 3600]);
        $keys = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'x', 'y', 'z'];
        $ids = [];
        foreach ($keys as $key) {
            $ids[$key] = hash('fnv164', $key);
            $this->_model->delete($ids[$key]);
            static::assertFalse($this->_model->exists($ids[$key]), "paste {$key} does not yet exist");
            if (in_array($key, ['y', 'z'], true)) {
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

    public function testGetIbmInstance(): void
    {
        $this->expectException(PDOException::class);
        new Database(
            [
                'dsn' => 'ibm:', 'usr' => null, 'pwd' => null,
                'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            ]
        );
    }

    public function testGetInformixInstance(): void
    {
        $this->expectException(PDOException::class);
        new Database(
            [
                'dsn' => 'informix:', 'usr' => null, 'pwd' => null,
                'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            ]
        );
    }

    public function testGetMssqlInstance(): void
    {
        $this->expectException(PDOException::class);
        new Database(
            [
                'dsn' => 'mssql:', 'usr' => null, 'pwd' => null,
                'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            ]
        );
    }

    public function testGetMysqlInstance(): void
    {
        $this->expectException(PDOException::class);
        new Database(
            [
                'dsn' => 'mysql:', 'usr' => null, 'pwd' => null,
                'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            ]
        );
    }

    public function testGetOciInstance(): void
    {
        $this->expectException(PDOException::class);
        new Database(
            [
                'dsn' => 'oci:', 'usr' => null, 'pwd' => null,
                'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            ]
        );
    }

    public function testGetPgsqlInstance(): void
    {
        $this->expectException(PDOException::class);
        new Database(
            [
                'dsn' => 'pgsql:', 'usr' => null, 'pwd' => null,
                'opt' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            ]
        );
    }

    public function testGetFooInstance(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(5);
        new Database(
            [
                'dsn' => 'foo:', 'usr' => null, 'pwd' => null, 'opt' => null,
            ]
        );
    }

    public function testMissingDsn(): void
    {
        $options = $this->_options;
        unset($options['dsn']);
        $this->expectException(Exception::class);
        $this->expectExceptionCode(6);
        new Database($options);
    }

    public function testMissingUsr(): void
    {
        $options = $this->_options;
        unset($options['usr']);
        $this->expectException(Exception::class);
        $this->expectExceptionCode(6);
        new Database($options);
    }

    public function testMissingPwd(): void
    {
        $options = $this->_options;
        unset($options['pwd']);
        $this->expectException(Exception::class);
        $this->expectExceptionCode(6);
        new Database($options);
    }

    public function testMissingOpt(): void
    {
        $options = $this->_options;
        unset($options['opt']);
        $this->expectException(Exception::class);
        $this->expectExceptionCode(6);
        new Database($options);
    }

    public function testOldAttachments(): void
    {
        mkdir($this->_path);
        $path = $this->_path.DIRECTORY_SEPARATOR.'attachement-test.sq3';
        if (is_file($path)) {
            unlink($path);
        }
        $this->_options['dsn'] = 'sqlite:'.$path;
        $this->_options['tbl'] = 'bar_';
        $model = new Database($this->_options);

        $original = $paste = Helper::getPasteWithAttachment(1, ['expire_date' => 1344803344]);
        $meta = $paste['meta'];
        $meta['attachment'] = $paste['attachment'];
        $meta['attachmentname'] = $paste['attachmentname'];
        unset($paste['attachment'], $paste['attachmentname']);

        $db = new PDO(
            $this->_options['dsn'],
            $this->_options['usr'],
            $this->_options['pwd'],
            $this->_options['opt']
        );
        $statement = $db->prepare('INSERT INTO bar_paste VALUES(?,?,?,?,?,?,?,?,?)');
        $statement->execute(
            [
                Helper::getPasteId(),
                $paste['data'],
                $paste['meta']['postdate'],
                $paste['meta']['expire_date'],
                0,
                0,
                json_encode($meta),
                null,
                null,
            ]
        );
        $statement->closeCursor();

        static::assertTrue($model->exists(Helper::getPasteId()), 'paste exists after storing it');
        static::assertSame($original, $model->read(Helper::getPasteId()));

        Helper::rmDir($this->_path);
    }

    public function testCorruptMeta(): void
    {
        mkdir($this->_path);
        $path = $this->_path.DIRECTORY_SEPARATOR.'meta-test.sq3';
        if (is_file($path)) {
            unlink($path);
        }
        $this->_options['dsn'] = 'sqlite:'.$path;
        $this->_options['tbl'] = 'baz_';
        $model = new Database($this->_options);
        $paste = Helper::getPaste(1, ['expire_date' => 1344803344]);
        unset($paste['meta']['formatter'], $paste['meta']['opendiscussion'], $paste['meta']['salt']);
        $model->delete(Helper::getPasteId());

        $db = new PDO(
            $this->_options['dsn'],
            $this->_options['usr'],
            $this->_options['pwd'],
            $this->_options['opt']
        );
        $statement = $db->prepare('INSERT INTO baz_paste VALUES(?,?,?,?,?,?,?,?,?)');
        $statement->execute(
            [
                Helper::getPasteId(),
                $paste['data'],
                $paste['meta']['postdate'],
                $paste['meta']['expire_date'],
                0,
                0,
                '{',
                null,
                null,
            ]
        );
        $statement->closeCursor();

        static::assertTrue($model->exists(Helper::getPasteId()), 'paste exists after storing it');
        static::assertSame($paste, $model->read(Helper::getPasteId()));

        Helper::rmDir($this->_path);
    }

    public function testTableUpgrade(): void
    {
        mkdir($this->_path);
        $path = $this->_path.DIRECTORY_SEPARATOR.'db-test.sq3';
        if (is_file($path)) {
            unlink($path);
        }
        $this->_options['dsn'] = 'sqlite:'.$path;
        $this->_options['tbl'] = 'foo_';
        $db = new PDO(
            $this->_options['dsn'],
            $this->_options['usr'],
            $this->_options['pwd'],
            $this->_options['opt']
        );
        $db->exec(
            'CREATE TABLE foo_paste ( '.
            'dataid CHAR(16), '.
            'data TEXT, '.
            'postdate INT, '.
            'expiredate INT, '.
            'opendiscussion INT, '.
            'burnafterreading INT );'
        );
        $db->exec(
            'CREATE TABLE foo_comment ( '.
            'dataid CHAR(16) NOT NULL, '.
            'pasteid CHAR(16), '.
            'parentid CHAR(16), '.
            'data BLOB, '.
            'nickname BLOB, '.
            'vizhash BLOB, '.
            'postdate INT );'
        );
        static::assertInstanceOf('PrivateBin\\Data\\Database', new Database($this->_options));

        // check if version number was upgraded in created configuration table
        $statement = $db->prepare('SELECT value FROM foo_config WHERE id LIKE ?');
        $statement->execute(['VERSION']);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        static::assertSame(Controller::VERSION, $result['value']);
        Helper::rmDir($this->_path);
    }

    public function testOciClob(): void
    {
        $int = (int) random_bytes(1);
        $string = random_bytes(10);
        $clob = fopen('php://memory', 'r+');
        fwrite($clob, $string);
        rewind($clob);
        static::assertSame($int, Database::_sanitizeClob($int));
        static::assertSame($string, Database::_sanitizeClob($string));
        static::assertSame($string, Database::_sanitizeClob($clob));
    }
}
