<?php
namespace App\Controller;

use Psr\Log\LoggerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Aliases\Aliases;
use Yiisoft\View\ViewContextInterface;
use Yiisoft\View\WebView;
use Yiisoft\Mailer\MailerInterface;

class SiteController implements ViewContextInterface
{
    private $responseFactory;
    private $aliases;
    private $view;
    private $layout;
    /**
     * @var LoggerInterface $logger
     */
    private $logger;
    /**
     * @var MailerInterface $mailer
     */
    private $mailer;

    public function __construct(
        ResponseFactoryInterface $responseFactory, 
        Aliases $aliases, 
        WebView $view, 
        LoggerInterface $logger,
        MailerInterface $mailer
    )
    {
        $this->responseFactory = $responseFactory;
        $this->aliases = $aliases;
        $this->view = $view;
        $this->layout = $aliases->get('@views') . '/layout/main';
        $this->logger = $logger;
        $this->mailer = $mailer;
    }

    public function index(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        $output = $this->render('index');

        $response->getBody()->write($output);
        return $response;
    }

    public function contact(RequestInterface $request): ResponseInterface
    {
        $parameters = [];
        if ($request->getMethod() === 'POST') {
            $config = array_merge(
                require $this->aliases->get('@root/config/params.php'),
                require $this->aliases->get('@root/config/params-local.php'),
            );
            $sent = false;
            $error = '';
            try {
                foreach (['subject', 'name', 'email', 'content'] as $name) {
                    if (empty($_POST[$name])) {
                        throw new \InvalidArgumentException(ucfirst($name). ' is required');
                    }
                }
                $message = $this->mailer->compose('contact', [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'],
                        'content' => $_POST['content']
                    ])
                    ->setSubject($_POST['subject'])
                    ->setTo($config['supportEmail'])
                    ->setFrom($config['mailer.username']);

                if (!empty($_FILES['file']['tmp_name'])) {
                    $file = $_FILES['file'];
                    $message->attach($file['tmp_name'], ['fileName' => $file['name'], 'contentType' => $file['type']]);
                }

                $message->send();
                $sent = true;
            } catch (\Throwable $e) {
                $this->logger->error($e);
                $error = $e->getMessage();
            }
            $parameters['sent'] = $sent;
            $parameters['error'] = $error;
        }

        $response = $this->responseFactory->createResponse();

        // $this->layout = null;
        $output = $this->render('contact', $parameters);

        $response->getBody()->write($output);
        return $response;
    }

    public function testParameter(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        $response = $this->responseFactory->createResponse();
        $response->getBody()->write('You are at test with param ' . $id);
        return $response;
    }

    private function render(string $view, array $parameters = []): string
    {
        $content = $this->view->render($view, $parameters, $this);
        return $this->renderContent($content);
    }

    private function renderContent($content): string
    {
        $layout = $this->findLayoutFile($this->layout);
        if ($layout !== null) {
            return $this->view->renderFile($layout, ['content' => $content], $this);
        }

        return $content;
    }

    /**
     * @return string the view path that may be prefixed to a relative view name.
     */
    public function getViewPath(): string
    {
        return $this->aliases->get('@views') . '/site';
    }

    private function findLayoutFile(?string $file): ?string
    {
        if ($file === null) {
            return null;
        }

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }

        return $file . '.' . $this->view->defaultExtension;
    }
}
