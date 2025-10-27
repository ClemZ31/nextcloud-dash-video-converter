<?php

namespace OCA\Video_Converter_Test_Clement\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\Util;

class Application extends App implements IBootstrap {

    public function __construct(array $urlParams = []) {
        parent::__construct('video_converter_test_clement', $urlParams);
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
        $appName = $this->getAppName(); // Capture app name outside the closure
        $context->registerService('ConversionController', function($c) use ($appName) {
            // Resolve current user and a guaranteed IRequest instance from the global server
            $user = \OC::$server->getUserSession()->getUser();
            $userId = $user ? $user->getUID() : null;

            // Always get the Request from the global server to avoid null injection/type errors
            $request = \OC::$server->getRequest();

            return new \OCA\Video_Converter_Test_Clement\Controller\ConversionController(
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
        // Register assets for the current request.
        // Boot runs during each request, ensuring Files pages load our JS/CSS.
        Util::addScript('video_converter_test_clement', 'conversion');
        Util::addStyle('video_converter_test_clement', 'style');
    }
}
