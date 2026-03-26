<?php
namespace CloudPad\I18n;

class Translator
{
    private string $appDir;

    private \CloudPad\Core\Session\I18nSessionStore $i18nSession;

    public function __construct(string $appDir, \CloudPad\Core\Session\I18nSessionStore $i18nSession)
    {
        $this->appDir      = $appDir;
        $this->i18nSession = $i18nSession;
    }

    public function getLang(): string
    {
        $lang = \CloudPad\Core\Request::getString('lang');

        if (!empty($lang)) {
            $lang = preg_replace('/[^a-zA-Z0-9\-]/', '', $lang);
        } elseif ($this->i18nSession->hasLang()) {
            $lang = $this->i18nSession->getLang();
        } elseif (!empty($_COOKIE['lang'])) {
            $lang = $_COOKIE['lang'];
        } else {
            $lang = $this->getBrowserLanguage();
        }

        return (string)$lang;
    }

    public function loadLanguageFile(): void
    {
        $lang = $this->getLang();

        setcookie('lang', $lang, time() + 86400, '/');
        $this->i18nSession->setLang($lang);

        $langfile = $this->appDir . "/locales/{$lang}.php";

        if (file_exists($langfile)) {
            require_once($langfile);
        }
    }

    public function getUserLanguage(): string
    {
        return $this->getBrowserLanguage();
    }

    public function getBrowserLanguage(): string
    {
        return isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)
            : 'en';
    }
}
