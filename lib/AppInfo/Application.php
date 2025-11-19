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
        $appName = $this->appName ?? 'video_converter_fm';

        // Register ConversionController
        $context->registerService('ConversionController', function($c) use ($appName) {
            $user = \OC::$server->getUserSession()->getUser();
            $userId = $user ? $user->getUID() : null;
            $request = \OC::$server->getRequest();
            $conversionService = $c->query('OCA\\Video_Converter_Fm\\Service\\ConversionService');
            $jobMapper = $c->query('OCA\\Video_Converter_Fm\\Db\\VideoJobMapper');
            $logger = \OC::$server->get(\OCP\ILogger::class);
            $groupManager = \OC::$server->getGroupManager();
            return new \OCA\Video_Converter_Fm\Controller\ConversionController(
                $appName,
                $request,
                $userId,
                $conversionService,
                $jobMapper,
                $logger,
                $groupManager
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
