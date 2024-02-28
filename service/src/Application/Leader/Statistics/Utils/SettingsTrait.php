<?php

namespace Fxtm\CopyTrading\Application\Leader\Statistics\Utils;

use Fxtm\CopyTrading\Application\SettingsRegistry;
use Fxtm\CopyTrading\Domain\Common\DateTime;

trait SettingsTrait
{
    /**
     * @var SettingsRegistry
     */
    private $settingsRegistry;

    /**
     * @param SettingsRegistry $settingsRegistry
     */
    public function setSettingsRegistry(SettingsRegistry $settingsRegistry)
    {
        $this->settingsRegistry = $settingsRegistry;
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    private function saveSettingsToRegistry(string $key, $value)
    {
        $this->settingsRegistry->set($key, $value);
    }

    /**
     *  Returns true if equities already was updated for the current hour
     *
     * @param string $action
     * @param DateTime $processTime
     * @return bool
     */
    private function alreadyExecutedForThisHour(string $action, DateTime $processTime) : bool
    {
        $lastUpdateAt = DateTime::of($this->settingsRegistry->get("stats.{$action}.last_update", '1970-01-01 00:00:00'));

        return $processTime->format('Y-m-d H') === $lastUpdateAt->format('Y-m-d H');
    }
}
