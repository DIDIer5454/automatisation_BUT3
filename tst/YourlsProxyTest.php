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
use PrivateBin\Configuration;
use PrivateBin\YourlsProxy;

/**
 * @internal
 * @coversNothing
 */
final class YourlsProxyTest extends TestCase
{
    private $_conf;

    private $_path;

    private $_mock_yourls_service;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        $this->_mock_yourls_service = $this->_path.DIRECTORY_SEPARATOR.'yourls.json';
        $options = parse_ini_file(CONF_SAMPLE, true);
        $options['main']['basepath'] = 'https://example.com/';
        $options['main']['urlshortener'] = 'https://example.com/shortenviayourls?link=';
        $options['yourls']['apiurl'] = $this->_mock_yourls_service;
        Helper::confBackup();
        Helper::createIniFile(CONF, $options);
        $this->_conf = new Configuration();
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        unlink(CONF);
        Helper::confRestore();
        Helper::rmDir($this->_path);
    }

    public function testYourlsProxy(): void
    {
        // the real service answer is more complex, but we only look for the shorturl & statusCode
        file_put_contents($this->_mock_yourls_service, '{"shorturl":"https:\/\/example.com\/1","statusCode":200}');

        $yourls = new YourlsProxy($this->_conf, 'https://example.com/?foo#bar');
        static::assertFalse($yourls->isError());
        static::assertSame($yourls->getUrl(), 'https://example.com/1');
    }

    public function testForeignUrl(): void
    {
        $yourls = new YourlsProxy($this->_conf, 'https://other.example.com/?foo#bar');
        static::assertTrue($yourls->isError());
        static::assertSame($yourls->getError(), 'Trying to shorten a URL that isn\'t pointing at our instance.');
    }

    public function testYourlsError(): void
    {
        // when statusCode is not 200, shorturl may not have been set
        file_put_contents($this->_mock_yourls_service, '{"statusCode":403}');

        $yourls = new YourlsProxy($this->_conf, 'https://example.com/?foo#bar');
        static::assertTrue($yourls->isError());
        static::assertSame($yourls->getError(), 'Error parsing YOURLS response.');
    }

    public function testServerError(): void
    {
        // simulate some other server error that results in a non-JSON reply
        file_put_contents($this->_mock_yourls_service, '500 Internal Server Error');

        $yourls = new YourlsProxy($this->_conf, 'https://example.com/?foo#bar');
        static::assertTrue($yourls->isError());
        static::assertSame($yourls->getError(), 'Error calling YOURLS. Probably a configuration issue, like wrong or missing "apiurl" or "signature".');
    }
}
