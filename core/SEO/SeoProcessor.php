<?php
namespace App\SEO;

use Twig\Environment;

class SeoProcessor
{
    /**
     * Рендер плейсхолдеров в SEO-данных через Twig (если встречаются строки-шаблоны)
     * Возвращает массив с теми же ключами, значения отрендерены.
     */
    public function processSeoTemplates(array $seoData, array $context, Environment $twig): array
    {
        $render = function ($value) use (&$render, $context, $twig) {
            if (is_array($value)) {
                $result = [];
                foreach ($value as $k => $v) {
                    $result[$k] = $render($v);
                }
                return $result;
            }
            if (is_string($value)) {
                // Рендерим как строковый шаблон
                return $twig->createTemplate($value)->render($context);
            }
            return $value;
        };

        return $render($seoData);
    }
}

