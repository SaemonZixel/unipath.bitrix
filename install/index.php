<?php

use \Bitrix\Main\ModuleManager;
 
Class unipath_bitrix extends CModule
{
    public $MODULE_ID = "unipath.bitrix";
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
 
    function __construct()
    {
        $this->MODULE_VERSION = "2.4rc3";
        $this->MODULE_VERSION_DATE = "06.08.2020";
        $this->MODULE_NAME = "UniPath";
        $this->MODULE_DESCRIPTION = "Поддержка библиотеки UniPath.php для Bitrix CMS";
        $this->PARTNER_NAME = "Saemon Zixel";
        $this->PARTNER_URI = "";
    }
 
    function DoInstall()
    {
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
        RegisterModule("unipath.bitrix");
        return true;
    }
 
    function DoUninstall()
    {
        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        UnRegisterModule("unipath.bitrix");
        return true;
    }
 
    function InstallDB()
    {
        return true;
    }
 
    function UnInstallDB()
    {
        return true;
    }
 
    function InstallEvents()
    {
        RegisterModuleDependences("main", "OnPageStart", $this->MODULE_ID);
        return true;
    }
 
    function UnInstallEvents()
    {
        UnRegisterModuleDependences("main", "OnPageStart", $this->MODULE_ID);
        return true;
    }
 
    function InstallFiles()
    {
        return true;
    }
 
    function UnInstallFiles()
    {
        return true;
    }
}