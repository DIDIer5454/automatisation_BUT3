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
use PrivateBin\FormatV2;

/**
 * @internal
 * @coversNothing
 */
final class FormatV2Test extends TestCase
{
    public function testFormatV2ValidatorValidatesCorrectly(): void
    {
        static::assertTrue(FormatV2::isValid(Helper::getPastePost()), 'valid format');
        static::assertTrue(FormatV2::isValid(Helper::getCommentPost(), true), 'valid format');

        $paste = Helper::getPastePost();
        $paste['adata'][0][0] = '$';
        static::assertFalse(FormatV2::isValid($paste), 'invalid base64 encoding of iv');

        $paste = Helper::getPastePost();
        $paste['adata'][0][1] = '$';
        static::assertFalse(FormatV2::isValid($paste), 'invalid base64 encoding of salt');

        $paste = Helper::getPastePost();
        $paste['ct'] = '$';
        static::assertFalse(FormatV2::isValid($paste), 'invalid base64 encoding of ct');

        $paste = Helper::getPastePost();
        $paste['ct'] = 'bm9kYXRhbm9kYXRhbm9kYXRhbm9kYXRhbm9kYXRhCg==';
        static::assertFalse(FormatV2::isValid($paste), 'low ct entropy');

        $paste = Helper::getPastePost();
        $paste['adata'][0][0] = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTA=';
        static::assertFalse(FormatV2::isValid($paste), 'iv too long');

        $paste = Helper::getPastePost();
        $paste['adata'][0][1] = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTA=';
        static::assertFalse(FormatV2::isValid($paste), 'salt too long');

        $paste = Helper::getPastePost();
        $paste['foo'] = 'bar';
        static::assertFalse(FormatV2::isValid($paste), 'invalid additional key');
        unset($paste['meta']);
        static::assertFalse(FormatV2::isValid($paste), 'invalid missing key');

        $paste = Helper::getPastePost();
        $paste['v'] = 0.9;
        static::assertFalse(FormatV2::isValid($paste), 'unsupported version');

        $paste = Helper::getPastePost();
        $paste['adata'][0][2] = 1000;
        static::assertFalse(FormatV2::isValid($paste), 'not enough iterations');

        $paste = Helper::getPastePost();
        $paste['adata'][0][3] = 127;
        static::assertFalse(FormatV2::isValid($paste), 'invalid key size');

        $paste = Helper::getPastePost();
        $paste['adata'][0][4] = 63;
        static::assertFalse(FormatV2::isValid($paste), 'invalid tag length');

        $paste = Helper::getPastePost();
        $paste['adata'][0][5] = '!#@';
        static::assertFalse(FormatV2::isValid($paste), 'invalid algorithm');

        $paste = Helper::getPastePost();
        $paste['adata'][0][6] = '!#@';
        static::assertFalse(FormatV2::isValid($paste), 'invalid mode');

        $paste = Helper::getPastePost();
        $paste['adata'][0][7] = '!#@';
        static::assertFalse(FormatV2::isValid($paste), 'invalid compression');

        static::assertFalse(FormatV2::isValid(Helper::getPaste()), 'invalid meta key');
    }
}
