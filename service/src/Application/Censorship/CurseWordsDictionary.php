<?php


namespace Fxtm\CopyTrading\Application\Censorship;


class CurseWordsDictionary implements Dictionary
{

    private static $words = [
        'fuck' => '',
    ];

    public function getWords(): array
    {
        return array_keys(self::$words);
    }

    public function getReplaces(): array
    {
        return self::$words;
    }

}