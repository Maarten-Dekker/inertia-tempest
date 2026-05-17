<?php

declare(strict_types=1);

namespace Inertia\Traits;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Tempest\DateTime\DateTime;
use Tempest\DateTime\Duration;
use UnitEnum;

trait ResolvesOnce
{
    /**
     * Indicates if the prop should be resolved only once.
     */
    protected bool $once = false;

    /**
     * Indicates if the prop should be forcefully refreshed.
     */
    protected bool $refresh = false;

    /**
     * The expiration timestamp in milliseconds.
     */
    protected ?int $expiresAt = null;

    /**
     * The custom key for resolving the once prop.
     */
    protected ?string $key = null;

    /**
     * Mark the prop to be resolved only once.
     */
    public function once(
        bool $value = true,
        BackedEnum|UnitEnum|null|string $as = null,
        DateTimeInterface|\Tempest\DateTime\DateTimeInterface|DateInterval|Duration|int|null $until = null,
    ): static {
        $clone = clone($this, [
            'once' => $value,
        ]);

        if ($as !== null) {
            $clone = $clone->as($as);
        }

        if ($until !== null) {
            return $clone->until($until);
        }

        return $clone;
    }

    /**
     * Determine if the prop should be resolved only once.
     */
    public function shouldResolveOnce(): bool
    {
        return $this->once;
    }

    /**
     * Determine if the prop should be forcefully refreshed.
     */
    public function shouldBeRefreshed(): bool
    {
        return $this->refresh;
    }

    /**
     * Get the custom key for resolving the once prop.
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Set a custom key for resolving the once prop.
     */
    public function as(BackedEnum|UnitEnum|string $key): static
    {
        return clone($this, [
            'key' => match (true) {
                $key instanceof BackedEnum => (string) $key->value,
                $key instanceof UnitEnum => $key->name,
                default => $key,
            },
        ]);
    }

    /**
     * Mark the property to be forcefully sent to the client.
     */
    public function fresh(bool $value = true): static
    {
        return clone($this, [
            'refresh' => $value,
        ]);
    }

    /**
     * Set the expiration for the once prop.
     */
    public function until(DateTimeInterface|\Tempest\DateTime\DateTimeInterface|DateInterval|Duration|int $delay): static
    {
        $milliseconds = match (true) {
            $delay instanceof \Tempest\DateTime\DateTimeInterface => $delay->getTimestamp()->getMilliseconds(),
            $delay instanceof DateTimeInterface => (int) DateTimeImmutable::createFromInterface($delay)->format('Uv'),
            $delay instanceof DateInterval => (int) new DateTimeImmutable()->add($delay)->format('Uv'),
            $delay instanceof Duration => DateTime::now()->plus($delay)->getTimestamp()->getMilliseconds(),
            default => DateTime::now()->plusSeconds($delay)->getTimestamp()->getMilliseconds(),
        };

        return clone($this, ['expiresAt' => $milliseconds]);
    }

    /**
     * Get the expiration timestamp in milliseconds for the once prop.
     */
    public function expiresAt(): ?int
    {
        return $this->expiresAt;
    }
}
