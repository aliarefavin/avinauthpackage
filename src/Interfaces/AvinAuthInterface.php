<?php

namespace Interfaces;

interface AvinAuthInterface
{

    /**
     * Send verify code to receiver.
     *
     * @param string $receiver
     * @param string $code
     * @return bool
     */
    public function send(string $receiver, string $code);
}
