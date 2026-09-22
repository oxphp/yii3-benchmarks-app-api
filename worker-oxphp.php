<?php

declare(strict_types=1);

use App\Environment;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use Yiisoft\Di\StateResetter;
use Yiisoft\ErrorHandler\ErrorHandler;
use Yiisoft\ErrorHandler\Middleware\ErrorCatcher;
use Yiisoft\ErrorHandler\Renderer\PlainTextRenderer;
use Yiisoft\Log\Logger;
use Yiisoft\Log\StreamTarget;
use Yiisoft\PsrEmitter\EmitterInterface;
use Yiisoft\PsrEmitter\SapiEmitter;
use Yiisoft\Yii\Http\Application;
use Yiisoft\Yii\Http\Handler\ThrowableHandler;
use Yiisoft\Yii\Runner\ApplicationRunner;
use Yiisoft\Yii\Runner\Http\RequestFactory;

require_once __DIR__ . '/src/bootstrap.php';

// Match the FrankenPHP runner: a client hanging up must not end the request mid-flight.
ignore_user_abort(true);

/**
 * Runs the Yii HTTP application in OxPHP worker mode.
 *
 * There is no Yii runner package for OxPHP yet, so this is the per-request loop of
 * yiisoft/yii-runner-frankenphp (BSD-3-Clause) with only the server call swapped:
 * the same container bootstrap, the same `start()` / `afterEmit()` / `shutdown()`
 * events, the same `StateResetter::reset()` and `gc_collect_cycles()` after every
 * request, and the same `ErrorCatcher` fallback. Requests are built by the
 * `RequestFactory` the classic runners use.
 */
final class OxPHPApplicationRunner extends ApplicationRunner
{
    private readonly EmitterInterface $emitter;

    public function __construct(
        string $rootPath,
        bool $debug,
        bool $checkEvents,
        ?string $environment,
        private readonly ErrorHandler $temporaryErrorHandler,
    ) {
        $this->emitter = new SapiEmitter();

        parent::__construct(
            $rootPath,
            $debug,
            $checkEvents,
            $environment,
            'bootstrap-web',
            'events-web',
            'di-web',
            'di-providers-web',
            'di-delegates-web',
            'di-tags-web',
            'params-web',
            ['params'],
            ['events'],
        );
    }

    public function run(): void
    {
        $this->registerErrorHandler($this->temporaryErrorHandler);

        $container = $this->getContainer();

        /** @var ErrorHandler $actualErrorHandler */
        $actualErrorHandler = $container->get(ErrorHandler::class);
        $this->registerErrorHandler($actualErrorHandler, $this->temporaryErrorHandler);

        $this->runBootstrap();
        $this->checkEvents();

        /** @var Application $application */
        $application = $container->get(Application::class);
        $application->start();

        /** @var RequestFactory $requestFactory */
        $requestFactory = $container->get(RequestFactory::class);

        oxphp_worker(fn () => $this->handle($container, $application, $requestFactory));

        $application->shutdown();
    }

    private function handle(
        ContainerInterface $container,
        Application $application,
        RequestFactory $requestFactory,
    ): void {
        $request = $requestFactory->create()->withAttribute('applicationStartTime', microtime(true));

        try {
            $response = $application->handle($request);
            $this->emitter->emit($response);
        } catch (Throwable $throwable) {
            /** @var ErrorCatcher $errorCatcher */
            $errorCatcher = $container->get(ErrorCatcher::class);
            $response = $errorCatcher->process($request, new ThrowableHandler($throwable));
            $this->emitter->emit($response);
        }

        $application->afterEmit($response);

        /** @var StateResetter $stateResetter */
        $stateResetter = $container->get(StateResetter::class);
        $stateResetter->reset();
        gc_collect_cycles();
    }

    private function registerErrorHandler(ErrorHandler $registered, ?ErrorHandler $unregistered = null): void
    {
        $unregistered?->unregister();

        if ($this->debug) {
            $registered->debug();
        }

        $registered->register();
    }
}

(new OxPHPApplicationRunner(
    rootPath: __DIR__,
    debug: Environment::appDebug(),
    checkEvents: Environment::appDebug(),
    environment: Environment::appEnv(),
    temporaryErrorHandler: new ErrorHandler(
        new Logger([(new StreamTarget())->setLevels([LogLevel::EMERGENCY, LogLevel::ERROR, LogLevel::WARNING])]),
        new PlainTextRenderer(),
    ),
))->run();
