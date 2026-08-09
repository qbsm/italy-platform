<?php

declare(strict_types=1);

use App\Action\ApiSendAction;
use App\Action\ApiWidgetRescueAction;
use App\Action\HealthAction;
use App\Action\PageAction;
use App\Action\PayCallbackAction;
use App\Action\PayCreateAction;
use App\Action\PayReturnAction;
use App\Action\SitemapAction;
use Slim\App;

return static function (App $app): void {
    $app->get('/health', HealthAction::class);
    $app->post('/api/send', ApiSendAction::class);
    $app->post('/api/pay', PayCreateAction::class);
    $app->get('/pay/return', PayReturnAction::class);
    $app->get('/pay/callback', PayCallbackAction::class);
    $app->get('/sitemap.xml', SitemapAction::class);
    $app->get('/', PageAction::class);
    $app->get('/{page}[/{params:.*}]', PageAction::class);
};
