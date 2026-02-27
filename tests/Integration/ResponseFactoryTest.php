<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\InertiaConfig;
use Inertia\Configs\PageConfig;
use Inertia\Exceptions\ComponentNotFoundException;
use Inertia\ResponseFactory;
use Inertia\Support\Header;
use Inertia\Support\SessionKey;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\ContentType;
use Tempest\Http\Response;
use Tempest\Http\Responses\Redirect;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;

use function Tempest\Router\uri;

final class ResponseFactoryTest extends TestCase
{
    #[Test]
    public function location_response_for_inertia_requests(): void
    {
        $this->makeRequest(
            uri: '/',
            headers: [Header::INERTIA => 'true'],
        );

        $response = $this->factory->location('https://inertiajs.com');

        $this->assertSame(Status::CONFLICT, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader(Header::LOCATION)->values[0]);
    }

    #[Test]
    public function location_response_for_non_inertia_requests(): void
    {
        $response = $this->factory->location('https://inertiajs.com');

        $this->assertSame(Status::FOUND, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader('Location')->values[0]);
    }

    #[Test]
    public function location_response_for_inertia_requests_using_redirect_response(): void
    {
        $this->makeRequest(
            uri: '/',
            headers: [Header::INERTIA => 'true'],
        );

        $redirect = new Redirect('https://inertiajs.com');

        $response = $this->factory->location($redirect);

        $this->assertSame(Status::CONFLICT, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader(Header::LOCATION)->values[0]);
    }

    #[Test]
    public function location_response_for_non_inertia_requests_using_redirect_response(): void
    {
        $redirect = new Redirect('https://inertiajs.com');

        $response = $this->factory->location($redirect);

        $this->assertSame($redirect, $response);
    }

    #[Test]
    public function location_redirects_are_not_modified(): void
    {
        $response = $this->factory->location('/foo');

        $this->assertSame(Status::FOUND, $response->status);
        $this->assertSame('/foo', $response->getHeader('Location')->values[0]);
    }

    #[Test]
    public function location_response_for_non_inertia_requests_using_redirect_response_with_existing_session_and_request_properties(): void
    {
        $redirect = new Redirect('https://inertiajs.com');
        $redirect->addSession('key', 'value');

        $response = $this->factory->location($redirect);

        $this->assertSame(Status::FOUND, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader('Location')->values[0]);
        $this->assertSame('value', $response->session->get('key'));
        $this->assertSame($redirect, $response);
    }

    #[Test]
    public function the_version_can_be_a_closure(): void
    {
        $expectedVersion = 'test-version-from-closure';

        $response = $this->http->get(
            uri: uri([TestController::class, 'versionCanBeAClosure']),
            headers: [
                Header::INERTIA => 'true',
                Header::VERSION => $expectedVersion,
            ],
        );

        $page = $response->body;

        $response->assertStatus(Status::OK);
        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $response->assertHasHeader(Header::INERTIA);

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('test-version-from-closure', $page['version']);
        $this->assertSame('bar', $page['props']['foo']);
    }

