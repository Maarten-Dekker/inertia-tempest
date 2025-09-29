<?php

declare(strict_types=1);

use Inertia\Configs\InertiaConfig;
use Inertia\Exceptions\ComponentNotFoundException;
use Inertia\Props\AlwaysProp;
use Inertia\Props\DeferProp;
use Inertia\Props\LazyProp;
use Inertia\Props\MergeProp;
use Inertia\Props\OptionalProp;
use Inertia\ResponseFactory;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use Tempest\Http\ContentType;
use Tempest\Http\Response;
use Tempest\Http\Responses\Redirect;
use Tempest\Http\Status;

use function Tempest\uri;

class ResponseFactoryTest extends TestCase
{
    public function test_location_response_for_inertia_requests(): void
    {
        $this->makeRequest(
            uri: '/',
            headers: [Header::INERTIA => 'true'],
        );

        $response = $this->factory->location('https://inertiajs.com');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Status::CONFLICT, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader(Header::LOCATION)->values[0]);
    }

    public function test_location_response_for_non_inertia_requests(): void
    {
        $response = $this->factory->location('https://inertiajs.com');

        $this->assertInstanceOf(Redirect::class, $response);
        $this->assertSame(Status::FOUND, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader('Location')->values[0]);
    }

    public function test_location_response_for_inertia_requests_using_redirect_response(): void
    {
        $this->makeRequest(
            uri: '/',
            headers: [Header::INERTIA => 'true'],
        );

        $redirect = new Redirect('https://inertiajs.com');

        $response = $this->factory->location($redirect);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Status::CONFLICT, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader(Header::LOCATION)->values[0]);
    }

    public function test_location_response_for_non_inertia_requests_using_redirect_response(): void
    {
        $redirect = new Redirect('https://inertiajs.com');

        $response = $this->factory->location($redirect);

        $this->assertSame($redirect, $response);
    }

    public function test_location_redirects_are_not_modified(): void
    {
        $response = $this->factory->location('/foo');

        $this->assertInstanceOf(Redirect::class, $response);
        $this->assertSame(Status::FOUND, $response->status);
        $this->assertSame('/foo', $response->getHeader('Location')->values[0]);
    }

    public function test_location_response_for_non_inertia_requests_using_redirect_response_with_existing_session_and_request_properties(): void
    {
        $redirect = new Redirect('https://inertiajs.com');

        $redirect->addSession('test_key', 'test_value');

        $response = $this->factory->location($redirect);

        $this->assertInstanceOf(Redirect::class, $response);
        $this->assertSame(Status::FOUND, $response->status);
        $this->assertSame('https://inertiajs.com', $response->getHeader('Location')->values[0]);
        $this->assertSame('test_value', $response->session->get('test_key'));
        $this->assertSame($redirect, $response);
    }

    public function test_the_version_can_be_a_closure(): void
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

    public function test_the_url_can_be_resolved_with_a_custom_resolver(): void
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

    public function test_shared_data_can_be_shared_from_anywhere(): void
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

    public function test_dot_props_are_merged_from_shared(): void
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

    public function test_shared_data_can_resolve_closure_arguments(): void
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

    public function test_dot_props_with_callbacks_are_merged_from_shared(): void
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

    public function test_can_flush_shared_data(): void
    {
        inertia()->share('foo', 'bar');
        $this->assertSame(['foo' => 'bar'], inertia()->getShared());
        inertia()->flushShared();
        $this->assertSame([], inertia()->getShared());
    }

    public function test_can_create_lazy_prop(): void
    {
        $this->expectUserDeprecationMessage('Method '
        . ResponseFactory::class
        . '::lazy() is deprecated, Use `optional` instead.');

        $lazyProp = $this->factory->lazy(fn(): string => 'A lazy value');

        $this->assertInstanceOf(LazyProp::class, $lazyProp);
    }

    public function test_can_create_deferred_prop(): void
    {
        $deferredProp = $this->factory->defer(fn(): string => 'A deferred value');

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertSame('default', $deferredProp->group());
    }

    public function test_can_create_deferred_prop_with_custom_group(): void
    {
        $deferredProp = $this->factory->defer(fn(): string => 'A deferred value', 'foo');

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
        $this->assertSame('foo', $deferredProp->group());
    }

    public function test_can_create_merged_prop(): void
    {
        $mergedProp = $this->factory->merge(fn(): string => 'A merged value');

        $this->assertInstanceOf(MergeProp::class, $mergedProp);
    }

    public function test_can_create_deep_merged_prop(): void
    {
        $mergedProp = $this->factory->deepMerge(fn(): string => 'A merged value');

        $this->assertInstanceOf(MergeProp::class, $mergedProp);
    }

    public function test_can_create_deferred_and_merged_prop(): void
    {
        $deferredProp = $this->factory->defer(fn(): string => 'A deferred + merged value')->merge();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
    }

    public function test_can_create_deferred_and_deep_merged_prop(): void
    {
        $deferredProp = $this->factory->defer(fn(): string => 'A deferred + merged value')->deepMerge();

        $this->assertInstanceOf(DeferProp::class, $deferredProp);
    }

    public function test_can_create_optional_prop(): void
    {
        $optionalProp = $this->factory->optional(fn(): string => 'An optional value');

        $this->assertInstanceOf(OptionalProp::class, $optionalProp);
    }

    public function test_can_create_always_prop(): void
    {
        $alwaysProp = $this->factory->always(fn(): string => 'An always value');

        $this->assertInstanceOf(AlwaysProp::class, $alwaysProp);
    }

    public function test_will_accept_arrayable_props(): void
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

    public function test_will_accept_instances_of_provides_inertia_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'renderWithProvider']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $props);
        $this->assertArrayHasKey('foo', $props);
        $this->assertSame('bar', $props['foo']);
        $this->assertCount(2, $props);
    }

    public function test_will_accept_arrays_containing_provides_inertia_props_in_render(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'renderWithMixedProps']),
            headers: [Header::INERTIA => 'true'],
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
        $this->assertCount(4, $props);
    }

    public function test_can_share_instances_of_provides_inertia_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'shareWithProvider']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;
        $props = $page['props'];

        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $props);
        $this->assertArrayHasKey('shared', $props);
        $this->assertArrayHasKey('regular', $props);
        $this->assertSame('data', $props['shared']);
        $this->assertSame('prop', $props['regular']);
        $this->assertCount(3, $props);
    }

    public function test_can_share_arrays_containing_provides_inertia_props(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'shareWithMixedProps']),
            headers: [Header::INERTIA => 'true'],
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
        $this->assertCount(4, $props);
    }

    public function test_will_throw_exception_if_component_does_not_exist_when_ensuring_is_enabled(): void
    {
        $config = $this->container->get(InertiaConfig::class);

        $originalValue = $config->pages->ensure_pages_exists;
        $config->pages->ensure_pages_exists = true;

        try {
            $this->expectException(ComponentNotFoundException::class);
            $this->expectExceptionMessage('Inertia page component [foo] not found.');

            $this->factory->render('foo');
        } finally {
            $config->pages->ensure_pages_exists = $originalValue;
        }
    }

    public function test_will_not_throw_exception_if_component_does_not_exist_when_ensuring_is_disabled(): void
    {
        $originalEnv = getenv('INERTIA_ENSURE_PAGES_EXISTS');
        putenv('INERTIA_ENSURE_PAGES_EXISTS=false');

        try {
            $response = $this->container->get(ResponseFactory::class)->render('foo');

            $this->assertInstanceOf(Response::class, $response);
        } finally {
            if (!$originalEnv) {
                putenv('INERTIA_ENSURE_PAGES_EXISTS');
            } else {
                putenv("INERTIA_ENSURE_PAGES_EXISTS={$originalEnv}");
            }
        }
    }
}
