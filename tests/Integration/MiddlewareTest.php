<?php

declare(strict_types=1);

namespace Inertia\Tests\Integration;

use Inertia\Configs\InertiaConfig;
use Inertia\Configs\ValidationConfig;
use Inertia\Middleware\Middleware;
use Inertia\Props\AlwaysProp;
use Inertia\Support\Header;
use Inertia\Tests\Fixtures\TestController;
use Inertia\Tests\TestCase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tempest\Http\ContentType;
use Tempest\Http\Session\FormSession;
use Tempest\Http\Status;
use Tempest\Router\Exceptions\ControllerActionHadNoReturn;
use Tempest\Validation\FailingRule;
use Tempest\Validation\Rules\HasLength;
use Tempest\Validation\Rules\IsEmail;
use Tempest\Validation\Rules\IsNotEmptyString;

use function Tempest\root_path;
use function Tempest\Router\uri;

final class MiddlewareTest extends TestCase
{
    #[Test]
    public function no_response_value_by_default_means_automatically_redirecting_back_for_inertia_requests(): void
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

    #[Test]
    public function no_response_value_can_be_customized_by_overriding_the_middleware_method(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'customEmptyResponseAction']),
            headers: [
                Header::INERTIA => 'true',
                'Content-Type' => 'application/json',
            ],
        );

        $this->assertInstanceOf(LogicException::class, $response->throwable);
        $this->assertSame('An empty Inertia response was returned.', $response->throwable->getMessage());
    }

    #[Test]
    public function no_response_means_no_response_for_non_inertia_requests(): void
    {
        $response = $this->http->put(
            uri: uri([TestController::class, 'voidPutAction']),
            headers: [
                'Content-Type' => 'application/json',
            ],
        );

        $this->assertInstanceOf(ControllerActionHadNoReturn::class, $response->throwable);
    }

    #[Test]
    public function the_version_is_optional(): void
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

    #[Test]
    public function the_version_can_be_a_number(): void
    {
        $version = 1_597_347_897_973;

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

    #[Test]
    public function the_version_can_be_a_string(): void
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

    #[Test]
    public function it_will_instruct_inertia_to_reload_on_a_version_mismatch(): void
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

    #[Test]
    public function the_url_can_be_resolved_with_a_custom_resolver(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithRootViewMethodMiddleware']),
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

    #[Test]
    public function validation_errors_are_registered_as_of_default(): void
    {
        $middleware = $this->container->get(Middleware::class);

        $sharedData = $middleware->share();

        $this->assertArrayHasKey('errors', $sharedData);
        $this->assertInstanceOf(AlwaysProp::class, $sharedData['errors']);
    }

    #[Test]
    public function validation_errors_can_be_empty(): void
    {
        $middleware = $this->container->get(Middleware::class);

        $sharedData = $middleware->share();
        $errors = $sharedData['errors']();

        $this->assertEmpty($errors);
    }

    #[Test]
    public function validation_errors_returns_single_error(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: false,
                localize_fields: false,
            )),
        );

        $formSession = $this->container->get(FormSession::class);

        $formSession->setErrors([
            'email' => [new FailingRule(new IsEmail()), new FailingRule(new HasLength(min: 50))],
        ]);

        $middleware = $this->container->get(Middleware::class);

        $sharedData = $middleware->share();
        $errors = $sharedData['errors']();

        $this->assertSame('Email must be a valid email address', $errors['email']);
    }

    #[Test]
    public function validation_errors_returns_multiple_errors(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: true,
                localize_fields: false,
            )),
        );

        $formSession = $this->container->get(FormSession::class);

        $formSession->setErrors([
            'email' => [new FailingRule(new IsEmail()), new FailingRule(new HasLength(min: 50))],
            'name' => [new FailingRule(new HasLength(min: 50))],
        ]);

        $middleware = $this->container->get(Middleware::class);

        $sharedData = $middleware->share();
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

    #[Test]
    public function validation_errors_are_localized_when_config_is_true(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: false,
                localize_fields: true,
            )),
        );

        $formSession = $this->container->get(FormSession::class);

        $formSession->setErrors([
            'email' => [new FailingRule(new IsNotEmptyString())],
        ]);

        $middleware = $this->container->get(Middleware::class);
        $sharedData = $middleware->share();
        $errors = $sharedData['errors']();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('email must not be empty', $errors['email']);
    }

    #[Test]
    public function validation_errors_are_generic_when_config_is_false(): void
    {
        $this->container->singleton(
            InertiaConfig::class,
            static fn () => new InertiaConfig(validation: new ValidationConfig(
                multiple_errors: false,
                localize_fields: false,
            )),
        );

        $formSession = $this->container->get(FormSession::class);

        $formSession->setErrors([
            'email' => [new FailingRule(new IsNotEmptyString())],
        ]);

        $middleware = $this->container->get(Middleware::class);

        $sharedData = $middleware->share();
        $errors = $sharedData['errors']();

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('Value must not be empty', $errors['email']);
    }

    #[Test]
    public function default_validation_errors_can_be_overwritten(): void
    {
        $formSession = $this->container->get(FormSession::class);
        $formSession->setErrors(['name' => [new FailingRule(new IsNotEmptyString())]]);

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

    #[Test]
    public function middleware_can_change_the_root_view_via_a_property(): void
    {
        $response = $this->http->get(uri: uri([TestController::class, 'basicRenderWithRootViewPropertyMiddleware']));

        $response->assertStatus(Status::OK);
        $response->assertView('welcome');
    }

    #[Test]
    public function middleware_can_change_the_root_view_by_overriding_the_rootview_method(): void
    {
        $response = $this->http->get(uri: uri([TestController::class, 'basicRenderWithRootViewMethodMiddleware']));

        $response->assertStatus(Status::OK);
        $response->assertView('welcome');
    }

    #[Test]
    public function determine_the_version_by_a_hash_of_the_vite_manifest(): void
    {
        $manifestPath = root_path('public/build/manifest.json');
        $directoryPath = dirname($manifestPath);
        $contents = json_encode(['vite' => true]);

        if (! is_dir($directoryPath)) {
            mkdir($directoryPath, 0o777, true);
        }

        file_put_contents($manifestPath, $contents);

        try {
            $response = $this->http->get(uri: uri([TestController::class, 'basicRenderWithMiddleware']));

            $page = $response->body->inertia['page'];

            $response->assertStatus(Status::OK);
            $this->assertSame(hash_file('xxh128', $manifestPath), $page['version']);
        } finally {
            unlink($manifestPath);
            rmdir($directoryPath);
        }
    }

    #[Test]
    public function middleware_share_once(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithRootViewMethodMiddleware']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertSame(['admin' => true], $page['props']['permissions']);
        $this->assertSame(['theme' => 'dark'], $page['props']['settings']);
        $this->assertSame(['prop' => 'permissions', 'expiresAt' => null], $page['onceProps']['permissions']);
        $this->assertSame('settings', $page['onceProps']['app-settings']['prop']);
        $this->assertNotNull($page['onceProps']['app-settings']['expiresAt']);
    }

    #[Test]
    public function middleware_share_and_share_once_are_merged(): void
    {
        $response = $this->http->get(
            uri: uri([TestController::class, 'basicRenderWithRootViewMethodMiddleware']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $response->body;

        $this->assertSame('Jonathan', $page['props']['user']['name']);
        $this->assertSame(['admin' => true], $page['props']['permissions']);
        $this->assertSame(['theme' => 'dark'], $page['props']['settings']);
        $this->assertSame(['prop' => 'permissions', 'expiresAt' => null], $page['onceProps']['permissions']);
        $this->assertSame('settings', $page['onceProps']['app-settings']['prop']);
        $this->assertNotNull($page['onceProps']['app-settings']['expiresAt']);
    }

    #[Test]
    public function flash_data_is_preserved_on_non_inertia_redirect(): void
    {
        $firstResponse = $this->http->get(uri: uri([TestController::class, 'actionWithRedirect']));

        $firstResponse->assertRedirect('/dashboard');

        $secondResponse = $this->http->get(
            uri: uri([TestController::class, 'dashboard']),
            headers: [Header::INERTIA => 'true'],
        );

        $page = $secondResponse->body;

        $this->assertSame('Success!', $page['flash']['message']);
    }
}
