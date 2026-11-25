<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Centralized mailer using PHPMailer SMTP with automatic DB outbox fallback.
 */
final class MailService
{
    private PDO $dbh;

    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * Sends an HTML email via SMTP, or queues it in tblmailoutbox if SMTP is unconfigured or fails.
     *
     * @return array{sent:bool, mode:string, message:string}
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $replyTo = null): array
    {
        $host = Env::get('SMTP_HOST', 'smtp.example.com') ?? 'smtp.example.com';
        $user = Env::get('SMTP_USER', '') ?? '';
        $pass = Env::get('SMTP_PASS', '') ?? '';
        $fromEmail = Env::get('EMAIL_FROM', 'noreply@drivexassam.local') ?? 'noreply@drivexassam.local';
        $fromName = Env::get('EMAIL_FROM_NAME', 'DriveX Assam') ?? 'DriveX Assam';

        $smtpConfigured = $host !== '' && $host !== 'smtp.example.com' && $user !== '' && $pass !== '';

        if (!$smtpConfigured) {
            $this->queueOutbox($toEmail, $subject, $htmlBody, 'queued_fallback', 'SMTP not configured; stored in local outbox.');
            return [
                'sent' => true,
                'mode' => 'outbox_fallback',
                'message' => 'Email logged to local mail outbox (SMTP not configured).',
            ];
        }

        try {
            $phpMailerDir = APP_ROOT . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR;
            require_once $phpMailerDir . 'Exception.php';
            require_once $phpMailerDir . 'PHPMailer.php';
            require_once $phpMailerDir . 'SMTP.php';

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = Env::get('SMTP_SECURE', 'tls') ?: 'tls';
            $mail->Port = Env::int('SMTP_PORT', 587);
            $mail->Timeout = 8;

            if (Env::get('APP_ENV', 'local') === 'local') {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);
            if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->send();

            $this->queueOutbox($toEmail, $subject, $htmlBody, 'sent', null);
            return ['sent' => true, 'mode' => 'smtp', 'message' => 'Email sent via SMTP.'];
        } catch (Throwable $e) {
            error_log('MailService SMTP error: ' . $e->getMessage());
            $this->queueOutbox($toEmail, $subject, $htmlBody, 'smtp_failed_fallback', $e->getMessage());
            return [
                'sent' => false,
                'mode' => 'outbox_fallback',
                'message' => 'SMTP delivery failed; saved in mail outbox.',
            ];
        }
    }

    public function notifyAdmin(string $subject, string $htmlBody, ?string $replyTo = null): array
    {
        $adminEmail = Env::get('EMAIL_TO', 'admin@drivexassam.local') ?? 'admin@drivexassam.local';
        $adminName = Env::get('EMAIL_TO_NAME', 'DriveX Admin') ?? 'DriveX Admin';
        return $this->send($adminEmail, $adminName, $subject, $htmlBody, $replyTo);
    }

    private function queueOutbox(string $recipient, string $subject, string $body, string $status, ?string $error): void
    {
        try {
            $stmt = $this->dbh->prepare(
                'INSERT INTO tblmailoutbox (recipient, subject, body, status, error_message) VALUES (:r, :s, :b, :st, :err)'
            );
            $stmt->execute([
                ':r' => substr($recipient, 0, 150),
                ':s' => substr($subject, 0, 255),
                ':b' => $body,
                ':st' => $status,
                ':err' => $error,
            ]);
        } catch (Throwable $e) {
            error_log('MailService outbox fallback failed: ' . $e->getMessage());
        }
    }
}
