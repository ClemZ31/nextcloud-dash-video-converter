<?php

namespace OCA\Video_Converter_Fm\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\Util;

class PageController extends Controller {
    
    private $userId;
    
    public function __construct(
        $appName,
        IRequest $request,
        $userId
    ) {
        parent::__construct($appName, $request);
        $this->userId = $userId;
    }
    
    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        // Load Vue app
    Util::addScript('video_converter_fm', 'conversions-app');
        // CSS emitted by Vite as css/style.css when cssCodeSplit=false
    Util::addStyle('video_converter_fm', 'style');
        
        return new TemplateResponse(
            'video_converter_fm',
            'main',
            []
        );
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function settings(): TemplateResponse {
        // Settings is now handled by Vue Router, redirect to index
        return $this->index();
    }
}
