<?php

namespace Fxtm\CopyTrading\Application\Services\LeaderProfile;

class LeaderProfileChecker
{
    /**
     * Checks that leader filled own profile:
     * avatar, country or residence, name/nickname and strategy description
     *
     * @param array $profile
     * @return bool
     */
    public function hasFilledProfile(array $profile) : bool
    {
        return $this->checkFilledAvatar($profile);
    }

    /**
     * Checks that leader filled his avatar
     *
     * @param array $profile
     * @return bool
     */
    private function checkFilledAvatar(array $profile) : bool
    {
        return !empty($profile['avatar']);
    }

    /**
     * Checks that leader filled his country
     *
     * @param array $profile
     * @return bool
     */
    private function checkFilledCountry(array $profile) : bool
    {
        return isset($profile['show_country']) && $profile['show_country'] == true && !empty($profile['country']);
    }

    /**
     * Checks that leader filled his real name or nickname
     *
     * @param array $profile
     * @return bool
     */
    private function checkFilledName(array $profile) : bool
    {
        return
            isset($profile['show_name']) &&
            $profile['show_name'] == true;
    }

    /**
     * Checks that leader filled strategy description
     *
     * @param array $profile
     * @return bool
     */
    public function checkFilledDescription(array $profile) : bool
    {
        return !empty($profile['acc_description']);
    }
}
