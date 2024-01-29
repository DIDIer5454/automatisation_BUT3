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

use AppendIterator;
use GlobIterator;

/**
 * I18n.
 *
 * provides internationalization tools like translation, browser language detection, etc.
 */
class I18n
{
    /**
     * language.
     *
     * @static
     *
     * @var string
     */
    protected static $_language = 'en';

    /**
     * language fallback.
     *
     * @static
     *
     * @var string
     */
    protected static $_languageFallback = 'en';

    /**
     * language labels.
     *
     * @static
     *
     * @var array
     */
    protected static $_languageLabels = [];

    /**
     * available languages.
     *
     * @static
     *
     * @var array
     */
    protected static $_availableLanguages = [];

    /**
     * path to language files.
     *
     * @static
     *
     * @var string
     */
    protected static $_path = '';

    /**
     * translation cache.
     *
     * @static
     *
     * @var array
     */
    protected static $_translations = [];

    /**
     * translate a string, alias for translate().
     *
     * @static
     *
     * @param string $messageId
     * @param mixed  $args      one or multiple parameters injected into placeholders
     *
     * @return string
     */
    public static function _($messageId)
    {
        return forward_static_call_array('PrivateBin\I18n::translate', \func_get_args());
    }

    /**
     * translate a string.
     *
     * @static
     *
     * @param string $messageId
     * @param mixed  $args      one or multiple parameters injected into placeholders
     *
     * @return string
     */
    public static function translate($messageId)
    {
        if (empty($messageId)) {
            return $messageId;
        }
        if (0 === \count(self::$_translations)) {
            self::loadTranslations();
        }
        $messages = $messageId;
        if (\is_array($messageId)) {
            $messageId = \count($messageId) > 1 ? $messageId[1] : $messageId[0];
        }
        if (!\array_key_exists($messageId, self::$_translations)) {
            self::$_translations[$messageId] = $messages;
        }
        $args = \func_get_args();
        if (\is_array(self::$_translations[$messageId])) {
            $number = (int) $args[1];
            $key = self::_getPluralForm($number);
            $max = \count(self::$_translations[$messageId]) - 1;
            if ($key > $max) {
                $key = $max;
            }

            $args[0] = self::$_translations[$messageId][$key];
            $args[1] = $number;
        } else {
            $args[0] = self::$_translations[$messageId];
        }
        // encode any non-integer arguments and the message ID, if it doesn't contain a link
        $argsCount = \count($args);
        if ($argsCount > 1) {
            for ($i = 0; $i < $argsCount; ++$i) {
                if (($i > 0 && !\is_int($args[$i])) || !str_contains($args[0], '<a')) {
                    $args[$i] = self::encode($args[$i]);
                }
            }
        }

        return \call_user_func_array('sprintf', $args);
    }

