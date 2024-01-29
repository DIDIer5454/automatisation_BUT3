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
 * ServerSalt.
 *
 * This is a random string which is unique to each PrivateBin installation.
 * It is automatically created if not present.
 *
 * Salt is used:
 * - to generate unique VizHash in discussions (which are not reproductible across PrivateBin servers)
 * - to generate unique deletion token (which are not re-usable across PrivateBin servers)
 */
class ServerSalt extends AbstractPersistence
{
    /**
     * generated salt.
     *
     * @static
     *
     * @var string
     */
    private static $_salt = '';

    /**
     * generate a large random hexadecimal salt.
     *
     * @static
     *
     * @return string
     */
    public static function generate()
    {
        return bin2hex(random_bytes(256));
    }

    /**
     * get server salt.
     *
     * @static
     *
     * @return string
     */
    public static function get()
    {
        if (\strlen(self::$_salt)) {
            return self::$_salt;
        }

        $salt = self::$_store->getValue('salt');
        if ($salt) {
            self::$_salt = $salt;
        } else {
            self::$_salt = self::generate();
            if (!self::$_store->setValue(self::$_salt, 'salt')) {
                error_log('failed to store the server salt, delete tokens, traffic limiter and user icons won\'t work');
            }
        }

        return self::$_salt;
    }

    /**
     * set the path.
     *
     * @static
     */
    public static function setStore(AbstractData $store): void
    {
        self::$_salt = '';
        parent::setStore($store);
    }
}
