<?php
// ========================================================
// api/mailer.php - Native TLS SMTP Mailer for Gmail / Google Workspace
// Sends real HTML emails directly to student inboxes with zero external dependencies
// ========================================================

// Load SMTP Configuration from protected config file
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/config.example.php')) {
    require_once __DIR__ . '/config.example.php';
}


/**
 * Sends a real 6-digit OTP verification email via Google SMTP
 * 
 * @param string $toEmail Recipient email address
 * @param string $otpCode 6-digit numeric OTP
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 */
function sendOtpEmail($toEmail, $otpCode) {
    $timeout = 15;

    // Stream context allowing secure TLS on Windows XAMPP
    $context = stream_context_create([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]
    ]);

    // Connect to Google SMTP
    $socket = @stream_socket_client(
        'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        return [
            'success' => false,
            'error'   => "Socket connection failed: $errstr ($errno)"
        ];
    }

    try {
        // Helper to read SMTP responses
        $readResponse = function($expectedCode) use ($socket) {
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') {
                    break;
                }
            }
            if (substr($response, 0, 3) !== (string)$expectedCode) {
                throw new Exception("SMTP Unexpected response: $response (Expected $expectedCode)");
            }
            return $response;
        };

        // 1. Initial 220 Service Ready
        $readResponse(220);

        // 2. EHLO
        fwrite($socket, "EHLO localhost\r\n");
        $readResponse(250);

        // 3. STARTTLS
        fwrite($socket, "STARTTLS\r\n");
        $readResponse(220);

        // 4. Negotiate TLS encryption
        $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (!stream_socket_enable_crypto($socket, true, $cryptoMethod)) {
            throw new Exception("TLS negotiation failed with Google SMTP");
        }

        // 5. Re-send EHLO after TLS handshake
        fwrite($socket, "EHLO localhost\r\n");
        $readResponse(250);

        // 6. Authenticate with Google
        fwrite($socket, "AUTH LOGIN\r\n");
        $readResponse(334);

        fwrite($socket, base64_encode(SMTP_USER) . "\r\n");
        $readResponse(334);

        fwrite($socket, base64_encode(SMTP_PASS) . "\r\n");
        $readResponse(235); // 235 2.7.0 Accepted

        // 7. Envelope headers
        fwrite($socket, "MAIL FROM:<" . SMTP_USER . ">\r\n");
        $readResponse(250);

        fwrite($socket, "RCPT TO:<" . $toEmail . ">\r\n");
        $readResponse(250);

        fwrite($socket, "DATA\r\n");
        $readResponse(354);

        // 8. Build Modern HTML Email
        $subject = "Your Campus Events Login Code: $otpCode";
        $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";

        $headers  = "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
        $headers .= "To: <" . $toEmail . ">\r\n";
        $headers .= "Subject: " . $encodedSubject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "\r\n";

        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #0b0f19; margin: 0; padding: 30px 10px; color: #f8fafc; }
                .email-card { max-width: 500px; margin: 0 auto; background-color: #1a2234; border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
                .header { background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); padding: 25px; text-align: center; }
                .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px; }
                .content { padding: 30px 25px; text-align: center; }
                .content p { font-size: 15px; color: #94a3b8; line-height: 1.6; margin: 0 0 20px; }
                .otp-box { background-color: #111827; border: 2px dashed #6366f1; border-radius: 12px; padding: 18px; margin: 25px 0; display: inline-block; }
                .otp-code { font-family: monospace; font-size: 34px; font-weight: 800; letter-spacing: 8px; color: #38bdf8; margin: 0; }
                .timer-badge { display: inline-block; background-color: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 10px; }
                .footer { border-top: 1px solid rgba(255,255,255,0.06); padding: 20px; text-align: center; font-size: 12px; color: #64748b; background-color: #151d2f; }
            </style>
        </head>
        <body>
            <div class="email-card">
                <div class="header">
                    <h1>🎓 CampusConnect Events</h1>
                </div>
                <div class="content">
                    <p>Hello,</p>
                    <p>Use the one-time verification code below to login and manage your campus event passes:</p>
                    
                    <div class="otp-box">
                        <div class="otp-code">' . $otpCode . '</div>
                    </div>
                    
                    <div>
                        <span class="timer-badge">⏳ Valid for 10 minutes</span>
                    </div>

                    <p style="margin-top: 25px; font-size: 13px; color: #64748b;">
                        If you did not request this verification code, please ignore this email.
                    </p>
                </div>
                <div class="footer">
                    &copy; 2026 Campus Event Registration System | IIT (ISM) Dhanbad
                </div>
            </div>
        </body>
        </html>
        ';

        // 9. Send email payload
        fwrite($socket, $headers . $htmlBody . "\r\n.\r\n");
        $readResponse(250);

        // 10. QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        return [
            'success' => true,
            'message' => 'Email delivered successfully via Google SMTP'
        ];

    } catch (Exception $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        return [
            'success' => false,
            'error'   => $e->getMessage()
        ];
    }
}
