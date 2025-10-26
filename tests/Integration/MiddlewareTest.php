<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\InertiaConfig;
use Inertia\Configs\ValidationConfig;
use Inertia\Middleware\Middleware;
use Inertia\Props\AlwaysProp;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\ExampleMiddleware;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use Inertia\Views\InertiaView;
use LogicException;
use Tempest\Http\ContentType;
use Tempest\Http\Request;
use Tempest\Http\Session\Session;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ControllerActionHadNoReturn;
use Tempest\Validation\Rules;

use function Tempest\root_path;
use function Tempest\Router\uri;

class MiddlewareTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        ExampleMiddleware::$runCount = 0;
        TestController::$voidActionCalled = false;
    }

    public function test_no_response_value_by_default_means_automatically_redirecting_back_for_inertia_requests(): void
    {
        $response = $this->http->put(
            uri: uri([TestController::class, 'voidPutAction']),
            headers: [
                Header::INERTIA => 'true',
                'Content-Type' => 'application/json',
                'Referer' => '/foo',
            ],
        );

        $response->assertStatus(Status::SEE_OTHER);
        $this->assertSame('/foo', $response->headers['Location']->values[0]);
        $this->assertTrue(TestController::$voidActionCalled, 'The controller action was not called.');
    }

    public function test_no_response_value_can_be_customized_by_overriding_the_middleware_method(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An empty Inertia response was returned.');

        $this->http->get(
            uri: uri([TestController::class, 'customEmptyResponseAction']),
            headers: [
                Header::INERTIA => 'true',
                'Content-Type' => 'application/json',
            ],
        );
    }

    public function test_no_response_means_no_response_for_non_inertia_requests(): void
    {
        $this->expectException(ControllerActionHadNoReturn::class);

        $this->http->put(
            uri: uri([TestController::class, 'voidPutAction']),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );
    }

    public function test_the_version_is_optional(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRender']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $response->assertStatus(Status::OK);
        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
    }

    public function test_the_version_can_be_a_number(): void
    {
        $version = 1597347897973;

        $response = $this->http->get(
            uri: uri([TestController::class, 'numericVersion']),
            headers: [
                Header::INERTIA => 'true',
                Header::VERSION => (string) $version,
            ],
        );

        $page = $response->body;

        $response->assertStatus(Status::OK);
        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
    }

    public function test_the_version_can_be_a_string(): void
    {
        $version = 'foo-version';

        $response = $this->http->get(
            uri: uri([TestController::class, 'stringVersion']),
            headers: [
                Header::INERTIA => 'true',
                Header::VERSION => $version,
            ],
        );

        $page = $response->body;

        $response->assertStatus(Status::OK);
        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
    }

    public function test_it_will_instruct_inertia_to_reload_on_a_version_mismatch(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'stringVersion']),
            headers: [
                Header::INERTIA => 'true',
                Header::VERSION => 'a-different-version',
            ],
        );

        $response->assertStatus(Status::CONFLICT);
        $this->assertSame('/string-version-test', $response->headers[Header::LOCATION]->values[0]);
        $this->assertEmpty($response->body);
    }

    public function test_the_url_can_be_resolved_with_a_custom_resolver(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithExampleMiddleware']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $response->assertStatus(Status::OK);
        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertSame('/my-custom-url', $page['url']);
    }

    public function test_validation_errors_are_registered_as_of_default(): void
    {
        $middleware = $this->container->get(Middleware::class);
        $request = $this->container->get(Request::class);
        $this->assertInstanceOf(Middleware::class, $middleware);

        $sharedData = $middleware->share($request);

        $this->assertArrayHasKey('errors', $sharedData);
        $this->assertInstanceOf(AlwaysProp::class, $sharedData['errors']);
    }

    public function test_validation_errors_can_be_empty(): void
    {
        $middleware = $this->container->get(Middleware::class);
        $request = $this->container->get(Request::class);
        $this->assertInstanceOf(Middleware::class, $middleware);

        $sharedData = $middleware->share($request);
        $errors = $sharedData['errors']();

        $this->assertEmpty($errors);
    }

    public function test_validation_errors_returns_single_error(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            fn() => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: false,
                localize_fields: false,
            )),
        );

        $session = $this->container->get(Session::class);

        $validationErrors = [
            'email' => [new rules\IsEmail(), new Rules\HasLength(min: 50)],
        ];
        $this->assertInstanceOf(Session::class, $session);
        $session->set(Session::VALIDATION_ERRORS, $validationErrors);

        $middleware = $this->container->get(Middleware::class);
        $request = $this->container->get(Request::class);
        $this->assertInstanceOf(Middleware::class, $middleware);

        $sharedData = $middleware->share($request);
        $errors = $sharedData['errors']();

        $this->assertSame('Email must be a valid email address', $errors['email']);
    }

    public function test_validation_errors_returns_multiple_errors(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            fn() => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: true,
                localize_fields: false,
            )),
        );

        $session = $this->container->get(Session::class);

        $validationErrors = [
            'email' => [new Rules\IsEmail(), new Rules\HasLength(min: 50)],
            'name' => [new Rules\HasLength(min: 50)],
        ];
        $this->assertInstanceOf(Session::class, $session);
        $session->set(Session::VALIDATION_ERRORS, $validationErrors);

        $middleware = $this->container->get(Middleware::class);
        $request = $this->container->get(Request::class);
        $this->assertInstanceOf(Middleware::class, $middleware);

        $sharedData = $middleware->share($request);
        $errors = $sharedData['errors']();

        $this->assertSame(
            [
                'Email must be a valid email address',
                'Value must be at least 50',
            ],
            $errors['email'],
        );

        $this->assertSame(
            [
                'Value must be at least 50',
            ],
            $errors['name'],
        );
    }

    public function test_validation_errors_are_localized_when_config_is_true(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            fn() => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: false,
                localize_fields: true,
            )),
        );

        $session = $this->container->get(Session::class);
        $validationErrors = [
            'email' => [new Rules\IsNotEmptyString()],
        ];
        $this->assertInstanceOf(\Tempest\Http\Session\Session::class, $session);
        $session->set(Session::VALIDATION_ERRORS, $validationErrors);

        $middleware = $this->container->get(Middleware::class);
        $request = $this->container->get(Request::class);
        $this->assertInstanceOf(\Inertia\Middleware\Middleware::class, $middleware);
        $sharedData = $middleware->share($request);
        $errors = $sharedData['errors']();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('email must not be empty', $errors['email']);
    }

    public function test_validation_errors_are_generic_when_config_is_false(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            fn() => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: false,
                localize_fields: false,
            )),
        );

        $session = $this->container->get(Session::class);
        $validationErrors = [
            'email' => [new Rules\IsNotEmptyString()],
        ];
        $this->assertInstanceOf(\Tempest\Http\Session\Session::class, $session);
        $session->set(Session::VALIDATION_ERRORS, $validationErrors);

        $middleware = $this->container->get(Middleware::class);
        $request = $this->container->get(Request::class);
        $this->assertInstanceOf(\Inertia\Middleware\Middleware::class, $middleware);
        $sharedData = $middleware->share($request);
        $errors = $sharedData['errors']();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('Value must not be empty', $errors['email']);
    }

    public function test_default_validation_errors_can_be_overwritten(): void
    {
        $session = $this->container->get(Session::class);
        $this->assertInstanceOf(Session::class, $session);
        $session->set(Session::VALIDATION_ERRORS, ['name' => ['This should be overwritten.']]);

        $response = $this->http->get(
            uri: uri([TestController::class, 'overwriteErrorsProp']),
            headers: [
                Header::INERTIA => 'true',
            ],
        );

        $page = $response->body;

        $this->assertSame(ContentType::JSON->value, $response->headers['Content-Type']->values[0]);
        $this->assertSame('User/Edit', $page['component']);
        $this->assertArrayHasKey('errors', $page['props']);
        $this->assertSame('foo', $page['props']['errors']);
    }

    public function test_middleware_can_change_the_root_view_via_a_property(): void
    {
        $response = $this->http->get(uri: uri([TestController::class, 'basicRenderWithExampleMiddleware']));

        $response->assertStatus(Status::OK);
        $this->assertInstanceOf(InertiaView::class, $response->body);
        $this->assertSame('welcome', $response->body->path);
    }

    public function test_middleware_can_change_the_root_view_by_overriding_the_rootview_method(): void
    {
        $response = $this->http->get(uri: uri([TestController::class, 'basicRenderWithExampleMiddleware']));

        $response->assertStatus(Status::OK);
        $this->assertInstanceOf(InertiaView::class, $response->body);
        $this->assertSame('welcome', $response->body->path);
    }

    public function test_determine_the_version_by_a_hash_of_the_vite_manifest(): void
    {
        $manifestPath = root_path('public/build/manifest.json');
        $directoryPath = dirname($manifestPath);
        $contents = json_encode(['vite' => true]);

        if (!is_dir($directoryPath)) {
            mkdir($directoryPath, 0o777, true);
        }

        file_put_contents($manifestPath, $contents);

        try {
            $response = $this->http->get(uri: uri([TestController::class, 'basicRenderWithMiddleware']));

            $page = $response->body->inertia['page'];

            $response->assertStatus(Status::OK);
            $this->assertInstanceOf(InertiaView::class, $response->body);
            $this->assertSame(hash_file('xxh128', $manifestPath), $page['version']);
        } finally {
            unlink($manifestPath);
            rmdir($directoryPath);
        }
    }

    public function test_extended_middleware_only_runs_once(): void
    {
        $this->container->singleton(Middleware::class, fn() => $this->container->get(ExampleMiddleware::class));

        $this->http->get(uri: uri([TestController::class, 'basicRender']));

        $this->assertSame(1, ExampleMiddleware::$runCount);
    }
}
