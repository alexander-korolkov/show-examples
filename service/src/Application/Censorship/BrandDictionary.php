<?php

namespace Fxtm\CopyTrading\Application\Censorship;

class BrandDictionary implements Dictionary
{
    private static $words = [
        'forextime' => 'invest',
        'fxtm' => 'invest',
        'alpari' => 'invest',
    ];

    /**
     * Returns array of words of this dictionary
     *
     * @return array
     */
    public function getWords(): array
    {
        return array_keys(self::$words);
    }

    /**
     * Returns array of words and their acceptable replaces
     *
     * @return array
     */
    public function getReplaces(): array
    {
        return self::$words;
    }
}
