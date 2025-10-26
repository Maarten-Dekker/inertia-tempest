<?php

declare(strict_types=1);

namespace Inertia\Support;

use Inertia\Configs\InertiaConfig;
use Tempest\Http\Session\Session;
use Tempest\Validation\Rule;
use Tempest\Validation\Validator;

use function Tempest\Support\arr;

final readonly class ResolveErrorProps
{
    public function __construct(
        private Session $session,
        private Validator $validator,
        private InertiaConfig $config,
    ) {}

    public function __invoke(): array
    {
        /** @var array<string, Rule[]> $validationErrors */
        $validationErrors = $this->session->get(Session::VALIDATION_ERRORS) ?? [];

        return arr($validationErrors)->map(function (array $rules, string $key): string|array|null {
            $fieldName = $this->config->validation->localize_fields ? $key : null;

            $messages = arr($rules)->map(
                fn(Rule $rule) => $this->validator->getErrorMessage($rule, $fieldName),
            )->toArray();

            if ($this->config->validation->multiple_errors) {
                return $messages;
            }

            return array_shift($messages);
        })->toArray();
    }
}
