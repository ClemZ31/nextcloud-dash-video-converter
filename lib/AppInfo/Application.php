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
        $context->registerService('ConversionController', function($c) {
            // We use the global server to fetch the current user id if available.
            $user = \OC::$server->getUserSession()->getUser();
            $userId = $user ? $user->getUID() : null;

            // $c is the DI container passed by the framework; query the Request from it.
            $request = null;
            if (is_object($c) && method_exists($c, 'query')) {
                $request = $c->query('Request');
            }

            return new \OCA\Video_Converter_Test_Clement\Controller\ConversionController(
                $this->getAppName(),
                $request,
                $userId
            );
        });

        // Register assets (registering directly here is acceptable for simple assets).
        // Using the event dispatcher API changed; register scripts/styles directly.
        Util::addScript('video_converter_test_clement', 'conversion');
        Util::addStyle('video_converter_test_clement', 'style');
    }

    /**
     * Boot is called after all apps are registered. Keep it as a no-op for now.
     *
     * @param IBootContext $context
     */
    public function boot(IBootContext $context): void {
        // No runtime boot actions required currently.
    }
}
