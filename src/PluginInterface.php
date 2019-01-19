<?php
/**
 * Created by PhpStorm.
 * User: thomas
 * Date: 2019-01-07
 * Time: 19:53
 */

declare(strict_types=1);

namespace Herbie;

interface PluginInterface
{
    public function attach(EventManager $events, int $priority = 1);
}