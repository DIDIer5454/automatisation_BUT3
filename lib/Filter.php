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

namespace PrivateBin;

use Exception;

/**
 * Filter.
 *
 * Provides data filtering functions.
 */
class Filter
{
    /**
     * format a given time string into a human readable label (localized).
     *
     * accepts times in the format "[integer][time unit]"
     *
     * @static
     *
     * @param string $time
     *
     * @throws Exception
     *
     * @return string
     */
    public static function formatHumanReadableTime($time)
    {
        if (1 !== preg_match('/^(\d+) *(\w+)$/', $time, $matches)) {
            throw new Exception("Error parsing time format '{$time}'", 30);
        }

        switch ($matches[2]) {
        case 'sec':
            $unit = 'second';

            break;

        case 'min':
            $unit = 'minute';

            break;

        default:
            $unit = rtrim($matches[2], 's');
        }

        return I18n::_(['%d '.$unit, '%d '.$unit.'s'], (int) $matches[1]);
    }

    /**
     * format a given number of bytes in IEC 80000-13:2008 notation (localized).
     *
     * @static
     *
     * @param int $size
     *
     * @return string
     */
    public static function formatHumanReadableSize($size)
    {
        $iec = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $i = 0;
        while (($size / 1024) >= 1) {
            $size = $size / 1024;
            ++$i;
        }

        return number_format($size, $i ? 2 : 0, '.', ' ').' '.I18n::_($iec[$i]);
    }
}
