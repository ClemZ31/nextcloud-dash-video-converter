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
     */
    public function register(IRegistrationContext $context): void {
        // On définit le nom en dur pour éviter toute ambiguïté
        $appName = 'video_converter_fm';

        // Register ConversionController
        $context->registerService('ConversionController', function($c) use ($appName) {
            $user = \OC::$server->getUserSession()->getUser();
            $userId = $user ? $user->getUID() : null;
            $request = \OC::$server->getRequest();

            // On utilise $c->query() avec ::class pour être sûr de récupérer les bons objets
            return new \OCA\Video_Converter_Fm\Controller\ConversionController(
                $appName,                                                            // 1. AppName
                $request,                                                            // 2. Request
                $userId,                                                             // 3. UserId
                $c->query(\OCA\Video_Converter_Fm\Service\ConversionService::class), // 4. Service
                $c->query(\OCA\Video_Converter_Fm\Db\VideoJobMapper::class),         // 5. Mapper
                $c->query(\Psr\Log\LoggerInterface::class),                          // 6. Logger (Interface standard PSR)
                $c->query(\OCP\IGroupManager::class),                                // 7. GroupManager
                $c->query(\OCP\Files\IRootFolder::class)                             // 8. RootFolder
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

    public function boot(IBootContext $context): void {
        Util::addScript('video_converter_fm', 'conversion');
    }
}