<?php

declare(strict_types=1);

namespace Inertia\Tests\Fixtures;

use Generator;
use JsonSerializable;
use Override;
use Tempest\Http\Cookie\Cookie;
use Tempest\Http\Header;
use Tempest\Http\Response;
use Tempest\Http\Status;
use Tempest\View\View;
use UnitEnum;

final class FakeClientResponse implements Response
{
    public Status $status;

    public array $headers = [];

    public function __construct(
        public View|string|array|Generator|JsonSerializable|null $body,
        private readonly bool $isSuccess,
    ) {
        $this->status = $isSuccess ? Status::OK : Status::INTERNAL_SERVER_ERROR;
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccess;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    #[Override]
    public function getHeader(string $name): ?Header
    {
        return null;
    }

    #[Override]
    public function addHeader(string $key, string $value): self
    {
        return $this;
    }

    #[Override]
    public function removeHeader(string $key): self
    {
        return $this;
    }

    #[Override]
    public function addSession(string $name, mixed $value): self
    {
        return $this;
    }

    #[Override]
    public function flash(string|UnitEnum $key, mixed $value): self
    {
        return $this;
    }

    #[Override]
    public function removeSession(string $name): self
    {
        return $this;
    }

    #[Override]
    public function addCookie(Cookie $cookie): self
    {
        return $this;
    }

    #[Override]
    public function removeCookie(string $key): self
    {
        return $this;
    }

    #[Override]
    public function setStatus(Status $status): self
    {
        return $this;
    }

    #[Override]
    public function setBody(View|string|array|Generator|null $body): self
    {
        return $this;
    }
}
