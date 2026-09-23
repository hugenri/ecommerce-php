<?php

declare(strict_types=1);

namespace App\Framework\Session;

interface SessionManagerInterface
{
    public function start(array $config = []): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function pull(string $key, mixed $default = null): mixed;
    public function destroy(): void;
    public function save(): void;
    public function getId(): string;
    public function regenerateId(bool $deleteOldSession = true): void;
    public function regenerateIdForced(bool $deleteOldSession = true): void;
    public function isExpired(): bool;
    public function validate(): bool;
}
