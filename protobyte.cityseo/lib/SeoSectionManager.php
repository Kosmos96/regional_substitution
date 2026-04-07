<?php
use Bitrix\Main\Context;
use Bitrix\Main\Loader;
use Bitrix\Main\Diag\Debug;
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
            Debug::dumpToFile(['method' => 'getSectionMap', 'raw' => 'empty'], 'SeoSectionManager', '/proto-log.log');
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

        Debug::dumpToFile(['method' => 'getSectionMap', 'result' => $result], 'SeoSectionManager', '/proto-log.log');
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

        $fullUrl = self::normalizeFullUrl($scheme . '://' . $host . $uri);
        Debug::dumpToFile(['method' => 'getCurrentFullUrl', 'scheme' => $scheme, 'host' => $host, 'uri' => $uri, 'fullUrl' => $fullUrl], 'SeoSectionManager', '/proto-log.log');

        return $fullUrl;
    }


    public static function getCurrentSection()
    {
        $map = self::getSectionMap();
        if (empty($map)) {
            Debug::dumpToFile(['method' => 'getCurrentSection', 'error' => 'map_empty'], 'SeoSectionManager', '/proto-log.log');
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
                Debug::dumpToFile(['method' => 'getCurrentSection', 'path' => $path, 'section' => $section, 'prefix' => $prefix], 'SeoSectionManager', '/proto-log.log');
                return $section;
            }
        }

        if (count($map) === 1) {
            $onlySection = array_key_first($map);
            Debug::dumpToFile(['method' => 'getCurrentSection', 'path' => $path, 'only_section' => $onlySection], 'SeoSectionManager', '/proto-log.log');
            return $onlySection;
        }

        Debug::dumpToFile(['method' => 'getCurrentSection', 'path' => $path, 'not_matched', 'sections' => array_keys($map)], 'SeoSectionManager', '/proto-log.log');
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

        Debug::dumpToFile(['step' => 'start', 'enabled' => self::isEnabled()], 'SeoSectionManager', '/proto-log.log');

        if (!self::isEnabled()) {
            Debug::dumpToFile(['step' => 'disabled'], 'SeoSectionManager', '/proto-log.log');
            return false;
        }

        if (!Loader::includeModule('highloadblock')) {
            Debug::dumpToFile(['step' => 'highloadblock_not_loaded'], 'SeoSectionManager', '/proto-log.log');
            return false;
        }

        $currentUrl = self::getCurrentFullUrl();
        Debug::dumpToFile(['step' => 'current_url', 'url' => $currentUrl], 'SeoSectionManager', '/proto-log.log');

        if ($currentUrl === '') {
            Debug::dumpToFile(['step' => 'current_url_empty'], 'SeoSectionManager', '/proto-log.log');
            return false;
        }

        $hlId = self::getHlIdBySection();
        $section = self::getCurrentSection();
        $sectionMap = self::getSectionMap();
        Debug::dumpToFile(['step' => 'section_check', 'section' => $section, 'hlId' => $hlId, 'map' => $sectionMap], 'SeoSectionManager', '/proto-log.log');

        if (!$hlId) {
            Debug::dumpToFile(['step' => 'hl_id_not_found'], 'SeoSectionManager', '/proto-log.log');
            return false;
        }

        $entityClass = self::getHlDataClass($hlId);
        if (!$entityClass) {
            Debug::dumpToFile(['step' => 'entity_class_not_found', 'hlId' => $hlId], 'SeoSectionManager', '/proto-log.log');
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

            Debug::dumpToFile(['step' => 'fetch_result', 'found' => (bool)$record, 'url' => $currentUrl, 'hlId' => $hlId], 'SeoSectionManager', '/proto-log.log');

            if (!$record) {
                return false;
            }

            Debug::dumpToFile(['step' => 'record_found', 'record_id' => $record['ID']], 'SeoSectionManager', '/proto-log.log');

            $rule = $record;
            $GLOBALS['SEO_SECTION_RULE'] = $record;

            return $rule;
        } catch (\Throwable $e) {
            Debug::dumpToFile(['step' => 'exception', 'error' => $e->getMessage()], 'SeoSectionManager', '/proto-log.log');
            return false;
        }
    }

    public static function addLog($message)
    {
        Debug::dumpToFile($message, 'SeoSectionManager', '/proto-log.log');
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
