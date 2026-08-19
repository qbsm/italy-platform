<?php

declare(strict_types=1);

use App\Action\PayCallbackAction;
use App\Action\PayCreateAction;
use App\Action\PayReturnAction;
use App\Service\AlfaGateway;
use App\Service\CallbackVerifier;
use App\Service\EventSeoBuilder;
use App\Service\MailService;
use App\Service\OrderConfirmer;
use App\Service\OrderStore;
use App\Service\RestaurantSeoBuilder;
use App\Service\SeoBuilderRegistry;
use App\Service\TelegramAlertService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Сервисы этого деплоймента: эквайринг Альфа-Банка и телеграм-алерты о сбоях писем.
 * Ядро подмешивает этот файл в контейнер (config/container.php), поэтому синк платформы
 * его не касается — раньше эти определения жили в синкаемом container.php и пропадали.
 */
return [
    // Реестр SEO-строителей живёт здесь, а не в синкаемом container.php: там его затирал
    // очередной distill, и страницы ресторанов теряли title и разметку Restaurant.
    RestaurantSeoBuilder::class => \DI\autowire(),
    EventSeoBuilder::class => \DI\autowire(),
    SeoBuilderRegistry::class => static fn(ContainerInterface $c) => new SeoBuilderRegistry([
        'restaurants' => $c->get(RestaurantSeoBuilder::class),
        'events' => $c->get(EventSeoBuilder::class),
    ]),

    TelegramAlertService::class => static fn(ContainerInterface $c) => new TelegramAlertService(
        $c->get('settings')['alerts'] ?? [],
        $c->get(LoggerInterface::class),
    ),

    // Почта деплоймента умеет жаловаться в телеграм: у ядра этого параметра нет.
    MailService::class => static fn(ContainerInterface $c) => new MailService(
        $c->get(MailerInterface::class),
        $c->get(LoggerInterface::class),
        $c->get('settings')['mail'] ?? [],
        $c->get(TelegramAlertService::class),
    ),

    AlfaGateway::class => static fn(ContainerInterface $c) => new AlfaGateway(
        $c->get('settings')['payment'] ?? [],
        $c->get(LoggerInterface::class),
    ),
    OrderStore::class => static fn(ContainerInterface $c) => new OrderStore(
        (string) ($c->get('settings')['payment']['orders_dir'] ?? ''),
        $c->get(LoggerInterface::class),
    ),
    CallbackVerifier::class => static fn(ContainerInterface $c) => new CallbackVerifier(
        (string) ($c->get('settings')['payment']['callback_token'] ?? ''),
    ),
    OrderConfirmer::class => \DI\autowire(),

    PayCreateAction::class => \DI\autowire()->constructorParameter('settings', \DI\get('settings')),
    PayReturnAction::class => \DI\autowire(),
    PayCallbackAction::class => \DI\autowire(),
];
