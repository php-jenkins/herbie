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

namespace Herbie;

use Herbie\Menu\MenuItemTrait;

/**
 * Stores the page.
 */
class Page
{
    protected $id;
    protected $parent;

    use MenuItemTrait;

    /**
     * @var array
     */
    protected $segments = [];

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->data['title'] ?? '';
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return isset($this->data['path']) ? $this->data['path'] : '';
    }

    /**
     * @return array
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    /**
     *
     * @param string $id
     * @return StringValue
     */
    public function getSegment(string $id): StringValue
    {
        $segment = new StringValue();
        if (array_key_exists($id, $this->segments)) {
            $segment->set($this->segments[$id]);
        }
        return $segment;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    /**
     * @param array $data
     * @throws \LogicException
     */
    public function setData(array $data)
    {
        if (array_key_exists('segments', $data)) {
            throw new \LogicException("Field segments is not allowed.");
        }
        foreach ($data as $key => $value) {
            $this->__set($key, $value);
        }
    }

    /**
     * @param string $format
     */
    public function setFormat(string $format)
    {
        switch ($format) {
            case 'md':
            case 'markdown':
                $format = 'markdown';
                break;
            case 'textile':
                $format = 'textile';
                break;
            default:
                $format = 'raw';
        }
        $this->data['format'] = $format;
    }

    /**
     * @param array $segments
     */
    public function setSegments(array $segments = [])
    {
        $this->segments = $segments;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'segments' => $this->segments
        ];
    }

    /**
     * Get the http status code depending on a set error code.
     * @return int
     */
    public function getStatusCode(): int
    {
        if (empty($this->data['error'])) {
            return 200;
        }
        if (empty($this->data['error']['code'])) {
            return 500;
        }
        return $this->data['error']['code'];
    }

    /**
     * @param \Throwable $e
     */
    public function setError(\Throwable $e)
    {
        $this->data['error'] = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }

    /**
     * @return string
     */
    public function getDefaultBlocksPath(): string
    {
        $pathinfo = pathinfo($this->path);
        return $pathinfo['dirname'] . '/_' . $pathinfo['filename'];
    }
}
