<?php

declare(strict_types=1);

namespace Inertia\Views;

use Inertia\Configs\InertiaConfig;
use JsonException;
use Tempest\Support\Html\HtmlString;
use Tempest\View\IsView;
use Tempest\View\View;

use function Tempest\Container\get;

final class InertiaView implements View
{
    use IsView;

    public function __construct(
        public string $path,
        public array $inertia,
        public ?string $ssrHead = null,
        public ?string $ssrBody = null,
    ) {}

    private InertiaConfig $config {
        get => $this->config ??= get(InertiaConfig::class);
    }

    /**
     * Renders the Inertia root element.
     *
     * @throws JsonException if the page data cannot be encoded to JSON.
     */
    public function inertia(string $id = 'app'): HtmlString
    {
        $id = trim($id) === '' ? 'app' : $id;

        $json = json_encode($this->inertia['page'], JSON_THROW_ON_ERROR);
        $escaped = htmlspecialchars($json, ENT_QUOTES, 'UTF-8');

        if ($this->ssrBody) {
            $body = $this->ssrBody;
        } elseif ($this->config->pages->use_data_attribute_for_initial_page) {
            $body = "<div id=\"{$id}\" data-page=\"{$escaped}\"></div>";
        } else {
            $body =
                "<script data-page=\"{$id}\" type=\"application/json\">{$json}</script>" . "<div id=\"{$id}\"></div>";
        }

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
