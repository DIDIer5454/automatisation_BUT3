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

class I18nMock extends I18n
{
    public static function resetAvailableLanguages(): void
    {
        self::$_availableLanguages = [];
    }

    public static function resetPath($path = ''): void
    {
        self::$_path = $path;
    }

    public static function getPath($file = '')
    {
        return self::_getPath($file);
    }
}

/**
 * @internal
 * @coversNothing
 */
final class I18nTest extends TestCase
{
    private $_translations = [];

    protected function setUp(): void
    {
        // Setup Routine
        $this->_translations = json_decode(
            file_get_contents(PATH.'i18n'.DIRECTORY_SEPARATOR.'de.json'),
            true
        );
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['lang'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testTranslationFallback(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foobar';
        $messageId = 'It does not matter if the message ID exists';
        I18n::loadTranslations();
        static::assertSame($messageId, I18n::_($messageId), 'fallback to en');
        I18n::getLanguageLabels();
    }

    public function testCookieLanguageDeDetection(): void
    {
        $_COOKIE['lang'] = 'de';
        I18n::loadTranslations();
        static::assertSame($_COOKIE['lang'], I18n::getLanguage(), 'browser language de');
        static::assertSame('0 Stunden', I18n::_('%d hours', 0), '0 hours in German');
        static::assertSame('1 Stunde', I18n::_('%d hours', 1), '1 hour in German');
        static::assertSame('2 Stunden', I18n::_('%d hours', 2), '2 hours in German');
    }

    public function testBrowserLanguageDeDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-CH,de;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2,fr;q=0.0';
        I18n::loadTranslations();
        static::assertSame('de', I18n::getLanguage(), 'browser language de');
        static::assertSame('0 Stunden', I18n::_('%d hours', 0), '0 hours in German');
        static::assertSame('1 Stunde', I18n::_('%d hours', 1), '1 hour in German');
        static::assertSame('2 Stunden', I18n::_('%d hours', 2), '2 hours in German');
    }

    public function testBrowserLanguageFrDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-CH,fr;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2,de;q=0.0';
        I18n::loadTranslations();
        static::assertSame('fr', I18n::getLanguage(), 'browser language fr');
        static::assertSame('0 heure', I18n::_('%d hours', 0), '0 hours in French');
        static::assertSame('1 heure', I18n::_('%d hours', 1), '1 hour in French');
        static::assertSame('2 heures', I18n::_('%d hours', 2), '2 hours in French');
    }

