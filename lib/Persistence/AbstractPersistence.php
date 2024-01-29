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

namespace PrivateBin\Persistence;

use PrivateBin\Data\AbstractData;

/**
 * AbstractPersistence.
 *
 * persists data in PHP files
 */
abstract class AbstractPersistence
{
    /**
     * data storage to use to persist something.
     *
     * @static
     *
     * @var AbstractData
     */
    protected static $_store;

    /**
     * set the path.
     *
     * @static
     */
    public static function setStore(AbstractData $store): void
    {
        self::$_store = $store;
    }
}
