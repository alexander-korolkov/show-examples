<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics\Utils;

trait OptionsTrait
{
    /**
     * @var array
     */
    private $options = [];

    /**
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        $this->options = $options;
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return $this->options;
    }

    /**
     * Checks that workflow started with option force-update
     *
     * @return bool
     */
    protected function forceUpdate(): bool
    {
        return array_key_exists('force-update', $this->getOptions());
    }

    /**
     * Checks that workflow started with option debug true
     *
     * @return bool
     */
    protected function debugMode(): bool
    {
        return array_key_exists('debug', $this->getOptions());
    }
}
