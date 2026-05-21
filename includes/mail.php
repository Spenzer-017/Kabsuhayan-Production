<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require __DIR__ . '/../phpmailer/src/Exception.php';
    require __DIR__ . '/../phpmailer/src/PHPMailer.php';
    require __DIR__ . '/../phpmailer/src/SMTP.php';

    require_once __DIR__ . '/env.php';
    loadEnv(__DIR__ . '/../.env');

    function sendMail(string $fromEmail, string $fromName, string $subject, string $body) {
        $mail = new PHPMailer(true);

        try {
            // SMTP config
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
            $mail->Port = $_ENV['MAIL_PORT'];

            // Sender (always your email)
            $mail->setFrom($_ENV['MAIL_FROM'], 'Kabsuhayan Support');

            // Receiver (your inbox)
            $mail->addAddress($_ENV['MAIL_FROM']);

            // User becomes reply-to
            $mail->addReplyTo($fromEmail, $fromName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            return $mail->send();

        } catch (Exception $e) {
            error_log("Mail Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    function sendVerificationCode(PDO $pdo, string $toEmail, string $toName): array {
        // Delete any previous pending codes for this email
        $pdo->prepare('DELETE FROM email_verifications WHERE email = ?')->execute([$toEmail]);

        // Generate a secure 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Expires in 15 minutes
        $expiresAt = date('Y-m-d H:i:s', time() + 900);

        // Store in DB
        $stmt = $pdo->prepare(
            'INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$toEmail, $code, $expiresAt]);

        // Build the HTML email body
        $appName = 'Kabsuhayan';
        $year = date('Y');
        $body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Verify your email</title>
    </head>
    <body style='margin:0;padding:0;background-color:#f4f4f0;font-family:\"Helvetica Neue\",Arial,sans-serif;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f0;padding:40px 0;'>
        <tr>
        <td align='center'>
            <table width='480' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);'>

            <!-- Header -->
            <tr>
                <td style='background:#163616;padding:32px 40px;text-align:center;'>
                <h1 style='margin:0;color:#4CAF50;font-size:22px;font-weight:700;letter-spacing:-0.3px;'>
                    Kabsu<span style='color:#C0B87A;'>hayan</span>
                </h1>
                <p style='margin:6px 0 0;color:#a8c5a8;font-size:13px;'>CvSU Student Marketplace</p>
                </td>
            </tr>

            <!-- Body -->
            <tr>
                <td style='padding:36px 40px 28px;'>
                <h2 style='margin:0 0 8px;color:#1A2B1A;font-size:18px;font-weight:600;'>Verify your email address</h2>
                <p style='margin:0 0 24px;color:#555;font-size:14px;line-height:1.6;'>
                    Hi <strong>" . htmlspecialchars($toName) . "</strong>, thanks for signing up!<br>
                    Use the code below to complete your registration. It expires in <strong>15 minutes</strong>.
                </p>

                <!-- OTP Box -->
                <div style='background:#f0f7f0;border:2px dashed #005F02;border-radius:10px;padding:24px;text-align:center;margin:0 0 24px;'>
                    <p style='margin:0 0 4px;color:#7A8C7A;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:600;'>Your verification code</p>
                    <p style='margin:0;color:#005F02;font-size:40px;font-weight:700;letter-spacing:10px;font-family:\"Courier New\",monospace;'>{$code}</p>
                </div>

                <p style='margin:0 0 8px;color:#888;font-size:13px;line-height:1.6;'>
                    If you did not create a Kabsuhayan account, you can safely ignore this email.
                </p>
                <p style='margin:0;color:#888;font-size:13px;line-height:1.6;'>
                    <strong>Do not share this code</strong> with anyone.
                </p>
                </td>
            </tr>

            <!-- Footer -->
            <tr>
                <td style='background:#f8f8f5;padding:18px 40px;border-top:1px solid #e8e8e0;text-align:center;'>
                <p style='margin:0;color:#aaa;font-size:12px;'>
                    &copy; {$year} {$appName}. Made by Thumbtack's Team<br>
                    This is an automated message, please do not reply.
                </p>
                </td>
            </tr>

            </table>
        </td>
        </tr>
    </table>
    </body>
    </html>";

        // Send via PHPMailer
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
            $mail->Port = $_ENV['MAIL_PORT'];

            $mail->setFrom($_ENV['MAIL_FROM'], 'Kabsuhayan');
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = "Your Kabsuhayan verification code: {$code}";
            $mail->Body = $body;
            $mail->AltBody = "Your Kabsuhayan verification code is: {$code}\n\nIt expires in 15 minutes. Do not share this code with anyone.";

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            error_log('Verification mail error: ' . $mail->ErrorInfo);

            // Clean up the stored code if sending failed
            $pdo->prepare('DELETE FROM email_verifications WHERE email = ?')->execute([$toEmail]);
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }

    function sendReportEmail(
        string $reporterName,
        string $reporterEmail,
        string $reportType,
        string $targetName,
        string $reason,
        string $details,
        string $targetLink,
        int $reportId
    ): bool {
        $year = date('Y');
        $date = date('F j, Y g:i A');
        $typeLabel = $reportType === 'listing' ? 'Listing Report' : 'User Report';
        $detailsHtml = $details !== ''
            ? htmlspecialchars($details, ENT_QUOTES, 'UTF-8')
            : '<em style="color:#aaa;">No additional details provided.</em>';

        $body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
    <meta charset='UTF-8'>
    <title>Report Received</title>
    </head>
    <body style='margin:0;padding:0;background:#f4f4f0;font-family:\"Helvetica Neue\",Arial,sans-serif;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f0;padding:40px 0;'>
    <tr><td align='center'>
        <table width='520' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);'>

        <!-- Header -->
        <tr>
            <td style='background:#163616;padding:28px 40px;text-align:center;'>
            <h1 style='margin:0;color:#4CAF50;font-size:20px;font-weight:700;'>
                Kabsu<span style='color:#C0B87A;'>hayan</span>
            </h1>
            <p style='margin:6px 0 0;color:#a8c5a8;font-size:13px;'>Report Notification</p>
            </td>
        </tr>

        <!-- Title bar -->
        <tr>
            <td style='background:#b94040;padding:14px 40px;'>
            <p style='margin:0;color:#fff;font-size:15px;font-weight:700;'>New {$typeLabel} Submitted</p>
            </td>
        </tr>

        <!-- Body -->
        <tr>
            <td style='padding:32px 40px;'>
            <p style='margin:0 0 20px;color:#333;font-size:14px;line-height:1.6;'>
                A report has been submitted on <strong>{$date}</strong>. Please review it below.
            </p>

            <!-- Report details table -->
            <table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e8e8e0;border-radius:8px;overflow:hidden;margin-bottom:24px;'>
                <tr style='background:#f8f8f5;'>
                <td style='padding:10px 16px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;width:140px;'>Report ID</td>
                <td style='padding:10px 16px;font-size:14px;color:#333;'>#" . $reportId . "</td>
                </tr>
                <tr style='border-top:1px solid #e8e8e0;'>
                <td style='padding:10px 16px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;'>Report Type</td>
                <td style='padding:10px 16px;font-size:14px;color:#333;'>{$typeLabel}</td>
                </tr>
                <tr style='background:#f8f8f5;border-top:1px solid #e8e8e0;'>
                <td style='padding:10px 16px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;'>Reported " . ($reportType === 'listing' ? 'Listing' : 'User') . "</td>
                <td style='padding:10px 16px;font-size:14px;color:#333;'>
                    <a href='" . htmlspecialchars($targetLink, ENT_QUOTES, 'UTF-8') . "' style='color:#005F02;font-weight:600;'>
                    " . htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8') . "
                    </a>
                </td>
                </tr>
                <tr style='border-top:1px solid #e8e8e0;'>
                <td style='padding:10px 16px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;'>Reported By</td>
                <td style='padding:10px 16px;font-size:14px;color:#333;'>" . htmlspecialchars($reporterName, ENT_QUOTES, 'UTF-8') . " " . htmlspecialchars($reporterEmail, ENT_QUOTES, 'UTF-8') . "</td>
                </tr>
                <tr style='background:#f8f8f5;border-top:1px solid #e8e8e0;'>
                <td style='padding:10px 16px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;'>Reason</td>
                <td style='padding:10px 16px;font-size:14px;color:#b94040;font-weight:600;'>" . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</td>
                </tr>
                <tr style='border-top:1px solid #e8e8e0;'>
                <td style='padding:10px 16px;font-size:12px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.06em;vertical-align:top;'>Details</td>
                <td style='padding:10px 16px;font-size:14px;color:#333;line-height:1.6;'>{$detailsHtml}</td>
                </tr>
            </table>

            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td style='background:#f8f8f5;padding:18px 40px;border-top:1px solid #e8e8e0;text-align:center;'>
            <p style='margin:0;color:#aaa;font-size:12px;'>
                &copy; {$year} Kabsuhayan. Made by Thumbtack's Team<br>
                This is an automated report notification.
            </p>
            </td>
        </tr>

        </table>
    </td></tr>
    </table>
    </body>
    </html>";

        return sendMail(
            $_ENV['MAIL_USERNAME'],
            'Kabsuhayan System',
            "[Kabsuhayan] New {$typeLabel}: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'),
            $body
        );
    }

    function sendPasswordResetEmail(PDO $pdo, string $toEmail, string $toName): array {
        // Clear any existing codes for this email
        $pdo->prepare('DELETE FROM email_verifications WHERE email = ?')->execute([$toEmail]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + 900);

        $pdo->prepare(
            'INSERT INTO email_verifications (email, code, expires_at) VALUES (?, ?, ?)'
        )->execute([$toEmail, $code, $expiresAt]);

        $year = date('Y');
        $body = "
    <!DOCTYPE html>
    <html lang='en'>
    <head><meta charset='UTF-8'><title>Reset your password</title></head>
    <body style='margin:0;padding:0;background:#f4f4f0;font-family:\"Helvetica Neue\",Arial,sans-serif;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f0;padding:40px 0;'>
    <tr><td align='center'>
        <table width='480' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);'>
        <tr>
            <td style='background:#163616;padding:32px 40px;text-align:center;'>
            <h1 style='margin:0;color:#4CAF50;font-size:22px;font-weight:700;'>Kabsu<span style='color:#C0B87A;'>hayan</span></h1>
            <p style='margin:6px 0 0;color:#a8c5a8;font-size:13px;'>CvSU Student Marketplace</p>
            </td>
        </tr>
        <tr>
            <td style='padding:36px 40px 28px;'>
            <h2 style='margin:0 0 8px;color:#1A2B1A;font-size:18px;font-weight:600;'>Reset your password</h2>
            <p style='margin:0 0 24px;color:#555;font-size:14px;line-height:1.6;'>
                Hi <strong>" . htmlspecialchars($toName) . "</strong>, we received a request to reset your Kabsuhayan password.<br>
                Use the code below to proceed. It expires in <strong>15 minutes</strong>.
            </p>
            <div style='background:#f0f7f0;border:2px dashed #005F02;border-radius:10px;padding:24px;text-align:center;margin:0 0 24px;'>
                <p style='margin:0 0 4px;color:#7A8C7A;font-size:12px;text-transform:uppercase;letter-spacing:0.08em;font-weight:600;'>Your reset code</p>
                <p style='margin:0;color:#005F02;font-size:40px;font-weight:700;letter-spacing:10px;font-family:\"Courier New\",monospace;'>{$code}</p>
            </div>
            <p style='margin:0 0 8px;color:#888;font-size:13px;line-height:1.6;'>If you did not request a password reset, you can safely ignore this email.</p>
            <p style='margin:0;color:#888;font-size:13px;'><strong>Do not share this code</strong> with anyone.</p>
            </td>
        </tr>
        <tr>
            <td style='background:#f8f8f5;padding:18px 40px;border-top:1px solid #e8e8e0;text-align:center;'>
            <p style='margin:0;color:#aaa;font-size:12px;'>&copy; {$year} Kabsuhayan. Made by Thumbtack's Team<br>This is an automated message, please do not reply.</p>
            </td>
        </tr>
        </table>
    </td></tr>
    </table>
    </body>
    </html>";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USERNAME'];
            $mail->Password = $_ENV['MAIL_PASSWORD'];
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
            $mail->Port = $_ENV['MAIL_PORT'];

            $mail->setFrom($_ENV['MAIL_FROM'], 'Kabsuhayan');
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = "Your Kabsuhayan password reset code: {$code}";
            $mail->Body = $body;
            $mail->AltBody = "Your Kabsuhayan password reset code is: {$code}\n\nIt expires in 15 minutes. Do not share this code with anyone.";

            $mail->send();
            return ['success' => true, 'error' => null];
        } catch (Exception $e) {
            error_log('Password reset mail error: ' . $mail->ErrorInfo);
            $pdo->prepare('DELETE FROM email_verifications WHERE email = ?')->execute([$toEmail]);
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }
?>