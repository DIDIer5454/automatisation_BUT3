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
 * Json.
 *
 * Provides JSON functions in an object oriented way.
 */
class Json
{
    /**
     * Returns a string containing the JSON representation of the given input.
     *
     * @static
     *
     * @param mixed $input
     *
     * @throws Exception
     *
     * @return string
     */
    public static function encode($input)
    {
        $jsonString = json_encode($input);
        self::_detectError();

        return $jsonString;
    }

    /**
     * Returns an array with the contents as described in the given JSON input.
     *
     * @static
     *
     * @param string $input
     *
     * @throws Exception
     *
     * @return mixed
     */
    public static function decode($input)
    {
        $output = json_decode($input, true);
        self::_detectError();

        return $output;
    }

    /**
     * Detects JSON errors and raises an exception if one is found.
     *
     * @static
     *
     * @throws Exception
     */
    private static function _detectError(): void
    {
        $errorCode = json_last_error();
        if (JSON_ERROR_NONE === $errorCode) {
            return;
        }

        $message = 'A JSON error occurred';
        if (\function_exists('json_last_error_msg')) {
            $message .= ': '.json_last_error_msg();
        }
        $message .= ' ('.$errorCode.')';

        throw new Exception($message, 90);
    }
}
