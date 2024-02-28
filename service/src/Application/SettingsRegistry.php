<?php

namespace Fxtm\CopyTrading\Application;

interface SettingsRegistry
{
    public const LEADER_WORKFLOW_PROCESSING_SETTING_NAME = 'leader.workflows.processing_status';
    public const FOLLOWER_WORKFLOW_PROCESSING_SETTING_NAME = 'follower.workflows.processing_status';
    public const ACCOUNTS_OPENING_PROCESSING_SETTING_NAME = 'accounts_opening.processing_status';

    public function get($key, $default = null);
    public function set($key, $value);
    public function getAll();
}
