<?php

declare(strict_types=1);

use App\Action\PayCallbackAction;
use App\Action\PayCreateAction;
use App\Action\PayReturnAction;
use Slim\App;

/**
 * Маршруты этого деплоймента: оплата билетов на события. Объявляются ядром до catch-all
 * страницы (config/routes.php), поэтому синк платформы их больше не сносит.
 */
return static function (App $app): void {
    $app->post('/api/pay', PayCreateAction::class);
    $app->get('/pay/return', PayReturnAction::class);
    $app->get('/pay/callback', PayCallbackAction::class);
};
