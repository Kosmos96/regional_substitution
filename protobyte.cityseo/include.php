<?php
use Bitrix\Main\Localization\Loc;

require_once __DIR__ . '/lib/SeoSectionManager.php';

// Инициализируем GLOBALS при загрузке модуля (до рендеринга шаблона)
if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
    SeoSectionManager::init();
}

function protobyte_cityseo_onBeforeProlog()
{
    SeoSectionManager::init();
}

function protobyte_cityseo_onEpilog()
{
    SeoSectionManager::onEpilog();
}

function protobyte_cityseo_onEndBufferContent(&$content)
{
    SeoSectionManager::onEndBufferContent($content);
}

if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
    AddEventHandler('main', 'OnBeforeProlog', 'protobyte_cityseo_onBeforeProlog');
    AddEventHandler('main', 'OnEpilog', 'protobyte_cityseo_onEpilog');
    AddEventHandler('main', 'OnEndBufferContent', 'protobyte_cityseo_onEndBufferContent');
}
