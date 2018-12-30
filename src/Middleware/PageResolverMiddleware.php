<?php
/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Herbie\Middleware;

use Herbie\Application;
use Herbie\Environment;
use Herbie\Loader\PageLoader;
use Herbie\Page;
use Herbie\Url\UrlMatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PageResolverMiddleware implements MiddlewareInterface
{
    protected $environment;
    protected $urlMatcher;
    protected $pageLoader;

    public function __construct(Environment $environment, UrlMatcher $urlMatcher, PageLoader $pageLoader)
    {
        $this->environment = $environment;
        $this->urlMatcher = $urlMatcher;
        $this->pageLoader = $pageLoader;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface
    {
        try {
            $route = $this->environment->getRoute();
            $menuItem = $this->urlMatcher->match($route);
            $path = $menuItem->getPath();

            $page = new Page();
            $page->setLoader($this->pageLoader);
            $page->load($path);

        } catch (\Throwable $t) {
            $page = new Page();
            $page->layout = 'error';
            $page->setError($t);
        }
        Application::setPage($page);
        $request = $request->withAttribute(Page::class, $page);
        return $handler->handle($request);
    }
}