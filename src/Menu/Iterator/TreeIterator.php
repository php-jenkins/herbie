<?php
/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <https://www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Herbie\Menu\Iterator;

use Herbie\Menu\MenuItem;
use Herbie\Menu\MenuTree;

class TreeIterator implements \RecursiveIterator
{
    /**
     * @var array
     */
    protected $children = [];

    /**
     * @var int
     */
    protected $position = 0;

    /**
     * @param mixed $context
     */
    public function __construct($context)
    {
        if (is_object($context)) {
            $this->children = $context->getChildren();
        } elseif (is_array($context)) {
            $this->children = $context;
        }
    }

    /**
     * @return TreeIterator
     */
    public function getChildren()
    {
        return new self($this->children[$this->position]->getChildren());
    }

    /**
     * @return bool
     */
    public function hasChildren(): bool
    {
        return $this->children[$this->position]->hasChildren();
    }

    /**
     * @return MenuTree
     */
    public function current(): MenuTree
    {
        return $this->children[$this->position];
    }

    /**
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * @return void
     */
    public function next(): void
    {
        $this->position++;
    }

    /**
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->children[$this->position]);
    }

    /**
     * @return MenuItem
     */
    public function getMenuItem(): MenuItem
    {
        return $this->children[$this->position]->getMenuItem();
    }
}
