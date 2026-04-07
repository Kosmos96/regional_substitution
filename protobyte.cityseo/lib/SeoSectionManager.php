<?php
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;

/**
 * Менеджер SEO-подмен для разделов сайта.
 */
class SeoSectionManager
{
    private const MODULE_ID = 'protobyte.cityseo';
    private const OPTION_ENABLED = 'cityseo_enabled';
    private const OPTION_SECTION_MAP = 'cityseo_section_map';
    private const BOTTOM_TEXT_CLASS = 'text_after_items';

    public static function isEnabled()
    {
        return COption::GetOptionString(self::MODULE_ID, self::OPTION_ENABLED, 'N') === 'Y';
    }

    public static function getSectionMap()
    {
        $raw = trim(COption::GetOptionString(self::MODULE_ID, self::OPTION_SECTION_MAP, ''));
        $result = [];

        if ($raw === '') {
            return $result;
        }

        $lines = preg_split("/\r\n|\n|\r/", $raw);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            if (strpos($line, '=') === false && strpos($line, ':') === false) {
                continue;
            }

            $parts = preg_split('/\s*(=|:)\s*/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $section = mb_strtolower(trim($parts[0]));
            $hlId = (int)trim($parts[1]);

            if ($section === '' || $hlId <= 0) {
                continue;
            }

            $result[$section] = $hlId;
        }

        return $result;
    }

