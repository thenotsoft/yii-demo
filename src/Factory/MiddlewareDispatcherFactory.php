<?php
namespace Yiisoft\Yii\Demo\Factory;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Yiisoft\Router\Middleware\Router;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Web\ErrorHandler\ErrorHandler;
use Yiisoft\Web\MiddlewareDispatcher;

class MiddlewareDispatcherFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $router = $container->get(UrlMatcherInterface::class);
        $responseFactory = $container->get(ResponseFactoryInterface::class);
        $logger = $container->get(LoggerInterface::class);

        return new MiddlewareDispatcher([
            new ErrorHandler($responseFactory, $logger),
            new Router($router),
        ], $container);
    }

    public static function __set_state(array $state): self
    {
        return new self();
    }
}
