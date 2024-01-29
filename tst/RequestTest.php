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
use PrivateBin\Request;

/**
 * @internal
 * @coversNothing
 */
final class RequestTest extends TestCase
{
    public function reset(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
    }

    /**
     * Returns 16 random hexadecimal characters.
     *
     * @return string
     */
    public function getRandomId()
    {
        // 8 binary bytes are 16 characters long in hex
        return bin2hex(random_bytes(8));
    }

    /**
     * Returns random query safe characters.
     *
     * @return string
     */
    public function getRandomQueryChars()
    {
        $queryChars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ=';
        $queryCharCount = strlen($queryChars) - 1;
        $resultLength = random_int(1, 10);
        $result = '';
        for ($i = 0; $i < $resultLength; ++$i) {
            $result .= $queryChars[random_int(0, $queryCharCount)];
        }

        return $result;
    }

    public function testView(): void
    {
        $this->reset();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame('view', $request->getOperation());
    }

    public function testRead(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET[$id] = '';
        $request = new Request();
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('read', $request->getOperation());
    }

    public function testDelete(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['pasteid'] = $id;
        $_GET['deletetoken'] = 'bar';
        $request = new Request();
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame('delete', $request->getOperation());
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('bar', $request->getParam('deletetoken'));
    }

    public function testApiCreate(): void
    {
        $this->reset();
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents($file, '{"ct":"foo"}');
        Request::setInputStream($file);
        $request = new Request();
        unlink($file);
        static::assertTrue($request->isJsonApiCall(), 'is JSON API call');
        static::assertSame('create', $request->getOperation());
        static::assertSame('foo', $request->getParam('ct'));
    }

    public function testApiCreateAlternative(): void
    {
        $this->reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/javascript, */*; q=0.01';
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents($file, '{"ct":"foo"}');
        Request::setInputStream($file);
        $request = new Request();
        static::assertTrue($request->isJsonApiCall(), 'is JSON API call');
        static::assertSame('create', $request->getOperation());
        static::assertSame('foo', $request->getParam('ct'));
    }

    public function testApiRead(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/javascript, */*; q=0.01';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET[$id] = '';
        $request = new Request();
        static::assertTrue($request->isJsonApiCall(), 'is JSON API call');
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('read', $request->getOperation());
    }

    public function testApiDelete(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET = [$id => ''];
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents($file, '{"deletetoken":"bar"}');
        Request::setInputStream($file);
        $request = new Request();
        static::assertTrue($request->isJsonApiCall(), 'is JSON API call');
        static::assertSame('delete', $request->getOperation());
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('bar', $request->getParam('deletetoken'));
    }

    public function testPostGarbage(): void
    {
        $this->reset();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents($file, random_bytes(256));
        Request::setInputStream($file);
        $request = new Request();
        unlink($file);
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame('create', $request->getOperation());
    }

    public function testReadWithNegotiation(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,text/html; charset=UTF-8,application/xhtml+xml, application/xml;q=0.9,*/*;q=0.8, text/csv,application/json';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET[$id] = '';
        $request = new Request();
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('read', $request->getOperation());
    }

    public function testReadWithXhtmlNegotiation(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ACCEPT'] = 'application/xhtml+xml,text/html,text/html; charset=UTF-8, application/xml;q=0.9,*/*;q=0.8, text/csv,application/json';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET[$id] = '';
        $request = new Request();
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('read', $request->getOperation());
    }

    public function testApiReadWithNegotiation(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ACCEPT'] = 'text/plain,text/csv, application/xml;q=0.9, application/json, text/html,text/html; charset=UTF-8,application/xhtml+xml, */*;q=0.8';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET[$id] = '';
        $request = new Request();
        static::assertTrue($request->isJsonApiCall(), 'is JSON Api call');
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('read', $request->getOperation());
    }

    public function testReadWithFailedNegotiation(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ACCEPT'] = 'text/plain,text/csv, application/xml;q=0.9, */*;q=0.8';
        $_SERVER['QUERY_STRING'] = $id;
        $_GET[$id] = '';
        $request = new Request();
        static::assertFalse($request->isJsonApiCall(), 'is HTML call');
        static::assertSame($id, $request->getParam('pasteid'));
        static::assertSame('read', $request->getOperation());
    }

    public function testPasteIdExtraction(): void
    {
        $this->reset();
        $id = $this->getRandomId();
        $queryParams = [$id];
        $queryParamCount = random_int(1, 5);
        for ($i = 0; $i < $queryParamCount; ++$i) {
            $queryParams[] = $this->getRandomQueryChars();
        }
        shuffle($queryParams);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['QUERY_STRING'] = implode('&', $queryParams);
        $_GET[$id] = '';
        $request = new Request();
        static::assertSame($id, $request->getParam('pasteid'));
    }
}
