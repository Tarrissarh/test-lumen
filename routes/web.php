<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    $rows = array();
    $routeCollection = $router->getRoutes();

    foreach ($routeCollection as $route) {
        $rows[$route['method']][] = [
            'path'       => $route['uri'],
            'controller' => $route['uses'] ?? 'None',
        ];
    }

    foreach ($rows as $method => $row) {
        echo "<b>Метод {$method}:</b><br>";

        foreach ($row as $action) {
            echo "URL: {$action['path']}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Контроллер: {$action['controller']}<br>";
        }

        echo "<br>";
    }

    return $router->app->version();
});

$router->post('/send-code', [
    'as' => 'send-code', 'uses' => '\App\Http\Controllers\CheckController@sendCode'
]);

$router->post('/check-code', [
    'as' => 'check-code', 'uses' => '\App\Http\Controllers\CheckController@checkCode'
]);
