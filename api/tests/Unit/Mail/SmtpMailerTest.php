<?php

use App\Mail\MailerInterface;
use App\Mail\SmtpMailer;

it('implements MailerInterface', function () {
    $mailer = new SmtpMailer('localhost', 1025, 'noreply@oms.local');
    expect($mailer)->toBeInstanceOf(MailerInterface::class);
});

it('throws RuntimeException when SMTP host is unreachable', function () {
    $mailer = new SmtpMailer('127.0.0.1', 19999, 'noreply@oms.local');
    $mailer->send('dest@example.com', 'Subject', 'Body');
})->throws(\RuntimeException::class);
