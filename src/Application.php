<?php
/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <https://www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Herbie;

use Ausi\SlugGenerator\SlugGenerator;
use Ausi\SlugGenerator\SlugOptions;
use Herbie\Middleware\DispatchMiddleware;
use Herbie\Middleware\ErrorHandlerMiddleware;
use Herbie\Middleware\MiddlewareDispatcher;
use Herbie\Middleware\PageResolverMiddleware;
use Psr\Http\Message\ResponseInterface;
use Tebe\HttpFactory\HttpFactory;

defined('HERBIE_DEBUG') or define('HERBIE_DEBUG', false);

class Application
{
    /**
     * @var DI
     */
    protected static $DI;

    /**
     * @var Page
     */
    protected static $page;

    /**
     * @var string
     */
    protected $sitePath;

    /**
     * @var string
     */
    protected $vendorDir;

    /**
     * @var array
     */
    protected $middlewares;

    /**
     * @param string $sitePath
     * @param string $vendorDir
     */
    public function __construct($sitePath, $vendorDir = '../vendor')
    {
        $this->sitePath = realpath($sitePath);
        $this->vendorDir = realpath($vendorDir);
        $this->middlewares = [];
        $this->init();
    }

    public function setMiddleware(array $middlewares)
    {
        $this->middlewares = $middlewares;
    }

    protected function getMiddleware(): array
    {
        $middlewares = array_merge(
            [
                ErrorHandlerMiddleware::class
            ],
            $this->middlewares,
            [
                new PageResolverMiddleware(
                    static::getService('Environment'),
                    static::getService('Url\UrlMatcher'),
                    static::getService('Loader\PageLoader')
                ),
                DispatchMiddleware::class
            ]
        );
        return $middlewares;
    }

