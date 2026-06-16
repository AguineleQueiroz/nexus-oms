<?php

namespace App\Mail;

class SmtpMailer implements MailerInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $from,
    )
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connection failed ({$this->host}:{$this->port}): {$errstr} [{$errno}]");
        }

        try {
            $this->expect($socket, '220');
            $this->write($socket, "EHLO oms.local");
            $this->expect($socket, '250');
            $this->write($socket, "MAIL FROM:<{$this->from}>");
            $this->expect($socket, '250');
            $this->write($socket, "RCPT TO:<{$to}>");
            $this->expect($socket, '250');
            $this->write($socket, "DATA");
            $this->expect($socket, '354');

            $date = date('r');
            $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B');
            $this->write($socket, implode("\r\n", [
                "From: {$this->from}",
                "To: {$to}",
                "Subject: {$subject}",
                "Date: {$date}",
                "MIME-Version: 1.0",
                "Content-Type: text/plain; charset=UTF-8",
                "",
                $body,
                ".",
            ]));
            $this->expect($socket, '250');
            $this->write($socket, "QUIT");
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function expect($socket, string $code): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        if (!str_starts_with(trim($response), $code)) {
            throw new \RuntimeException("SMTP unexpected response (expected {$code}): {$response}");
        }
        return $response;
    }

    /** @param resource $socket */
    private function write($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }
}
