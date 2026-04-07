<?php
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$module = new protobyte_cityseo();
$module->DoInstall();
echo Loc::getMessage('PROTOBYTE_CITYSEO_INSTALL_COMPLETE');
