<?php
/**
 * Email sending through SMTP (settings are managed in Admin → Settings → Email).
 */

require_once SHIFA_ROOT . '/includes/vendor/PHPMailer/Exception.php';
require_once SHIFA_ROOT . '/includes/vendor/PHPMailer/PHPMailer.php';
require_once SHIFA_ROOT . '/includes/vendor/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/** Last error message from send_mail(), for showing in the admin panel. */
$GLOBALS['MAIL_ERROR'] = '';

function mail_log(string $line): void
{
    @file_put_contents(SHIFA_ROOT . '/data/mail.log', '[' . now() . '] ' . $line . PHP_EOL, FILE_APPEND);
}

/**
 * Send one HTML email. Returns true on success. Never throws — order placement must not fail because of email.
 */
function send_mail(string $to, string $subject, string $html, array $opts = []): bool
{
    $GLOBALS['MAIL_ERROR'] = '';
    if (!valid_email($to)) {
        $GLOBALS['MAIL_ERROR'] = 'Invalid recipient address';
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $host = trim((string)setting('smtp_host'));
        if ($host !== '') {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = (int)setting('smtp_port', '587');
            $secure = setting('smtp_secure', 'tls');
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $user = (string)setting('smtp_user');
            if ($user !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $user;
                $mail->Password = (string)setting('smtp_pass');
            }
            $mail->Timeout = 20;
        } else {
            $mail->isMail(); // fall back to the hosting server's mail()
        }
        $fromEmail = setting('mail_from_email', setting('smtp_user', setting('notify_email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))));
        $fromName = setting('mail_from_name', setting('store_name', 'Shifa'));
        $mail->setFrom($fromEmail, $fromName);
        if (!empty($opts['reply_to']) && valid_email($opts['reply_to'])) {
            $mail->addReplyTo($opts['reply_to'], $opts['reply_name'] ?? '');
        }
        $mail->addAddress($to, $opts['to_name'] ?? '');
        if (!empty($opts['unsubscribe'])) {
            $mail->addCustomHeader('List-Unsubscribe', '<' . $opts['unsubscribe'] . '>');
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(preg_replace(['~<br\s*/?>~i', '~</(p|tr|h[1-6]|div)>~i'], "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $mail->send();
        mail_log('SENT to ' . $to . ' | ' . $subject);
        return true;
    } catch (Throwable $ex) {
        $GLOBALS['MAIL_ERROR'] = $mail->ErrorInfo ?: $ex->getMessage();
        mail_log('FAILED to ' . $to . ' | ' . $subject . ' | ' . $GLOBALS['MAIL_ERROR']);
        return false;
    }
}
