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
use PrivateBin\I18n;
use PrivateBin\View;

/**
 * @internal
 * @coversNothing
 */
final class ViewTest extends TestCase
{
    private static $error = 'foo bar';

    private static $status = '!*#@?$+';

    private static $formatters = [
        'plaintext' => 'Plain Text',
        'syntaxhighlighting' => 'Source Code',
        'markdown' => 'Markdown',
    ];

    private static $formatter_default = 'plaintext';

    private static $expire = [
        '5min' => '5 minutes',
        '1hour' => '1 hour',
        'never' => 'Never',
    ];

    private static $expire_default = '1hour';

    private static $version = 'Version 1.2.3';

    private $_content = [];

    protected function setUp(): void
    {
        // Setup Routine
        $page = new View();
        $page->assign('NAME', 'PrivateBinTest');
        $page->assign('BASEPATH', '');
        $page->assign('ERROR', self::$error);
        $page->assign('STATUS', self::$status);
        $page->assign('VERSION', self::$version);
        $page->assign('DISCUSSION', true);
        $page->assign('OPENDISCUSSION', true);
        $page->assign('MARKDOWN', true);
        $page->assign('SYNTAXHIGHLIGHTING', true);
        $page->assign('SYNTAXHIGHLIGHTINGTHEME', 'sons-of-obsidian');
        $page->assign('FORMATTER', self::$formatters);
        $page->assign('FORMATTERDEFAULT', self::$formatter_default);
        $page->assign('BURNAFTERREADINGSELECTED', false);
        $page->assign('PASSWORD', true);
        $page->assign('FILEUPLOAD', false);
        $page->assign('ZEROBINCOMPATIBILITY', false);
        $page->assign('INFO', 'example');
        $page->assign('NOTICE', 'example');
        $page->assign('LANGUAGESELECTION', '');
        $page->assign('LANGUAGES', I18n::getLanguageLabels(I18n::getAvailableLanguages()));
        $page->assign('EXPIRE', self::$expire);
        $page->assign('EXPIREDEFAULT', self::$expire_default);
        $page->assign('URLSHORTENER', '');
        $page->assign('QRCODE', true);
        $page->assign('EMAIL', true);
        $page->assign('HTTPWARNING', true);
        $page->assign('HTTPSLINK', 'https://example.com/');
        $page->assign('COMPRESSION', 'zlib');
        $page->assign('CSPHEADER', 'default-src \'none\'');

        $dir = dir(PATH.'tpl');
        while (false !== ($file = $dir->read())) {
            if ('.php' === substr($file, -4)) {
                $template = substr($file, 0, -4);
                ob_start();
                $page->draw($template);
                $this->_content[$template] = ob_get_contents();
                ob_end_clean();
            }
        }
        // check bootstrap variants
        $template = 'bootstrap-page';
        ob_start();
        $page->draw($template);
        $this->_content[$template] = ob_get_contents();
        ob_end_clean();
        foreach (['-dark', '-compact'] as $suffix) {
            $template = 'bootstrap'.$suffix;
            ob_start();
            $page->draw($template);
            $this->_content[$template] = ob_get_contents();
            ob_end_clean();

            $template .= '-page';
            ob_start();
            $page->draw($template);
            $this->_content[$template] = ob_get_contents();
            ob_end_clean();
        }
    }

    public function testTemplateRendersCorrectly(): void
    {
        foreach ($this->_content as $template => $content) {
            static::assertMatchesRegularExpression(
                '#<div[^>]+id="errormessage"[^>]*>.*'.self::$error.'#s',
                $content,
                $template.': outputs error correctly'
            );
            if ('yourlsproxy' === $template) {
                // yourlsproxy template only displays error message
                continue;
            }
            static::assertMatchesRegularExpression(
                '#<[^>]+id="password"[^>]*>#',
                $content,
                $template.': password available if configured'
            );
            static::assertMatchesRegularExpression(
                '#<input[^>]+id="opendiscussion"[^>]*checked="checked"[^>]*>#',
                $content,
                $template.': checked discussion if configured'
            );
            static::assertMatchesRegularExpression(
                '#<[^>]+id="opendiscussionoption"[^>]*>#',
                $content,
                $template.': discussions available if configured'
            );
            // testing version number in JS address, since other instances may not be present in different templates
            static::assertMatchesRegularExpression(
                '#<script[^>]+src="js/privatebin.js\\?'.rawurlencode(self::$version).'"[^>]*>#',
                $content,
                $template.': outputs version correctly'
            );
        }
    }

    public function testMissingTemplate(): void
    {
        $test = new View();
        $this->expectException(Exception::class);
        $this->expectExceptionCode(80);
        $test->draw('123456789 does not exist!');
    }
}
