<?php
namespace CloudPad\Auth;

interface AuthServiceInterface
{
    public function auth(): void;
    public function ensureAuth(bool $authed): void;
    public function isUserLoggedIn(): bool;
    public function getUserSessionId(): string;
    public function getUserDataDir(): string;
    public function getUserUploadDir(): string;
    public function getUserRepositoryDir(): string;
    public function getUserTempDir(): string;
    public function getUserRevisionDir(): string;
    public function getUserTempRevisionDir(): string;
    public function serializeUserSessionData(): void;
    public function reloadUserSessionData(): void;
}
