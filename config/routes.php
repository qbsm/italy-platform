<?php

declare(strict_types=1);

use App\Action\PageAction;
use App\Action\ApiSendAction;
use Slim\App;

return static function (App $app): void {
    $app->post('/api/send', ApiSendAction::class);
    $app->get('/', PageAction::class);
    $app->get('/{page}[/{params:.*}]', PageAction::class);
};
