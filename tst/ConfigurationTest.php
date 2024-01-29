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

/**
 * @internal
 * @coversNothing
 */
final class ConfigurationTest extends TestCase
{
    private $_minimalConfig;

    private $_options;

    private $_path;

    protected function setUp(): void
    {
        // Setup Routine
        Helper::confBackup();
        $this->_minimalConfig = '[main]'.PHP_EOL.'[model]'.PHP_EOL.'[model_options]';
        $this->_options = Configuration::getDefaults();
        $this->_options['model_options']['dir'] = PATH.$this->_options['model_options']['dir'];
        $this->_path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_cfg';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
    }

    protected function tearDown(): void
    {
        // Tear Down Routine
        Helper::rmDir($this->_path);
        if (is_file(CONF)) {
            unlink(CONF);
        }
        Helper::confRestore();
    }

    public function testDefaultConfigFile(): void
    {
        $conf = new Configuration();
        static::assertSame($this->_options, $conf->get(), 'default configuration is correct');
    }

    public function testHandleFreshConfigFile(): void
    {
        Helper::createIniFile(CONF, $this->_options);
        $conf = new Configuration();
        static::assertSame($this->_options, $conf->get(), 'newly generated configuration is correct');
    }

    public function testHandleMissingConfigFile(): void
    {
        if (is_file(CONF)) {
            unlink(CONF);
        }
        $conf = new Configuration();
        static::assertSame($this->_options, $conf->get(), 'returns correct defaults on missing file');
    }

    public function testHandleBlankConfigFile(): void
    {
        file_put_contents(CONF, '');
        $this->expectException(Exception::class);
        $this->expectExceptionCode(2);
        new Configuration();
    }

    public function testHandleMinimalConfigFile(): void
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration();
        static::assertSame($this->_options, $conf->get(), 'returns correct defaults on empty file');
    }

    public function testHandleInvalidSection(): void
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration();
        $this->expectException(Exception::class);
        $this->expectExceptionCode(3);
        $conf->getKey('foo', 'bar');
    }

    public function testHandleInvalidKey(): void
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration();
        $this->expectException(Exception::class);
        $this->expectExceptionCode(4);
        $conf->getKey('foo');
    }

    public function testHandleGetKey(): void
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration();
        static::assertSame($this->_options['main']['sizelimit'], $conf->getKey('sizelimit'), 'get default size');
    }

    public function testHandleWrongTypes(): void
    {
        $original_options = $this->_options;
        $original_options['main']['syntaxhighlightingtheme'] = 'foo';
        $options = $original_options;
        $options['main']['discussion'] = 'true';
        $options['main']['opendiscussion'] = 0;
        $options['main']['password'] = -1; // evaluates to TRUE
        $options['main']['fileupload'] = 'false';
        $options['expire_options']['foo'] = 'bar';
        $options['formatter_options'][] = 'foo';
        Helper::createIniFile(CONF, $options);
        $conf = new Configuration();
        $original_options['expire_options']['foo'] = (int) 'bar';
        $original_options['formatter_options'][0] = 'foo';
        static::assertSame($original_options, $conf->get(), 'incorrect types are corrected');
    }

    public function testHandleMissingSubKeys(): void
    {
        $options = $this->_options;
        unset($options['expire_options']['1week'], $options['expire_options']['1year'], $options['expire_options']['never']);

        Helper::createIniFile(CONF, $options);
        $conf = new Configuration();
        $options['expire']['default'] = '5min';
        static::assertSame($options, $conf->get(), 'not overriding "missing" subkeys');
    }

    public function testHandlePreRenameConfig(): void
    {
        $options = $this->_options;
        $options['model']['class'] = 'zerobin_data';
        Helper::createIniFile(CONF, $options);
        $conf = new Configuration();
        static::assertSame('Filesystem', $conf->getKey('class', 'model'), 'old data class gets renamed');

        $options['model']['class'] = 'zerobin_db';
        Helper::createIniFile(CONF, $options);
        $conf = new Configuration();
        static::assertSame('Database', $conf->getKey('class', 'model'), 'old db class gets renamed');
    }

    public function testConfigPath(): void
    {
        // setup
        $configFile = $this->_path.DIRECTORY_SEPARATOR.'conf.php';
        $options = $this->_options;
        $options['main']['name'] = 'OtherBin';
        Helper::createIniFile($configFile, $options);

        // test
        putenv('CONFIG_PATH='.$this->_path);
        $conf = new Configuration();
        static::assertSame('OtherBin', $conf->getKey('name'), 'changing config path is supported');

        // cleanup environment
        if (is_file($configFile)) {
            unlink($configFile);
        }
        putenv('CONFIG_PATH');
    }
}
