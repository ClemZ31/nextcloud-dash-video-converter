<?php
/**
 *  INUTILE !!!
 * Bootstrap the application (AppFramework v3 compatible) and load Javascript
 */
require_once __DIR__ . '/../lib/AppInfo/Application.php';
$app = new \OCA\Video_Converter_Test_Clement\AppInfo\Application();

use OCP\Util;
$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener('OCA\\Files::loadAdditionalScripts', function(){
    Util::addScript('video_converter_test_clement', 'conversion' );
    Util::addStyle('video_converter_test_clement', 'style' );
});