    public static function normalizeFullUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return '';
        }

        $scheme = !empty($parts['scheme']) ? mb_strtolower($parts['scheme']) : 'https';
        $host = mb_strtolower($parts['host']);
        $path = !empty($parts['path']) ? $parts['path'] : '/';

        $path = '/' . trim($path, '/') . '/';
        $path = preg_replace('#/+#', '/', $path);

        return $scheme . '://' . $host . $path;
    }

    public static function getCurrentFullUrl()
    {
        global $APPLICATION;

        $request = Context::getCurrent()->getRequest();
        $scheme = $request->isHttps() ? 'https' : 'http';
        $host = mb_strtolower($request->getHttpHost());
        $uri = $APPLICATION->GetCurPage(false);

        $uri = '/' . trim($uri, '/') . '/';
        $uri = preg_replace('#/+#', '/', $uri);

        return self::normalizeFullUrl($scheme . '://' . $host . $uri);
    }

    public static function getCurrentSection()
    {
        $map = self::getSectionMap();
        if (empty($map)) {
            return null;
        }

        global $APPLICATION;
        $path = $APPLICATION->GetCurPage(false);
        $path = '/' . trim($path, '/') . '/';
        $path = preg_replace('#/+#', '/', $path);

        $sections = array_keys($map);
        usort($sections, static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($sections as $section) {
            $prefix = '/' . trim($section, '/') . '/';
            if ($path === $prefix || str_starts_with($path, $prefix)) {
                return $section;
            }
        }

        if (count($map) === 1) {
            return array_key_first($map);
        }

        return null;
    }

    public static function getHlIdBySection()
    {
        $map = self::getSectionMap();
        if (empty($map)) {
            return null;
        }

        $section = self::getCurrentSection();
        if ($section !== null && isset($map[$section])) {
            return $map[$section];
        }

        if (count($map) === 1) {
            return array_values($map)[0];
        }

        return null;
    }

    public static function getHlDataClass($hlId)
    {
        static $cache = [];
        $hlId = (int)$hlId;

        if ($hlId <= 0) {
            return null;
        }

        if (isset($cache[$hlId])) {
            return $cache[$hlId];
        }

        $hlBlock = HighloadBlockTable::getById($hlId)->fetch();
        if (!$hlBlock) {
            return null;
        }

        $entity = HighloadBlockTable::compileEntity($hlBlock);
        $cache[$hlId] = $entity->getDataClass();

        return $cache[$hlId];
    }

    public static function getCurrentRule()
    {
        static $rule;
        static $isLoaded = false;

        if ($isLoaded) {
            return $rule;
        }

        $isLoaded = true;
        $rule = false;

        if (!self::isEnabled()) {
            \Bitrix\Main\Diag\Debug::dumpToFile('cityseo_disabled', '/proto-log.log');
            return false;
        }

        if (!Loader::includeModule('highloadblock')) {
            \Bitrix\Main\Diag\Debug::dumpToFile('highloadblock_not_found', '/proto-log.log');
            return false;
        }

        $currentUrl = self::getCurrentFullUrl();
        \Bitrix\Main\Diag\Debug::dumpToFile('current_url: ' . $currentUrl, '/proto-log.log');
        if ($currentUrl === '') {
            \Bitrix\Main\Diag\Debug::dumpToFile('current_url_empty', '/proto-log.log');
            return false;
        }

        $section = self::getCurrentSection();
        $hlId = self::getHlIdBySection();
        \Bitrix\Main\Diag\Debug::dumpToFile('section: ' . ($section ?: 'null') . ', hlId: ' . ($hlId ?: 'null'), '/proto-log.log');
        if (!$hlId) {
            \Bitrix\Main\Diag\Debug::dumpToFile('hlId_null', '/proto-log.log');
            return false;
        }

        $entityClass = self::getHlDataClass($hlId);
        if (!$entityClass) {
            \Bitrix\Main\Diag\Debug::dumpToFile('entity_class_null', '/proto-log.log');
            return false;
        }

        try {
            $record = $entityClass::getList([
                'filter' => [
                    '=UF_ACTIVE' => 1,
                    '=UF_URL' => $currentUrl,
                ],
                'limit' => 1,
            ])->fetch();

            \Bitrix\Main\Diag\Debug::dumpToFile('record_fetch_attempt, found: ' . ($record ? 'yes' : 'no'), '/proto-log.log');
        } catch (\Exception $e) {
            \Bitrix\Main\Diag\Debug::dumpToFile('record_fetch_error: ' . $e->getMessage(), '/proto-log.log');
            return false;
        }

        if (!$record) {
            \Bitrix\Main\Diag\Debug::dumpToFile('no_matching_record', '/proto-log.log');
            return false;
        }

        \Bitrix\Main\Diag\Debug::dumpToFile('record_found_id: ' . $record['ID'], '/proto-log.log');
        \Bitrix\Main\Diag\Debug::dumpToFile('UF_DETAIL_TEXT: ' . (isset($record['UF_DETAIL_TEXT']) ? 'set' : 'not_set'), '/proto-log.log');

        $rule = $record;
        $GLOBALS['SEO_SECTION_RULE'] = $record;

        return $rule;
    }

    public static function onEpilog()
    {
        global $APPLICATION;

        $rule = self::getCurrentRule();
        if (!$rule) {
            return;
        }

        if (!empty($rule['UF_H1'])) {
            $APPLICATION->SetTitle($rule['UF_H1']);
            $GLOBALS['SEO_SECTION_H1'] = $rule['UF_H1'];
        }

        if (!empty($rule['UF_META_TITLE'])) {
            $APPLICATION->SetPageProperty('title', $rule['UF_META_TITLE']);
        }

        if (!empty($rule['UF_META_DESCRIPTION'])) {
            $APPLICATION->SetPageProperty('description', $rule['UF_META_DESCRIPTION']);
        }

        if (!empty($rule['UF_DETAIL_TEXT'])) {
            $GLOBALS['SEO_SECTION_DETAIL_TEXT'] = $rule['UF_DETAIL_TEXT'];
        }

        if (!empty($rule['UF_ADDITIONAL_BOTTOM_TEXT'])) {
            $GLOBALS['SEO_SECTION_ADDITIONAL_BOTTOM_TEXT'] = $rule['UF_ADDITIONAL_BOTTOM_TEXT'];
        }
    }

    public static function onEndBufferContent(&$content)
    {
        $rule = self::getCurrentRule();
        if (!$rule) {
            return;
        }

        if (empty($rule['UF_ADDITIONAL_BOTTOM_TEXT'])) {
            return;
        }

        $newHtml = $rule['UF_ADDITIONAL_BOTTOM_TEXT'];
        $targetClass = self::BOTTOM_TEXT_CLASS;

        $pattern = '~(<([a-z][a-z0-9]*)[^>]*class\s*=\s*["\'][^"\']*' . preg_quote($targetClass, '~') . '[^"\']*["\'][^>]*>)~is';

        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            $openTagFull = $matches[1][0];
            $tagName = $matches[2][0];
            $openTagStart = $matches[0][1];
            $openTagEndPos = $openTagStart + strlen($matches[0][0]);

            $depth = 1;
            $pos = $openTagEndPos;
            $closePattern = '~</?' . preg_quote($tagName, '~') . '(?:\s|>|/)~i';
            $closeTagStart = null;
            $closeTagEnd = null;

            while ($depth > 0 && preg_match($closePattern, $content, $m, PREG_OFFSET_CAPTURE, $pos)) {
                $match = $m[0][0];
                $matchPos = $m[0][1];

                if (strpos($match, '</') === 0) {
                    $depth--;
                    if ($depth === 0) {
                        $closeTag = '</' . $tagName . '>';
                        $closeTagStart = $matchPos;
                        $closeTagEnd = $closeTagStart + strlen($closeTag);
                        break;
                    }
                } else {
                    $depth++;
                }

                $pos = $matchPos + 1;
            }

            if ($depth === 0 && $closeTagStart !== null && $closeTagEnd !== null) {
                $newBlock = $openTagFull . $newHtml . '</' . $tagName . '>';
                $oldBlockLength = $closeTagEnd - $openTagStart;
                $content = substr_replace($content, $newBlock, $openTagStart, $oldBlockLength);
                return;
            }
        }

        if (preg_match('~</body>~i', $content, $bodyMatch, PREG_OFFSET_CAPTURE)) {
            $pos = $bodyMatch[0][1];
            $content = substr_replace($content, $newHtml, $pos, 0);
        }
    }
}
