<?php

declare(strict_types=1);

namespace App\Core\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Config\MailConfig;

class MailService
{
    public function __construct(private MailConfig $mailConfig) {}

    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        ?string $from = null,
        ?string $fromName = null
    ): bool {
        try {
            $mail = $this->createMailer($from, $fromName);

            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);

            return $mail->send();
        } catch (Exception $e) {
            error_log(sprintf(
                'MailService: no se pudo enviar el correo a %s: %s',
                $to,
                $e->getMessage()
            ));

            return false;
        }
    }

    private function createMailer(?string $from, ?string $fromName): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isHTML(true);

        $mail->From = $from ?: $this->mailConfig->from();
        $mail->FromName = $fromName ?: $this->mailConfig->fromName();

        $host = $this->mailConfig->host();

        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $this->mailConfig->port();

            $username = $this->mailConfig->username();
            $password = $this->mailConfig->password();

            $mail->SMTPAuth = $username !== '' || $password !== '';
            $mail->Username = $username;
            $mail->Password = $password;

            $encryption = strtolower($this->mailConfig->encryption());

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
        } else {
            $mail->isMail();
        }

        return $mail;
    }

    public function sendTemplate(string $to, string $subject, string $view, array $data = []): bool
    {
        return $this->send($to, $subject, $this->render($view, $data));
    }

    public function render(string $view, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/Shared/Views/Emails/' . ltrim($view, '/') . '.php';

        if (!is_file($path)) {
            throw new \RuntimeException("Plantilla de correo no encontrada: {$path}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}