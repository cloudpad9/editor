<?php
namespace CloudPad\Core\Session;

/**
 * I18nSessionStore — Typed accessor cho i18n/language session data.
 *
 * Phase 11: Thay thế $_SESSION['lang'] trong Translator.
 */
class I18nSessionStore
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function getLang(): string
    {
        return (string) $this->session->get('lang', '');
    }

    public function setLang(string $lang): void
    {
        $this->session->set('lang', $lang);
    }

    public function hasLang(): bool
    {
        return $this->session->has('lang');
    }
}