    /**
     * encode HTML entities for output into an HTML5 document.
     *
     * @static
     *
     * @param string $string
     *
     * @return string
     */
    public static function encode($string)
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5 | ENT_DISALLOWED, 'UTF-8', false);
    }

    /**
     * loads translations.
     *
     * From: https://stackoverflow.com/questions/3770513/detect-browser-language-in-php#3771447
     *
     * @static
     */
    public static function loadTranslations(): void
    {
        $availableLanguages = self::getAvailableLanguages();

        // check if the lang cookie was set and that language exists
        if (\array_key_exists('lang', $_COOKIE)
            && ($key = array_search($_COOKIE['lang'], $availableLanguages, true)) !== false
        ) {
            $match = $availableLanguages[$key];
        }
        // find a translation file matching the browsers language preferences
        else {
            $match = self::_getMatchingLanguage(
                self::getBrowserLanguages(),
                $availableLanguages
            );
        }

        // load translations
        self::$_language = $match;
        self::$_translations = ('en' === $match) ? [] : Json::decode(
            file_get_contents(self::_getPath($match.'.json'))
        );
    }

    /**
     * get list of available translations based on files found.
     *
     * @static
     *
     * @return array
     */
    public static function getAvailableLanguages()
    {
        if (0 === \count(self::$_availableLanguages)) {
            self::$_availableLanguages[] = 'en'; // en.json is not part of the release archive
            $languageIterator = new AppendIterator();
            $languageIterator->append(new GlobIterator(self::_getPath('??.json')));
            $languageIterator->append(new GlobIterator(self::_getPath('???.json'))); // for jbo
            foreach ($languageIterator as $file) {
                $language = $file->getBasename('.json');
                if ('en' !== $language) {
                    self::$_availableLanguages[] = $language;
                }
            }
        }

        return self::$_availableLanguages;
    }

    /**
     * detect the clients supported languages and return them ordered by preference.
     *
     * From: https://stackoverflow.com/questions/3770513/detect-browser-language-in-php#3771447
     *
     * @static
     *
     * @return array
     */
    public static function getBrowserLanguages()
    {
        $languages = [];
        if (\array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
            $languageRanges = explode(',', trim($_SERVER['HTTP_ACCEPT_LANGUAGE']));
            foreach ($languageRanges as $languageRange) {
                if (preg_match(
                    '/(\*|[a-zA-Z0-9]{1,8}(?:-[a-zA-Z0-9]{1,8})*)(?:\s*;\s*q\s*=\s*(0(?:\.\d{0,3})|1(?:\.0{0,3})))?/',
                    trim($languageRange),
                    $match
                )
                ) {
                    if (!isset($match[2])) {
                        $match[2] = '1.0';
                    } else {
                        $match[2] = (string) (float) ($match[2]);
                    }
                    if (!isset($languages[$match[2]])) {
                        $languages[$match[2]] = [];
                    }
                    $languages[$match[2]][] = strtolower($match[1]);
                }
            }
            krsort($languages);
        }

        return $languages;
    }

    /**
     * get currently loaded language.
     *
     * @static
     *
     * @return string
     */
    public static function getLanguage()
    {
        return self::$_language;
    }

    /**
     * get list of language labels.
     *
     * Only for given language codes, otherwise all labels.
     *
     * @static
     *
     * @param array $languages
     *
     * @return array
     */
    public static function getLanguageLabels($languages = [])
    {
        $file = self::_getPath('languages.json');
        if (0 === \count(self::$_languageLabels) && is_readable($file)) {
            self::$_languageLabels = Json::decode(file_get_contents($file));
        }
        if (0 === \count($languages)) {
            return self::$_languageLabels;
        }

        return array_intersect_key(self::$_languageLabels, array_flip($languages));
    }

    /**
     * determines if the current language is written right-to-left (RTL).
     *
     * @static
     *
     * @return bool
     */
    public static function isRtl()
    {
        return \in_array(self::$_language, ['ar', 'he'], true);
    }

    /**
     * set the default language.
     *
     * @static
     *
     * @param string $lang
     */
    public static function setLanguageFallback($lang): void
    {
        if (\in_array($lang, self::getAvailableLanguages(), true)) {
            self::$_languageFallback = $lang;
        }
    }

    /**
     * get language file path.
     *
     * @static
     *
     * @param string $file
     *
     * @return string
     */
    protected static function _getPath($file = '')
    {
        if (empty(self::$_path)) {
            self::$_path = PUBLIC_PATH.\DIRECTORY_SEPARATOR.'i18n';
        }

        return self::$_path.(empty($file) ? '' : \DIRECTORY_SEPARATOR.$file);
    }

    /**
     * determines the plural form to use based on current language and given number.
     *
     * From: https://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
     *
     * @static
     *
     * @param int $n
     *
     * @return int
     */
    protected static function _getPluralForm($n)
    {
        switch (self::$_language) {
        case 'ar':
            return 0 === $n ? 0 : (1 === $n ? 1 : (2 === $n ? 2 : ($n % 100 >= 3 && $n % 100 <= 10 ? 3 : ($n % 100 >= 11 ? 4 : 5))));

        case 'cs':
        case 'sk':
            return 1 === $n ? 0 : ($n >= 2 && $n <= 4 ? 1 : 2);

        case 'co':
        case 'fr':
        case 'oc':
        case 'tr':
        case 'zh':
            return $n > 1 ? 1 : 0;

        case 'he':
            return 1 === $n ? 0 : (2 === $n ? 1 : (($n < 0 || $n > 10) && (0 === $n % 10) ? 2 : 3));

        case 'id':
        case 'ja':
        case 'jbo':
        case 'th':
            return 0;

        case 'lt':
            return 1 === $n % 10 && 11 !== $n % 100 ? 0 : (($n % 10 >= 2 && $n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);

        case 'pl':
            return 1 === $n ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);

        case 'ru':
        case 'uk':
            return 1 === $n % 10 && 11 !== $n % 100 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);

        case 'sl':
            return 1 === $n % 100 ? 1 : (2 === $n % 100 ? 2 : (3 === $n % 100 || 4 === $n % 100 ? 3 : 0));

        default:
            // bg, ca, de, el, en, es, et, fi, hu, it, nl, no, pt
            return 1 !== $n ? 1 : 0;
        }
    }

    /**
     * compares two language preference arrays and returns the preferred match.
     *
     * From: https://stackoverflow.com/questions/3770513/detect-browser-language-in-php#3771447
     *
     * @static
     *
     * @param array $acceptedLanguages
     * @param array $availableLanguages
     *
     * @return string
     */
    protected static function _getMatchingLanguage($acceptedLanguages, $availableLanguages)
    {
        $matches = [];
        $any = false;
        foreach ($acceptedLanguages as $acceptedQuality => $acceptedValues) {
            $acceptedQuality = (float) $acceptedQuality;
            if (0.0 === $acceptedQuality) {
                continue;
            }
            foreach ($availableLanguages as $availableValue) {
                $availableQuality = 1.0;
                foreach ($acceptedValues as $acceptedValue) {
                    if ('*' === $acceptedValue) {
                        $any = true;
                    }
                    $matchingGrade = self::_matchLanguage($acceptedValue, $availableValue);
                    if ($matchingGrade > 0) {
                        $q = (string) ($acceptedQuality * $availableQuality * $matchingGrade);
                        if (!isset($matches[$q])) {
                            $matches[$q] = [];
                        }
                        if (!\in_array($availableValue, $matches[$q], true)) {
                            $matches[$q][] = $availableValue;
                        }
                    }
                }
            }
        }
        if (0 === \count($matches) && $any) {
            if (\count($availableLanguages) > 0) {
                $matches['1.0'] = $availableLanguages;
            }
        }
        if (0 === \count($matches)) {
            return self::$_languageFallback;
        }
        krsort($matches);
        $topmatches = current($matches);

        return current($topmatches);
    }

    /**
     * compare two language IDs and return the degree they match.
     *
     * From: https://stackoverflow.com/questions/3770513/detect-browser-language-in-php#3771447
     *
     * @static
     *
     * @param string $a
     * @param string $b
     *
     * @return float
     */
    protected static function _matchLanguage($a, $b)
    {
        $a = explode('-', $a);
        $b = explode('-', $b);
        for ($i = 0, $n = min(\count($a), \count($b)); $i < $n; ++$i) {
            if ($a[$i] !== $b[$i]) {
                break;
            }
        }

        return 0 === $i ? 0 : (float) $i / \count($a);
    }
}
