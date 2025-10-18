<?php

declare(strict_types=1);

namespace Inertia\Support;

use Tempest\Http\Session\Session;
use Tempest\Validation\Rule;
use Tempest\Validation\Validator;

use function Tempest\Support\arr;

final readonly class ResolveErrorProps
{
    public function __construct(
        private Session $session,
        private Validator $validator,
    ) {}

    public function __invoke(): object
    {
        $validationErrors = $this->session->get(Session::VALIDATION_ERRORS) ?? [];

        $processedErrors = arr($validationErrors)
            ->map(function (array $rules): ?string {
                $firstRule = $rules[0] ?? null;

                if ($firstRule instanceof Rule) {
                    $message = $this->validator->getErrorMessage($firstRule);

                    if ($message !== '' && $message !== '0') {
                        return $message;
                    }
                }

                return null;
            })
            ->filter(fn(?string $message) => $message !== null)
            ->toArray();

        return (object) $processedErrors;
    }
}
