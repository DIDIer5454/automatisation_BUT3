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
 * Request.
 *
 * parses request parameters and provides helper functions for routing
 */
class Request
{
    /**
     * MIME type for JSON.
     *
     * @const string
     */
    public const MIME_JSON = 'application/json';

    /**
     * MIME type for HTML.
     *
     * @const string
     */
    public const MIME_HTML = 'text/html';

    /**
     * MIME type for XHTML.
     *
     * @const string
     */
    public const MIME_XHTML = 'application/xhtml+xml';

    /**
     * Input stream to use for PUT parameter parsing.
     *
     * @var string
     */
    private static $_inputStream = 'php://input';

    /**
     * Operation to perform.
     *
     * @var string
     */
    private $_operation = 'view';

    /**
     * Request parameters.
     *
     * @var array
     */
    private $_params = [];

    /**
     * If we are in a JSON API context.
     *
     * @var bool
     */
    private $_isJsonApi = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // decide if we are in JSON API or HTML context
        $this->_isJsonApi = $this->_detectJsonRequest();

        // parse parameters, depending on request type
        switch (\array_key_exists('REQUEST_METHOD', $_SERVER) ? $_SERVER['REQUEST_METHOD'] : 'GET') {
        case 'DELETE':
        case 'PUT':
        case 'POST':
            // it might be a creation or a deletion, the latter is detected below
            $this->_operation = 'create';

            try {
                $this->_params = Json::decode(
                    file_get_contents(self::$_inputStream)
                );
            } catch (Exception $e) {
                // ignore error, $this->_params will remain empty
            }

            break;

        default:
            $this->_params = $_GET;
        }
        if (!\array_key_exists('pasteid', $this->_params)
            && !\array_key_exists('jsonld', $this->_params)
            && !\array_key_exists('link', $this->_params)
            && \array_key_exists('QUERY_STRING', $_SERVER)
            && !empty($_SERVER['QUERY_STRING'])
        ) {
            $this->_params['pasteid'] = $this->getPasteId();
        }

        // prepare operation, depending on current parameters
        if (\array_key_exists('pasteid', $this->_params) && !empty($this->_params['pasteid'])) {
            if (\array_key_exists('deletetoken', $this->_params) && !empty($this->_params['deletetoken'])) {
                $this->_operation = 'delete';
            } elseif ('create' !== $this->_operation) {
                $this->_operation = 'read';
            }
        } elseif (\array_key_exists('jsonld', $this->_params) && !empty($this->_params['jsonld'])) {
            $this->_operation = 'jsonld';
        } elseif (\array_key_exists('link', $this->_params) && !empty($this->_params['link'])) {
            if (str_contains($this->getRequestUri(), '/shortenviayourls')) {
                $this->_operation = 'yourlsproxy';
            }
        }
    }

    /**
     * Get current operation.
     *
     * @return string
     */
    public function getOperation()
    {
        return $this->_operation;
    }

    /**
     * Get data of paste or comment.
     *
     * @return array
     */
    public function getData()
    {
        $data = [
            'adata' => $this->getParam('adata'),
        ];
        $required_keys = ['v', 'ct'];
        $meta = $this->getParam('meta');
        if (empty($meta)) {
            $required_keys[] = 'pasteid';
            $required_keys[] = 'parentid';
        } else {
            $data['meta'] = $meta;
        }
        foreach ($required_keys as $key) {
            $data[$key] = $this->getParam($key, 'v' === $key ? 1 : '');
        }
        // forcing a cast to int or float
        $data['v'] = $data['v'] + 0;

        return $data;
    }

    /**
     * Get a request parameter.
     *
     * @param string $param
     * @param string $default
     *
     * @return string
     */
    public function getParam($param, $default = '')
    {
        return \array_key_exists($param, $this->_params) ?
            $this->_params[$param] : $default;
    }

    /**
     * Get host as requested by the client.
     *
     * @return string
     */
    public function getHost()
    {
        return \array_key_exists('HTTP_HOST', $_SERVER) ?
            htmlspecialchars($_SERVER['HTTP_HOST']) :
            'localhost';
    }

    /**
     * Get request URI.
     *
     * @return string
     */
    public function getRequestUri()
    {
        return \array_key_exists('REQUEST_URI', $_SERVER) ?
        htmlspecialchars(
            parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
        ) : '/';
    }

    /**
     * If we are in a JSON API context.
     *
     * @return bool
     */
    public function isJsonApiCall()
    {
        return $this->_isJsonApi;
    }

    /**
     * Override the default input stream source, used for unit testing.
     *
     * @param string $input
     */
    public static function setInputStream($input): void
    {
        self::$_inputStream = $input;
    }

    /**
     * Return the paste ID of the current paste.
     *
     * @return string
     */
    private function getPasteId()
    {
        // RegEx to check for valid paste ID (16 base64 chars)
        $pasteIdRegEx = '/^[a-f0-9]{16}$/';

        foreach ($_GET as $key => $value) {
            // only return if value is empty and key matches RegEx
            if (('' === $value) && preg_match($pasteIdRegEx, $key, $match)) {
                return $match[0];
            }
        }

        return 'invalid id';
    }

    /**
     * Detect the clients supported media type and decide if its a JSON API call or not.
     *
     * Adapted from: https://stackoverflow.com/questions/3770513/detect-browser-language-in-php#3771447
     *
     * @return bool
     */
    private function _detectJsonRequest()
    {
        $hasAcceptHeader = \array_key_exists('HTTP_ACCEPT', $_SERVER);
        $acceptHeader = $hasAcceptHeader ? $_SERVER['HTTP_ACCEPT'] : '';

        // simple cases
        if ((\array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER)
            && 'JSONHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH'])
            || ($hasAcceptHeader
            && str_contains($acceptHeader, self::MIME_JSON)
            && !str_contains($acceptHeader, self::MIME_HTML)
            && !str_contains($acceptHeader, self::MIME_XHTML))
        ) {
            return true;
        }

        // advanced case: media type negotiation
        $mediaTypes = [];
        if ($hasAcceptHeader) {
            $mediaTypeRanges = explode(',', trim($acceptHeader));
            foreach ($mediaTypeRanges as $mediaTypeRange) {
                if (preg_match(
                    '#(\*/\*|[a-z\-]+/[a-z\-+*]+(?:\s*;\s*[^q]\S*)*)(?:\s*;\s*q\s*=\s*(0(?:\.\d{0,3})|1(?:\.0{0,3})))?#',
                    trim($mediaTypeRange),
                    $match
                )
                ) {
                    if (!isset($match[2])) {
                        $match[2] = '1.0';
                    } else {
                        $match[2] = (string) (float) ($match[2]);
                    }
                    if (!isset($mediaTypes[$match[2]])) {
                        $mediaTypes[$match[2]] = [];
                    }
                    $mediaTypes[$match[2]][] = strtolower($match[1]);
                }
            }
            krsort($mediaTypes);
            foreach ($mediaTypes as $acceptedQuality => $acceptedValues) {
                if ('0.0' === $acceptedQuality) {
                    continue;
                }
                foreach ($acceptedValues as $acceptedValue) {
                    if (str_starts_with($acceptedValue, self::MIME_HTML)
                        || str_starts_with($acceptedValue, self::MIME_XHTML)
                    ) {
                        return false;
                    }
                    if (str_starts_with($acceptedValue, self::MIME_JSON)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
