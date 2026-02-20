<?php

declare(strict_types=1);

use App\Middleware\LanguageMiddleware;
use App\Middleware\RedirectMiddleware;
use App\Middleware\TrailingSlashMiddleware;
use Slim\App;

return static function (App $app): void {
    $settings = $app->getContainer()->get('settings');
    $displayErrorDetails = (bool) ($settings['twig']['debug'] ?? false);

    $app->add(LanguageMiddleware::class);
    $app->add(RedirectMiddleware::class);
    $app->add(TrailingSlashMiddleware::class);

    $app->addRoutingMiddleware();
    $app->addErrorMiddleware($displayErrorDetails, true, true);
};
