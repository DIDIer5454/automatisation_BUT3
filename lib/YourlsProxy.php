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
 * YourlsProxy.
 *
 * Forwards a URL for shortening to YOURLS (your own URL shortener) and stores
 * the result.
 */
class YourlsProxy
{
    /**
     * error message.
     *
     * @var string
     */
    private $_error = '';

    /**
     * shortened URL.
     *
     * @var string
     */
    private $_url = '';

    /**
     * constructor.
     *
     * initializes and runs PrivateBin
     *
     * @param string $link
     */
    public function __construct(Configuration $conf, $link)
    {
        if (!str_contains($link, $conf->getKey('basepath').'?')) {
            $this->_error = 'Trying to shorten a URL that isn\'t pointing at our instance.';

            return;
        }

        $yourls_api_url = $conf->getKey('apiurl', 'yourls');
        if (empty($yourls_api_url)) {
            $this->_error = 'Error calling YOURLS. Probably a configuration issue, like wrong or missing "apiurl" or "signature".';

            return;
        }

        $data = file_get_contents(
            $yourls_api_url,
            false,
            stream_context_create(
                [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => http_build_query(
                            [
                                'signature' => $conf->getKey('signature', 'yourls'),
                                'format' => 'json',
                                'action' => 'shorturl',
                                'url' => $link,
                            ]
                        ),
                    ],
                ]
            )
        );

        try {
            $data = Json::decode($data);
        } catch (Exception $e) {
            $this->_error = 'Error calling YOURLS. Probably a configuration issue, like wrong or missing "apiurl" or "signature".';
            error_log('Error calling YOURLS: '.$e->getMessage());

            return;
        }

        if (null !== $data
            && \array_key_exists('statusCode', $data)
            && 200 === $data['statusCode']
            && \array_key_exists('shorturl', $data)
        ) {
            $this->_url = $data['shorturl'];
        } else {
            $this->_error = 'Error parsing YOURLS response.';
        }
    }

    /**
     * Returns the (untranslated) error message.
     *
     * @return string
     */
    public function getError()
    {
        return $this->_error;
    }

    /**
     * Returns the shortened URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->_url;
    }

    /**
     * Returns true if any error has occurred.
     *
     * @return bool
     */
    public function isError()
    {
        return !empty($this->_error);
    }
}