    /**
     * Initialize the application.
     */
    private function init()
    {
        $errorHandler = new ErrorHandler();
        $errorHandler->register($this->sitePath . '/log');

        static::$DI = $DI = DI::instance();

        $DI['HttpFactory'] = $httpFactory = new HttpFactory();

        $DI['Request'] = $request = $httpFactory->createServerRequestFromGlobals();

        $DI['Environment'] = $environment = new Environment($request);

        $DI['Config'] = $config = new Config(
            $this->sitePath,
            dirname($_SERVER['SCRIPT_FILENAME']),
            $environment->getBaseUrl()
        );

        $DI['Alias'] = new Alias([
            '@app' => $config->get('app.path'),
            '@asset' => $this->sitePath . '/assets',
            '@media' => $config->get('media.path'),
            '@page' => $config->get('pages.path'),
            '@plugin' => $config->get('plugins.path'),
            '@post' => $config->get('posts.path'),
            '@site' => $this->sitePath,
            '@vendor' => $this->vendorDir,
            '@web' => $config->get('web.path')
        ]);

        $DI['SlugGenerator'] = function ($DI) {
            $locale = $DI['Config']->get('language');
            $options = new SlugOptions([
                'locale' => $locale,
                'delimiter' => '-'
            ]);
            return new SlugGenerator($options);
        };

        $DI['Assets'] = function ($DI) {
            return new Assets($DI['Alias'], $DI['Config']->get('web.url'));
        };

        $DI['Cache\PageCache'] = function ($DI) {
            return Cache\CacheFactory::create('page', $DI['Config']);
        };

        $DI['Cache\DataCache'] = function ($DI) {
            return Cache\CacheFactory::create('data', $DI['Config']);
        };

        $DI['DataArray'] = function ($DI) {
            $loader = new Loader\DataLoader($DI['Config']->get('data.extensions'));
            return $loader->load($DI['Config']->get('data.path'));
        };

        $DI['Loader\PageLoader'] = function ($DI) {
            $loader = new Loader\PageLoader($DI['Alias']);
            return $loader;
        };

        $DI['Menu\Page\Builder'] = function ($DI) {

            $paths = [];
            $paths['@page'] = realpath($DI['Config']->get('pages.path'));
            foreach ($DI['Config']->get('pages.extra_paths', []) as $alias) {
                $paths[$alias] = $DI['Alias']->get($alias);
            }
            $extensions = $DI['Config']->get('pages.extensions', []);

            $builder = new Menu\Page\Builder($paths, $extensions);
            return $builder;
        };

        $DI['PluginManager'] = function ($DI) {
            $enabled = $DI['Config']->get('plugins.enable', []);
            $path = $DI['Config']->get('plugins.path');
            $enabledSysPlugins = $DI['Config']->get('sysplugins.enable');
            return new PluginManager($enabled, $path, $enabledSysPlugins);
        };

        $DI['Url\UrlGenerator'] = function ($DI) {
            return new Url\UrlGenerator(
                $DI['Request'],
                $DI['Environment'],
                $DI['Config']->get('nice_urls', false)
            );
        };

        setlocale(LC_ALL, $DI['Config']->get('locale'));

        // Add custom PSR-4 plugin path to Composer autoloader
        $autoload = require($this->vendorDir . '/autoload.php');
        $autoload->addPsr4('herbie\\plugin\\', $DI['Config']->get('plugins.path'));

        // Init PluginManager at first
        if (true === $DI['PluginManager']->init($DI['Config'])) {
            Hook::trigger(Hook::ACTION, 'pluginsInitialized', $DI['PluginManager']);

            Hook::trigger(Hook::ACTION, 'shortcodeInitialized', $DI['Shortcode']);

            $DI['Menu\Page\Collection'] = function ($DI) {
                $DI['Menu\Page\Builder']->setCache($DI['Cache\DataCache']);
                return $DI['Menu\Page\Builder']->buildCollection();
            };

            $DI['Menu\Page\Node'] = function ($DI) {
                return Menu\Page\Node::buildTree($DI['Menu\Page\Collection']);
            };

            $DI['Menu\Page\RootPath'] = function ($DI) {
                $rootPath = new Menu\Page\RootPath($DI['Menu\Page\Collection'], $DI['Environment']->getRoute());
                return $rootPath;
            };

            $DI['Menu\Post\Collection'] = function ($DI) {
                $builder = new Menu\Post\Builder($DI['Cache\DataCache'], $DI['Config']);
                return $builder->build();
            };

            $DI['Translator'] = function ($DI) {
                $translator = new Translator($DI['Config']->get('language'), [
                    'app' => $DI['Alias']->get('@app/../messages')
                ]);
                foreach ($DI['PluginManager']->getLoadedPlugins() as $key => $dir) {
                    $translator->addPath($key, $dir . '/messages');
                }
                $translator->init();
                return $translator;
            };

            $DI['Url\UrlMatcher'] = function ($DI) {
                return new Url\UrlMatcher($DI['Menu\Page\Collection'], $DI['Menu\Post\Collection']);
            };
        }
    }

    /**
     * Retrieve a registered service from DI container.
     * @param string $service
     * @return mixed
     */
    public static function getService($service)
    {
        return static::$DI[$service];
    }

    /**
     * Get the loaded (current) Page from DI container. This is a shortcut to Application::getService('Page').
     * @return Page
     */

    public static function getPage()
    {
        return static::$page;
    }

    public static function setPage(Page $page)
    {
        static::$page = $page;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $middlewares = $this->getMiddleware();
        $dispatcher = new MiddlewareDispatcher($middlewares);
        $request = static::getService('Request');
        $response = $dispatcher->dispatch($request);

        Hook::trigger(Hook::ACTION, 'outputGenerated', $response);

        $this->emitResponse($response);

        Hook::trigger(Hook::ACTION, 'outputRendered');
    }

    /**
     * @param ResponseInterface $response
     */
    private function emitResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        http_response_code($statusCode);
        foreach ($response->getHeaders() as $k => $values) {
            foreach ($values as $v) {
                header(sprintf('%s: %s', $k, $v), false);
            }
        }
        echo $response->getBody();
    }

    public static function getRoute()
    {
        return static::getService('Environment')->getRoute();
    }

    public static function getRouteLine()
    {
        return static::getService('Environment')->getRouteLine();
    }

    public static function getBasePath()
    {
        return static::getService('Environment')->getBasePath();
    }
}
