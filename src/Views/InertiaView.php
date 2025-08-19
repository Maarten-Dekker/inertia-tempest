<?php

declare(strict_types=1);

namespace Inertia\Views;

use JsonException;
use Tempest\Support\Html\HtmlString;
use Tempest\View\IsView;
use Tempest\View\View;

final class InertiaView implements View
{
    use IsView;

    public function __construct(
        public string $path,
        public array $inertia,
        public ?string $ssrHead = null,
        public ?string $ssrBody = null,
    ) {}

    /**
     * Renders the Inertia root element.
     *
     * @throws JsonException if the page data cannot be encoded to JSON.
     */
    public function inertia(string $id = 'app'): HtmlString
    {
        $page = htmlspecialchars(json_encode($this->inertia['page'], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');

        $body = $this->ssrBody ?? "<div id=\"{$id}\" data-page=\"{$page}\"></div>";

        return new HtmlString($body);
    }

    /**
     * Renders the content for SSR responses.
     */
    public function inertiaHead(): HtmlString
    {
        return new HtmlString($this->ssrHead ?? '');
    }
}
