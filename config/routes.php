<?php

declare(strict_types=1);

use App\Action\PageAction;
use Slim\App;

return static function (App $app): void {
    $app->get('/', PageAction::class);
    $app->get('/{page}[/{params:.*}]', PageAction::class);
};
