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
use PrivateBin\Controller;
use PrivateBin\Data\Filesystem;
use PrivateBin\Persistence\ServerSalt;
use PrivateBin\Request;

/**
 * @internal
 * @coversNothing
 */
final class JsonApiTest extends TestCase
{
    protected $_model;

    protected $_path;

    protected function setUp(): void
    {
        // Setup Routine
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_data';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
        $this->_model = new Filesystem(['dir' => $this->_path]);
        ServerSalt::setStore($this->_model);

        $_POST = [];
        $_GET = [];
        $_SERVER = [];
        if ($this->_model->exists(Helper::getPasteId())) {
            $this->_model->delete(Helper::getPasteId());
        }
        $options = parse_ini_file(CONF_SAMPLE, true);
        $options['model_options']['dir'] = $this->_path;
        Helper::confBackup();
        Helper::createIniFile(CONF, $options);
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        unlink(CONF);
        Helper::confRestore();
        Helper::rmDir($this->_path);
    }

    /**
     * @runInSeparateProcess
     */
    public function testCreate(): void
    {
        $options = parse_ini_file(CONF, true);
        $options['traffic']['limit'] = 0;
        Helper::createIniFile(CONF, $options);
        $paste = Helper::getPasteJson();
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents($file, $paste);
        Request::setInputStream($file);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '::1';
        $_SERVER['REQUEST_URI'] = '/';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        $response = json_decode($content, true);
        static::assertSame(0, $response['status'], 'outputs status');
        static::assertStringEndsWith('?'.$response['id'], $response['url'], 'returned URL points to new paste');
        static::assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
        $paste = $this->_model->read($response['id']);
        static::assertSame(
            hash_hmac('sha256', $response['id'], $paste['meta']['salt']),
            $response['deletetoken'],
            'outputs valid delete token'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testPut(): void
    {
        $options = parse_ini_file(CONF, true);
        $options['traffic']['limit'] = 0;
        Helper::createIniFile(CONF, $options);
        $paste = Helper::getPasteJson();
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents($file, $paste);
        Request::setInputStream($file);
        $_SERVER['QUERY_STRING'] = Helper::getPasteId();
        $_GET[Helper::getPasteId()] = '';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['REMOTE_ADDR'] = '::1';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        unlink($file);
        $response = json_decode($content, true);
        static::assertSame(0, $response['status'], 'outputs status');
        static::assertSame(Helper::getPasteId(), $response['id'], 'outputted paste ID matches input');
        static::assertStringEndsWith('?'.$response['id'], $response['url'], 'returned URL points to new paste');
        static::assertTrue($this->_model->exists($response['id']), 'paste exists after posting data');
        $paste = $this->_model->read($response['id']);
        static::assertSame(
            hash_hmac('sha256', $response['id'], $paste['meta']['salt']),
            $response['deletetoken'],
            'outputs valid delete token'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testDelete(): void
    {
        $this->_model->create(Helper::getPasteId(), Helper::getPaste());
        static::assertTrue($this->_model->exists(Helper::getPasteId()), 'paste exists before deleting data');
        $paste = $this->_model->read(Helper::getPasteId());
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents(
            $file,
            json_encode(
                [
                    'deletetoken' => hash_hmac('sha256', Helper::getPasteId(), $paste['meta']['salt']),
                ]
            )
        );
        Request::setInputStream($file);
        $_SERVER['QUERY_STRING'] = Helper::getPasteId();
        $_GET[Helper::getPasteId()] = '';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        unlink($file);
        $response = json_decode($content, true);
        static::assertSame(0, $response['status'], 'outputs status');
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste successfully deleted');
    }

    /**
     * @runInSeparateProcess
     */
    public function testDeleteWithPost(): void
    {
        $this->_model->create(Helper::getPasteId(), Helper::getPaste());
        static::assertTrue($this->_model->exists(Helper::getPasteId()), 'paste exists before deleting data');
        $paste = $this->_model->read(Helper::getPasteId());
        $file = tempnam(sys_get_temp_dir(), 'FOO');
        file_put_contents(
            $file,
            json_encode(
                [
                    'pasteid' => Helper::getPasteId(),
                    'deletetoken' => hash_hmac('sha256', Helper::getPasteId(), $paste['meta']['salt']),
                ]
            )
        );
        Request::setInputStream($file);
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        $response = json_decode($content, true);
        static::assertSame(0, $response['status'], 'outputs status');
        static::assertFalse($this->_model->exists(Helper::getPasteId()), 'paste successfully deleted');
    }

    /**
     * @runInSeparateProcess
     */
    public function testRead(): void
    {
        $paste = Helper::getPaste();
        $this->_model->create(Helper::getPasteId(), $paste);
        $_SERVER['QUERY_STRING'] = Helper::getPasteId();
        $_GET[Helper::getPasteId()] = '';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'JSONHttpRequest';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        $response = json_decode($content, true);
        static::assertSame(0, $response['status'], 'outputs success status');
        static::assertSame(Helper::getPasteId(), $response['id'], 'outputs data correctly');
        static::assertStringEndsWith('?'.$response['id'], $response['url'], 'returned URL points to new paste');
        static::assertSame($paste['ct'], $response['ct'], 'outputs data correctly');
        static::assertSame($paste['meta']['created'], $response['meta']['created'], 'outputs postdate correctly');
        static::assertSame(0, $response['comment_count'], 'outputs comment_count correctly');
        static::assertSame(0, $response['comment_offset'], 'outputs comment_offset correctly');
    }

    /**
     * @runInSeparateProcess
     */
    public function testJsonLdPaste(): void
    {
        $_GET['jsonld'] = 'paste';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertSame(
            str_replace(
                '?jsonld=',
                '/?jsonld=',
                file_get_contents(PUBLIC_PATH.'/js/paste.jsonld')
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testJsonLdComment(): void
    {
        $_GET['jsonld'] = 'comment';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertSame(
            str_replace(
                '?jsonld=',
                '/?jsonld=',
                file_get_contents(PUBLIC_PATH.'/js/comment.jsonld')
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testJsonLdPasteMeta(): void
    {
        $_GET['jsonld'] = 'pastemeta';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertSame(
            str_replace(
                '?jsonld=',
                '/?jsonld=',
                file_get_contents(PUBLIC_PATH.'/js/pastemeta.jsonld')
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testJsonLdCommentMeta(): void
    {
        $_GET['jsonld'] = 'commentmeta';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertSame(
            str_replace(
                '?jsonld=',
                '/?jsonld=',
                file_get_contents(PUBLIC_PATH.'/js/commentmeta.jsonld')
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testJsonLdTypes(): void
    {
        $_GET['jsonld'] = 'types';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertSame(
            str_replace(
                '?jsonld=',
                '/?jsonld=',
                file_get_contents(PUBLIC_PATH.'/js/types.jsonld')
            ),
            $content,
            'outputs data correctly'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testJsonLdInvalid(): void
    {
        $_GET['jsonld'] = CONF;
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertSame('{}', $content, 'does not output nasty data');
    }

    /**
     * @runInSeparateProcess
     */
    public function testShortenViaYourls(): void
    {
        $mock_yourls_service = $this->_path.DIRECTORY_SEPARATOR.'yourls.json';
        $options = parse_ini_file(CONF, true);
        $options['main']['basepath'] = 'https://example.com/path'; // missing slash gets added by Configuration constructor
        $options['main']['urlshortener'] = 'https://example.com/path/shortenviayourls?link=';
        $options['yourls']['apiurl'] = $mock_yourls_service;
        Helper::createIniFile(CONF, $options);

        // the real service answer is more complex, but we only look for the shorturl & statusCode
        file_put_contents($mock_yourls_service, '{"shorturl":"https:\/\/example.com\/1","statusCode":200}');

        $_SERVER['REQUEST_URI'] = '/path/shortenviayourls?link=https%3A%2F%2Fexample.com%2Fpath%2F%3Ffoo%23bar';
        $_GET['link'] = 'https://example.com/path/?foo#bar';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertStringContainsString('id="pasteurl" href="https://example.com/1"', $content, 'outputs shortened URL correctly');
    }

    /**
     * @runInSeparateProcess
     */
    public function testShortenViaYourlsFailure(): void
    {
        $_SERVER['REQUEST_URI'] = '/path/shortenviayourls?link=https%3A%2F%2Fexample.com%2Fpath%2F%3Ffoo%23bar';
        $_GET['link'] = 'https://example.com/path/?foo#bar';
        ob_start();
        new Controller();
        $content = ob_get_contents();
        ob_end_clean();
        static::assertStringContainsString('Error calling YOURLS.', $content, 'outputs error correctly');
    }
}
