<?php
/**
 * Mailer Functions
 *
 * Contains functions for sending emails.
 * For production, use a robust library like PHPMailer or Symfony Mailer.
 * This is a basic example using PHP's mail() function.
 *
 * Note: mail() often fails on local development (XAMPP/WAMP)
 * without proper configuration of sendmail.
 */

// We assume config.php is loaded
// require_once __DIR__ . '/config.php';

/**
 * Sends an email.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The email subject.
 * @param string $message The HTML or plain text message.
 * @param string $from_name The sender's name (e.g., SITE_NAME).
 * @param string $from_email The sender's email (e.g., no-reply@lodgie.com).
 * @return bool True on success, false on failure.
 */
function send_email($to, $subject, $message, $from_name = SITE_NAME, $from_email = 'no-reply@lodgie.com') {
    
    // To send HTML mail, the Content-type header must be set
    $headers  = 'MIME-Version: 1.0' . "\r\n";
    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
    
    // Create email headers
    $headers .= 'From: ' . $from_name . ' <' . $from_email . '>' . "\r\n";
    $headers .= 'Reply-To: ' . $from_email . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    // A simple HTML template
    $html_message = '
    <html>
    <body style="font-family: Arial, sans-serif; line-height: 1.6;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2 style="color: #f97316;">' . SITE_NAME . '</h2>
            <p>' . $message . '</p>
            <hr>
            <p style="font-size: 0.9em; color: #777;">
                This is an automated message. Please do not reply.
            </p>
        </div>
    </body>
    </html>';

    // Send the email
    if (mail($to, $subject, $html_message, $headers)) {
        return true;
    } else {
        // Log the error
        error_log("Mail function failed for: $to with subject: $subject");
        return false;
    }
}

/**
 * Example Usage:
 *
 * include_once 'includes/mailer.php';
 * $user_email = 'tenant@example.com';
 * $subject = 'Your Booking is Confirmed!';
 * $body = 'Hi Jane, your booking for "Sunshine Hostel" has been successfully confirmed. <br>Your move-in date is 2025-01-10.';
 *
 * if(send_email($user_email, $subject, $body)) {
 * echo 'Email sent.';
 * } else {
 * echo 'Email failed to send.';
 * }
 */
?>