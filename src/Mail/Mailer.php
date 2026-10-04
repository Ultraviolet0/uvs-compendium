<?php

declare(strict_types=1);

namespace Uvs\Mail;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use Uvs\Config;
use Uvs\Logger;

/**
 * Provider-neutral transactional email.
 *
 * - smtp: any standard SMTP mailbox (for example the hosting plan's own mailbox).
 * - log: writes messages to private storage for local development and tests;
 *        refused in production so reset links never sit on disk there.
 * - disabled: nothing is sent; callers degrade gracefully.
 */
final class Mailer
{
    public function __construct(
        private readonly Config $config,
        private readonly string $captureDirectory,
        private readonly Logger $logger,
    ) {
    }

    public function transport(): string
    {
        $transport = (string) $this->config->get('mail.transport', 'disabled');
        if ($transport === 'log' && $this->config->isProduction()) {
            return 'disabled';
        }
        if ($transport === 'smtp' && (!is_string($this->config->get('mail.smtp.host')) || !$this->fromAddress())) {
            return 'disabled';
        }
        return in_array($transport, ['smtp', 'log'], true) ? $transport : 'disabled';
    }

    public function isEnabled(): bool
    {
        return $this->transport() !== 'disabled';
    }

    private function fromAddress(): ?string
    {
        $from = $this->config->get('mail.from_address');
        if (is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        return $this->config->isProduction() ? null : 'no-reply@localhost.test';
    }

    public function send(string $to, string $subject, string $text): bool
    {
        $transport = $this->transport();
        if ($transport === 'disabled') {
            $this->logger->warning('Email not sent: mail transport is not configured', ['subject' => $subject]);
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        if ($transport === 'log') {
            return $this->capture($to, $subject, $text);
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $this->config->get('mail.smtp.host');
            $mail->Port = (int) $this->config->get('mail.smtp.port', 587);
            $encryption = (string) $this->config->get('mail.smtp.encryption', 'tls');
            $mail->SMTPSecure = $encryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
            $username = $this->config->get('mail.smtp.username');
            if (is_string($username) && $username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = (string) $this->config->get('mail.smtp.password', '');
            }
            $mail->Timeout = (int) $this->config->get('mail.smtp.timeout', 10);
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom((string) $this->fromAddress(), (string) $this->config->get('mail.from_name', "UV's Compendium"));
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $text;
            $mail->isHTML(false);
            $mail->send();
            return true;
        } catch (MailException) {
            // PHPMailer's message can include server responses; record only the fact of failure.
            $this->logger->error('SMTP delivery failed', ['subject' => $subject]);
            return false;
        }
    }

    private function capture(string $to, string $subject, string $text): bool
    {
        if (!is_dir($this->captureDirectory)) {
            @mkdir($this->captureDirectory, 0700, true);
        }
        $file = sprintf('%s/%s-%s.eml', $this->captureDirectory, gmdate('Ymd-His'), bin2hex(random_bytes(4)));
        $message = "To: {$to}\nSubject: {$subject}\nDate: " . gmdate('r') . "\n\n{$text}\n";
        return @file_put_contents($file, $message, LOCK_EX) !== false;
    }
}
