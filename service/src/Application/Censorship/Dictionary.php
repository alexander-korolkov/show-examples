<?php

namespace Fxtm\CopyTrading\Application\Censorship;

interface Dictionary
{
    /**
     * Returns array of words of this dictionary
     *
     * @return array
     */
    public function getWords() : array;

    /**
     * Returns array of words and their acceptable replaces
     *
     * @return array
     */
    public function getReplaces() : array;
}
