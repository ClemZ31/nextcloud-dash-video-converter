<?php

namespace OCA\Video_Converter_Fm\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Util;

class Application extends App implements IBootstrap {

    public function __construct(array $urlParams = []) {
        parent::__construct('video_converter_fm', $urlParams);
    }

    /**
     * Register services and event listeners using the AppFramework bootstrap.
     * This replaces the deprecated appinfo/app.php usage.
     *
     * @param IRegistrationContext $context
     */
    public function register(IRegistrationContext $context): void {
        // Register the ConversionController service so the router can instantiate it.
        // IRegistrationContext exposes registerService(...) rather than getContainer().
    $appName = 'video_converter_fm'; // Static app name to avoid relying on deprecated helpers
        
        // Register ConversionController
        $context->registerService('ConversionController', function($c) use ($appName) {
            // Resolve current user and a guaranteed IRequest instance from the global server
            $user = \OC::$server->getUserSession()->getUser();
            $userId = $user ? $user->getUID() : null;

            // Always get the Request from the global server to avoid null injection/type errors
            $request = \OC::$server->getRequest();

            return new \OCA\Video_Converter_Fm\Controller\ConversionController(
                $appName,
                $request,
                $userId
            );
        });
        
        // Register PageController
        $context->registerService('PageController', function($c) use ($appName) {
            $user = \OC::$server->getUserSession()->getUser();
            $userId = $user ? $user->getUID() : null;
            $request = \OC::$server->getRequest();

            return new \OCA\Video_Converter_Fm\Controller\PageController(
                $appName,
                $request,
                $userId
            );
        });

    }

    /**
     * Boot is called after all apps are registered. Keep it as a no-op for now.
     *
     * @param IBootContext $context
     */
    public function boot(IBootContext $context): void {
        // Charger le script d'intégration Files sur toutes les pages
        // (le script lui-même vérifie s'il est sur la page Files avant de s'enregistrer)
        Util::addScript('video_converter_fm', 'conversion');
    }
}