    public function testBrowserLanguageNoDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'no;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('no', I18n::getLanguage(), 'browser language no');
        static::assertSame('0 timer', I18n::_('%d hours', 0), '0 hours in Norwegian');
        static::assertSame('1 time', I18n::_('%d hours', 1), '1 hour in Norwegian');
        static::assertSame('2 timer', I18n::_('%d hours', 2), '2 hours in Norwegian');
    }

    public function testBrowserLanguageOcDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'oc;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('oc', I18n::getLanguage(), 'browser language oc');
        static::assertSame('0 ora', I18n::_('%d hours', 0), '0 hours in Occitan');
        static::assertSame('1 ora', I18n::_('%d hours', 1), '1 hour in Occitan');
        static::assertSame('2 oras', I18n::_('%d hours', 2), '2 hours in Occitan');
    }

    public function testBrowserLanguageZhDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'zh;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('zh', I18n::getLanguage(), 'browser language zh');
        static::assertSame('0 小时', I18n::_('%d hours', 0), '0 hours in Chinese');
        static::assertSame('1 小时', I18n::_('%d hours', 1), '1 hour in Chinese');
        static::assertSame('2 小时', I18n::_('%d hours', 2), '2 hours in Chinese');
    }

    public function testBrowserLanguagePlDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pl;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('pl', I18n::getLanguage(), 'browser language pl');
        static::assertSame('1 godzina', I18n::_('%d hours', 1), '1 hour in Polish');
        static::assertSame('2 godzina', I18n::_('%d hours', 2), '2 hours in Polish');
        static::assertSame('12 godzinę', I18n::_('%d hours', 12), '12 hours in Polish');
        static::assertSame('22 godzina', I18n::_('%d hours', 22), '22 hours in Polish');
        static::assertSame('1 minut', I18n::_('%d minutes', 1), '1 minute in Polish');
        static::assertSame('3 minut', I18n::_('%d minutes', 3), '3 minutes in Polish');
        static::assertSame('13 minut', I18n::_('%d minutes', 13), '13 minutes in Polish');
        static::assertSame('23 minut', I18n::_('%d minutes', 23), '23 minutes in Polish');
    }

    public function testBrowserLanguageRuDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ru;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('ru', I18n::getLanguage(), 'browser language ru');
        static::assertSame('1 минуту', I18n::_('%d minutes', 1), '1 minute in Russian');
        static::assertSame('3 минуты', I18n::_('%d minutes', 3), '3 minutes in Russian');
        static::assertSame('10 минут', I18n::_('%d minutes', 10), '10 minutes in Russian');
        static::assertSame('21 минуту', I18n::_('%d minutes', 21), '21 minutes in Russian');
    }

    public function testBrowserLanguageSlDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'sl;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('sl', I18n::getLanguage(), 'browser language sl');
        static::assertSame('0 ura', I18n::_('%d hours', 0), '0 hours in Slowene');
        static::assertSame('1 uri', I18n::_('%d hours', 1), '1 hour in Slowene');
        static::assertSame('2 ure', I18n::_('%d hours', 2), '2 hours in Slowene');
        static::assertSame('3 ur', I18n::_('%d hours', 3), '3 hours in Slowene');
        static::assertSame('11 ura', I18n::_('%d hours', 11), '11 hours in Slowene');
        static::assertSame('101 uri', I18n::_('%d hours', 101), '101 hours in Slowene');
        static::assertSame('102 ure', I18n::_('%d hours', 102), '102 hours in Slowene');
        static::assertSame('104 ur', I18n::_('%d hours', 104), '104 hours in Slowene');
    }

    public function testBrowserLanguageCsDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'cs;q=0.8,en-GB;q=0.6,en-US;q=0.4,en;q=0.2';
        I18n::loadTranslations();
        static::assertSame('cs', I18n::getLanguage(), 'browser language cs');
        static::assertSame('1 hodina', I18n::_('%d hours', 1), '1 hour in Czech');
        static::assertSame('2 hodiny', I18n::_('%d hours', 2), '2 hours in Czech');
        static::assertSame('5 minut', I18n::_('%d minutes', 5), '5 minutes in Czech');
        static::assertSame('14 minut', I18n::_('%d minutes', 14), '14 minutes in Czech');
    }

    public function testBrowserLanguageAnyDetection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '*';
        I18n::loadTranslations();
        static::assertTrue(strlen(I18n::getLanguage()) >= 2, 'browser language any');
    }

    public function testVariableInjection(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foobar';
        I18n::loadTranslations();
        static::assertSame('some string + 1', I18n::_('some %s + %d', 'string', 1), 'browser language en');
    }

    public function testHtmlEntityEncoding(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foobar';
        I18n::loadTranslations();
        $input = '&<>"\'/`=';
        $result = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5 | ENT_DISALLOWED, 'UTF-8', false);
        static::assertSame($result, I18n::encode($input), 'encodes HTML entities');
        static::assertSame('<a>some '.$result.' + 1</a>', I18n::_('<a>some %s + %d</a>', $input, 1), 'encodes parameters in translations');
        static::assertSame($result.$result, I18n::_($input.'%s', $input), 'encodes message ID as well, when no link');
    }

    public function testFallbackAlwaysPresent(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'privatebin_i18n';
        if (!is_dir($path)) {
            mkdir($path);
        }

        $languageIterator = new AppendIterator();
        $languageIterator->append(new GlobIterator(I18nMock::getPath('??.json')));
        $languageIterator->append(new GlobIterator(I18nMock::getPath('???.json'))); // for jbo
        $languageCount = 0;
        foreach ($languageIterator as $file) {
            ++$languageCount;
            static::assertTrue(copy($file, $path.DIRECTORY_SEPARATOR.$file->getBasename()));
        }

        I18nMock::resetPath($path);
        $languagesDevelopment = I18nMock::getAvailableLanguages();
        static::assertSame($languageCount, count($languagesDevelopment), 'all copied languages detected');
        static::assertTrue(in_array('en', $languagesDevelopment, true), 'English fallback present');

        unlink($path.DIRECTORY_SEPARATOR.'en.json');
        I18nMock::resetAvailableLanguages();
        $languagesDeployed = I18nMock::getAvailableLanguages();
        static::assertSame($languageCount, count($languagesDeployed), 'all copied languages detected, plus fallback');
        static::assertTrue(in_array('en', $languagesDeployed, true), 'English fallback still present');

        I18nMock::resetAvailableLanguages();
        I18nMock::resetPath();
        Helper::rmDir($path);
    }

    public function testMessageIdsExistInAllLanguages(): void
    {
        $messageIds = [];
        $languages = [];
        $dir = dir(PATH.'i18n');
        while (false !== ($file = $dir->read())) {
            if (7 === strlen($file)) {
                $language = substr($file, 0, 2);
                $languageMessageIds = array_keys(
                    json_decode(
                        file_get_contents(PATH.'i18n'.DIRECTORY_SEPARATOR.$file),
                        true
                    )
                );
                $messageIds = array_unique(array_merge($messageIds, $languageMessageIds));
                $languages[$language] = $languageMessageIds;
            }
        }
        foreach ($messageIds as $messageId) {
            foreach (array_keys($languages) as $language) {
                // most languages don't translate the data size units, ignore those
                if ('B' !== $messageId && 3 !== strlen($messageId) && 2 !== strpos($messageId, 'B', 2)) {
                    static::assertContains(
                        $messageId,
                        $languages[$language],
                        "message ID '{$messageId}' exists in translation file {$language}.json"
                    );
                }
            }
        }
    }
}
