<?php
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
require_once __DIR__ . '/version.php';

class protobyte_cityseo extends CModule
{
    public $MODULE_ID = "protobyte.cityseo";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public function __construct()
    {
        global $arModuleVersion;

        $this->MODULE_ID = "protobyte.cityseo";
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage("PROTOBYTE_CITYSEO_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("PROTOBYTE_CITYSEO_MODULE_DESCRIPTION");
        $this->PARTNER_NAME = Loc::getMessage("PROTOBYTE_CITYSEO_PARTNER_NAME");
        $this->PARTNER_URI = Loc::getMessage("PROTOBYTE_CITYSEO_PARTNER_URI");
    }

    public function InstallDB($arParams = array())
    {
        return true;
    }

    public function UnInstallDB($arParams = array())
    {
        return true;
    }

    public function InstallEvents()
    {
        RegisterModuleDependences('main', 'OnEpilog', $this->MODULE_ID, false, 'protobyte_cityseo_onEpilog', 100, 'include.php');
        RegisterModuleDependences('main', 'OnEndBufferContent', $this->MODULE_ID, false, 'protobyte_cityseo_onEndBufferContent', 100, 'include.php');

        return true;
    }

    public function UnInstallEvents()
    {
        UnRegisterModuleDependences('main', 'OnEpilog', $this->MODULE_ID, false, 'protobyte_cityseo_onEpilog', 100, 'include.php');
        UnRegisterModuleDependences('main', 'OnEndBufferContent', $this->MODULE_ID, false, 'protobyte_cityseo_onEndBufferContent', 100, 'include.php');

        return true;
    }

    public function InstallFiles()
    {
        return true;
    }

    public function UnInstallFiles()
    {
        return true;
    }

    public function DoInstall()
    {
        $this->InstallDB();
        $this->InstallFiles();
        $this->InstallEvents();
        RegisterModule($this->MODULE_ID);
    }

    public function DoUninstall()
    {
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallDB();
        UnRegisterModule($this->MODULE_ID);
    }
}