    #[Test]
    public function the_url_can_be_resolved_with_a_custom_resolver(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'customUrlResolver']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('/my-custom-url', $page['url']);
    }

    #[Test]
    public function shared_data_can_be_shared_from_anywhere(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'sharedData']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('foo', $page['props']);
        $this->assertSame('bar', $page['props']['foo']);
    }

    #[Test]
    public function dot_props_are_merged_from_shared(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'dotPropMerging']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $props['auth']['user']['name']);
        $this->assertFalse($props['auth']['user']['can']['create_group']);
    }

    #[Test]
    public function shared_data_can_resolve_closure_arguments(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'sharedClosure']) . '?foo=bar',
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('query', $page['props']);
        $this->assertSame(['foo' => 'bar'], $page['props']['query']);
    }

    #[Test]
    public function dot_props_with_callbacks_are_merged_from_shared(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'dotPropsWithCallbacksMerging']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $props['auth']['user']['name']);
        $this->assertFalse($props['auth']['user']['can']['create_group']);
    }

    #[Test]
    public function can_flush_shared_data(): void
    {
        inertia()->share('foo', 'bar');
        $this->assertSame(['foo' => 'bar'], inertia()->getShared());
        inertia()->flushShared();
        $this->assertSame([], inertia()->getShared());
    }

    #[Test]
    public function can_create_deferred_prop(): void
    {
        $deferredProp = $this->factory->defer(static fn (): string => 'A deferred value');

        $this->assertSame('default', $deferredProp->group());
    }

    #[Test]
    public function can_create_deferred_prop_with_custom_group(): void
    {
        $deferredProp = $this->factory->defer(static fn (): string => 'A deferred value', 'foo');

        $this->assertSame('foo', $deferredProp->group());
    }

    #[Test]
    public function response_factory_once_creates_working_once_prop(): void
    {
        $once = $this->factory->once(static fn () => ['theme' => 'dark']);

        $this->assertSame(['theme' => 'dark'], $once());
    }

    #[Test]
    public function will_accept_arrayable_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'arrayableProps']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('foo', $page['props']);
        $this->assertSame('bar', $page['props']['foo']);
    }

    #[Test]
    public function will_accept_instances_of_provides_inertia_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'renderWithProvider']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $props);
        $this->assertArrayHasKey('foo', $props);
        $this->assertSame('bar', $props['foo']);
    }

    #[Test]
    public function will_accept_arrays_containing_provides_inertia_props_in_render(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'renderWithMixedProps']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $props);
        $this->assertArrayHasKey('regular', $props);
        $this->assertArrayHasKey('from_object', $props);
        $this->assertArrayHasKey('another', $props);
        $this->assertSame('prop', $props['regular']);
        $this->assertSame('value', $props['from_object']);
        $this->assertSame('normal_prop', $props['another']);
    }

    #[Test]
    public function can_share_instances_of_provides_inertia_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'shareWithProvider']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $props);
        $this->assertArrayHasKey('shared', $props);
        $this->assertArrayHasKey('regular', $props);
        $this->assertSame('data', $props['shared']);
        $this->assertSame('prop', $props['regular']);
    }

    #[Test]
    public function can_share_arrays_containing_provides_inertia_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'shareWithMixedProps']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $props);
        $this->assertArrayHasKey('regular', $props);
        $this->assertArrayHasKey('from_object', $props);
        $this->assertArrayHasKey('component', $props);
        $this->assertSame('shared_prop', $props['regular']);
        $this->assertSame('shared_value', $props['from_object']);
        $this->assertSame('prop', $props['component']);
    }

    #[Test]
    public function will_throw_exception_if_component_does_not_exist_when_ensuring_is_enabled(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(pages: new PageConfig(ensure_pages_exists: true)),
        );

        $this->expectException(ComponentNotFoundException::class);
        $this->expectExceptionMessage('Inertia page component [foo] not found.');

        $this->factory->render('foo');
    }

    #[Test]
    public function will_not_throw_exception_if_component_does_not_exist_when_ensuring_is_disabled(): void
    {
        $originalEnv = getenv('INERTIA_ENSURE_PAGES_EXISTS');
        putenv('INERTIA_ENSURE_PAGES_EXISTS=false');

        try {
            $response = $this->container->get(ResponseFactory::class)->render('foo');

            $this->assertInstanceOf(Response::class, $response);
        } finally {
            if (! $originalEnv) {
                putenv('INERTIA_ENSURE_PAGES_EXISTS');
            } else {
                putenv("INERTIA_ENSURE_PAGES_EXISTS={$originalEnv}");
            }
        }
    }

    #[Test]
    public function flash_data_is_flashed_to_session_on_redirect(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'flashTest']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $session = $this->container->get(Session::class);

        $response->assertRedirect();
        $this->assertSame(['message' => 'Success!'], $session->get(SessionKey::FlashData->value));
    }

    #[Test]
    public function render_with_flash_includes_flash_in_page(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'flashRenderTest']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;
        $session = $this->container->get(Session::class);
        $session->cleanup();

        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('Jonathan', $page['props']['user']);
        $this->assertSame('User updated!', $page['flash']['message']);
        $this->assertSame('success', $page['flash']['type']);
        $this->assertArrayNotHasKey(SessionKey::FlashData->value, $session->all());
    }

    #[Test]
    public function render_without_flash_does_not_include_flash_key(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'noFlashTest']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertArrayNotHasKey('flash', $page);
        $this->assertSame('User/Edit', $page['component']);
    }

    #[Test]
    public function multiple_flash_calls_are_merged(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'multipleFlashTest']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertArrayHasKey('flash', $page);
        $this->assertSame('value1', $page['flash']['foo']);
        $this->assertSame('value2', $page['flash']['bar']);
    }
}
