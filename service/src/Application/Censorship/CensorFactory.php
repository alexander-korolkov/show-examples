<?php

namespace Fxtm\CopyTrading\Application\Censorship;

class CensorFactory
{
    public function __invoke()
    {
        $dictionaries = [
            new BrandDictionary(),
            new CurseWordsDictionary(),
        ];

        return new Censor($dictionaries);
    }
}
