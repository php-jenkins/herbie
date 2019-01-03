<?php
/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Herbie\Middleware;

use Herbie\Application;
use Herbie\Environment;
use Herbie\Page;
use Herbie\Repository\PageRepositoryInterface;
use Herbie\Url\UrlMatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PageResolverMiddleware implements MiddlewareInterface
{
    protected $environment;
    protected $herbie;
    protected $urlMatcher;
    protected $pageRepository;

    /**
     * PageResolverMiddleware constructor.
     * @param Application $herbie
     * @param Environment $environment
     * @param PageRepositoryInterface $pageRepository
     * @param UrlMatcher $urlMatcher
     */
    // TODO dont inject Application
    public function __construct(
        Application $herbie,
        Environment $environment,
        PageRepositoryInterface $pageRepository,
        UrlMatcher $urlMatcher
    ) {
        $this->herbie = $herbie;
        $this->environment = $environment;
        $this->urlMatcher = $urlMatcher;
        $this->pageRepository = $pageRepository;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Herbie\Exception\HttpException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->environment->getRoute();
        $menuItem = $this->urlMatcher->match($route);
        $path = $menuItem->getPath();
        $page = $this->pageRepository->find($path);
        $this->herbie->setPage($page);
        $request = $request->withAttribute(Page::class, $page);
        return $handler->handle($request);
    }
}
