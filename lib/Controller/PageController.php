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
     * Affiche la page principale de l'application
     * 
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @return TemplateResponse
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
     * Affiche la page des paramètres de l'application
     * 
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @return TemplateResponse
     */
    public function settings(): TemplateResponse {
        // Settings is now handled by Vue Router, redirect to index
        return $this->index();
    }

    /**
     * Affiche la page des conversions en cours
     * 
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * @return TemplateResponse
     */
    public function conversions(): TemplateResponse {
        // Conversions is now handled by Vue Router, redirect to index
        return $this->index();
    }
}
