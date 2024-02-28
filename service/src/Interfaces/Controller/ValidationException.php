<?php


namespace Fxtm\CopyTrading\Interfaces\Controller;


class ValidationException extends \RuntimeException
{

    const WRONG_IMAGE_SIZE = 100;
    const NOT_ALLOWED_FILE_TYPE = 200;

}