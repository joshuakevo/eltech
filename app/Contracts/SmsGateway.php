<?php

namespace App\Contracts;

interface SmsGateway
{
    /**
     * Send one SMS. $to is already normalized to E.164 (+256...).
     *
     * @return array{success: bool, message: string}
     */
    public function send(string $to, string $message): array;
}
