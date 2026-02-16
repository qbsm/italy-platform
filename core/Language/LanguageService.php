<?php
namespace App\Language;

class LanguageService
{
    private string $currentLangCode;
    private string $defaultLangCode;

    public function __construct(string $currentLangCode, string $defaultLangCode)
    {
        $this->currentLangCode = $currentLangCode;
        $this->defaultLangCode = $defaultLangCode;
    }

    public function getCurrentLanguage(): string
    {
        return $this->currentLangCode;
    }

    public function getDefaultLanguage(): string
    {
        return $this->defaultLangCode;
    }
}

