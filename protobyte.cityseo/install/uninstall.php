<?php
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$module = new protobyte_cityseo();
$module->DoUninstall();
echo Loc::getMessage('PROTOBYTE_CITYSEO_UNINSTALL_COMPLETE');
