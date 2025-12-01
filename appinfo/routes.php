<?php
/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\Extract\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
    'routes' => [
       [
            'name' => 'conversion#convertHere',
            'url'  => 'ajax/convertHere.php',
            'verb' => 'POST'
        ],
        [
            'name' => 'conversion#listJobs',
            'url'  => 'api/jobs',
            'verb' => 'GET'
        ],
        [
            'name' => 'conversion#listAllJobs',
            'url'  => 'api/jobs/all',
            'verb' => 'GET'
        ],
        [
            'name' => 'conversion#getJobStatus',
            'url'  => 'api/jobs/{jobId}',
            'verb' => 'GET'
        ],
        [
            'name' => 'conversion#deleteJob',
            'url'  => 'api/jobs/{jobId}',
            'verb' => 'DELETE'
        ],
        [
            'name' => 'conversion#probeVideo',
            'url'  => 'api/video/probe',
            'verb' => 'POST'
        ],
        [
            'name' => 'page#index', 
            'url' => '/', 
            'verb' => 'GET'
        ],
        [
            'name' => 'page#settings', 
            'url' => 'settings', 
            'verb' => 'GET'
        ],
        [
            'name' => 'page#conversions', 
            'url' => 'conversions', 
            'verb' => 'GET'
        ],
        [
            'name' => 'conversion#checkDeleteOrCancel', 
            'url' => 'api/jobs/{id}/action/{action}', 
            'verb' => 'GET'
        ],
        [
            'name' => 'conversion#deleteOrCancel', 
            'url' => 'api/jobs/{id}/action/{action}', 
            'verb' => 'POST'
        ]
    ]
];
