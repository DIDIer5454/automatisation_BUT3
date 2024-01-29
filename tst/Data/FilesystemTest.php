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

/**
 * @internal
 * @coversNothing
 */
final class FilesystemTest extends TestCase
{
    private $_model;

    private $_path;

    private $_invalidPath;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        $this->_invalidPath = $this->_path.DIRECTORY_SEPARATOR.'bar';
        $this->_model = new Filesystem(['dir' => $this->_path]);
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        if (!is_dir($this->_invalidPath)) {
            mkdir($this->_invalidPath);
        }
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        chmod($this->_invalidPath, 0700);
        Helper::rmDir($this->_path);
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

    public function testFileBasedAttachmentStoreWorks(): void
    {
        $this->_model->delete(Helper::getPasteId());
        $original = $paste = Helper::getPasteWithAttachment(1, ['expire_date' => 1344803344]);
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
        mkdir($this->_path.DIRECTORY_SEPARATOR.'00', 0777, true);
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
        static::assertFalse($this->_model->setValue('foo', 'non existing namespace'), 'rejects setting value in non existing namespace');
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

    public function testOldFilesGetConverted(): void
    {
        // generate 10 (default purge batch size) pastes in the old format
        $paste = Helper::getPaste();
        $comment = Helper::getComment();
        $commentid = Helper::getCommentId();
        $ids = [];
        for ($i = 0, $max = 10; $i < $max; ++$i) {
            // PHPs mt_rand only supports 32 bit or up 0x7fffffff on 64 bit systems to be precise :-/
            $dataid = str_pad(dechex(random_int(0, mt_getrandmax())), 8, '0', STR_PAD_LEFT).
                str_pad(dechex(random_int(0, mt_getrandmax())), 8, '0', STR_PAD_LEFT);
            $storagedir = $this->_path.DIRECTORY_SEPARATOR.substr($dataid, 0, 2).
                DIRECTORY_SEPARATOR.substr($dataid, 2, 2).DIRECTORY_SEPARATOR;
            $ids[$dataid] = $storagedir;

            if (!is_dir($storagedir)) {
                mkdir($storagedir, 0700, true);
            }
            file_put_contents($storagedir.$dataid, json_encode($paste));

            $storagedir .= $dataid.'.discussion'.DIRECTORY_SEPARATOR;
            if (!is_dir($storagedir)) {
                mkdir($storagedir, 0700, true);
            }
            file_put_contents($storagedir.$dataid.'.'.$commentid.'.'.$dataid, json_encode($comment));
        }
        // check that all 10 pastes were converted after the purge
        $this->_model->purge(10);
        foreach ($ids as $dataid => $storagedir) {
            static::assertFileExists($storagedir.$dataid.'.php', "paste {$dataid} exists in new format");
            static::assertFileDoesNotExist($storagedir.$dataid, "old format paste {$dataid} got removed");
            static::assertTrue($this->_model->exists($dataid), "paste {$dataid} exists");
            static::assertSame($this->_model->read($dataid), $paste, "paste {$dataid} wasn't modified in the conversion");

            $storagedir .= $dataid.'.discussion'.DIRECTORY_SEPARATOR;
            static::assertFileExists($storagedir.$dataid.'.'.$commentid.'.'.$dataid.'.php', "comment of {$dataid} exists in new format");
            static::assertFileDoesNotExist($storagedir.$dataid.'.'.$commentid.'.'.$dataid, "old format comment of {$dataid} got removed");
            static::assertTrue($this->_model->existsComment($dataid, $dataid, $commentid), "comment in paste {$dataid} exists");
            $comment = $comment;
            $comment['id'] = $commentid;
            $comment['parentid'] = $dataid;
            static::assertSame($this->_model->readComments($dataid), [$comment['meta']['created'] => $comment], "comment of {$dataid} wasn't modified in the conversion");
        }
    }
}
