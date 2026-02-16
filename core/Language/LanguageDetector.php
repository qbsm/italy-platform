<?php
namespace App\Language;

class LanguageDetector
{
    /**
     * @param string[] $segments Сегменты пути без ведущего языка
     * @param array $supportedLanguages Ассоциативный массив code=>info (как в global.json)
     * @param string $defaultCode Код языка по умолчанию
     * @return array{lang_code:string,current_lang:array,is_lang_in_url:bool,segments:array}
     */
    public function detect(array $segments, array $supportedLanguages, string $defaultCode): array
    {
        $langCode = $defaultCode;
        $currentLang = $supportedLanguages[$defaultCode] ?? ['code' => $defaultCode];
        $isInUrl = false;
        $resultSegments = array_values($segments);

        if (!empty($resultSegments) && isset($supportedLanguages[$resultSegments[0]])) {
            $langCode = (string)$resultSegments[0];
            $currentLang = $supportedLanguages[$langCode];
            $isInUrl = true;
            array_shift($resultSegments);
        }

        return [
            'lang_code' => $langCode,
            'current_lang' => $currentLang,
            'is_lang_in_url' => $isInUrl,
            'segments' => array_values($resultSegments),
        ];
    }
}

