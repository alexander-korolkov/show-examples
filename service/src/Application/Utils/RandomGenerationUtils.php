<?php

namespace Fxtm\CopyTrading\Application\Utils;

trait RandomGenerationUtils
{
    protected function generateRandomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    protected function generateRandomInt(int $length = 1): int
    {
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        return rand($min, $max);
    }

    protected function generateRandomDateTime(): \DateTime
    {
        $dateTime = new \DateTime();
        $dateTime->setTimestamp(
            rand(1, time())
        );
        return $dateTime;
    }

    protected function generateRandomBool(): bool
    {
        return rand(0, 1) === 1;
    }

    protected function generateRandomEmail(
        int $mainLength = 10,
        int $subdomainLength = 5,
        int $domainLength = 3
    ): string {
        return $this->generateRandomString($mainLength) . '@' .
            $this->generateRandomString($subdomainLength) . '.' .
            $this->generateRandomString($domainLength);
    }

    protected function generateRandomPhone(): string
    {
        return '+' . $this->generateRandomInt(11);
    }

    protected function generateRandomIp(): string
    {
        return mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255);
    }
}
