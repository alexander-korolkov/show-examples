<?php

namespace Fxtm\CopyTrading\Application\Censorship;

class Censor
{
    /**
     * @var Dictionary[]
     */
    private $dictionaries = [];

    /**
     * Censor constructor.
     * @param array $dictionaries
     */
    public function __construct(array $dictionaries)
    {
        $this->dictionaries = $dictionaries;
    }


    /**
     * Returns bool result of given text validation
     *
     * @param string $text
     * @return bool
     */
    public function pass(string $text) : bool
    {
        foreach ($this->dictionaries as $dictionary) {
            foreach ($dictionary->getWords() as $word) {
                if (stripos($text, $word) !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns given text with replaced bad words
     *
     * @param string $text
     * @return string
     */
    public function replace(string $text) : string
    {
        foreach ($this->dictionaries as $dictionary) {
            $text = str_ireplace(
                array_keys($dictionary->getReplaces()),
                array_values($dictionary->getReplaces()),
                $text
            );
        }

        return $text;
    }
}
