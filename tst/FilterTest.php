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
use PrivateBin\Filter;

/**
 * @internal
 * @coversNothing
 */
final class FilterTest extends TestCase
{
    public function testFilterMakesTimesHumanlyReadable(): void
    {
        static::assertSame('5 minutes', Filter::formatHumanReadableTime('5min'));
        static::assertSame('90 seconds', Filter::formatHumanReadableTime('90sec'));
        static::assertSame('1 week', Filter::formatHumanReadableTime('1week'));
        static::assertSame('6 months', Filter::formatHumanReadableTime('6months'));
    }

    public function testFilterFailTimesHumanlyReadable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(30);
        Filter::formatHumanReadableTime('five_minutes');
    }

    public function testFilterMakesSizesHumanlyReadable(): void
    {
        static::assertSame('1 B', Filter::formatHumanReadableSize(1));
        static::assertSame('1 000 B', Filter::formatHumanReadableSize(1000));
        static::assertSame('1.00 KiB', Filter::formatHumanReadableSize(1024));
        static::assertSame('1.21 KiB', Filter::formatHumanReadableSize(1234));
        $exponent = 1024;
        static::assertSame('1 000.00 KiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 MiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 MiB', Filter::formatHumanReadableSize(1234 * $exponent));
        $exponent *= 1024;
        static::assertSame('1 000.00 MiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 GiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 GiB', Filter::formatHumanReadableSize(1234 * $exponent));
        $exponent *= 1024;
        static::assertSame('1 000.00 GiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 TiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 TiB', Filter::formatHumanReadableSize(1234 * $exponent));
        $exponent *= 1024;
        static::assertSame('1 000.00 TiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 PiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 PiB', Filter::formatHumanReadableSize(1234 * $exponent));
        $exponent *= 1024;
        static::assertSame('1 000.00 PiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 EiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 EiB', Filter::formatHumanReadableSize(1234 * $exponent));
        $exponent *= 1024;
        static::assertSame('1 000.00 EiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 ZiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 ZiB', Filter::formatHumanReadableSize(1234 * $exponent));
        $exponent *= 1024;
        static::assertSame('1 000.00 ZiB', Filter::formatHumanReadableSize(1000 * $exponent));
        static::assertSame('1.00 YiB', Filter::formatHumanReadableSize(1024 * $exponent));
        static::assertSame('1.21 YiB', Filter::formatHumanReadableSize(1234 * $exponent));
    }
}
