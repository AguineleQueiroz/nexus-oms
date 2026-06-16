<?php

namespace App\Mail;

class NullMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body): void
    {
    }
}
