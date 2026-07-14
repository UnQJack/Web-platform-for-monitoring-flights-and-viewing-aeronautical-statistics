<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';
require_once 'add_notification.php';

function sendEmailNotification($conn, $to, $subject, $message) {

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        $mail->Username = 'daniserb023@gmail.com';
        $mail->Password = 'cvwvxbkzpdgiwive';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('daniserb023@gmail.com', 'SkyTix');
        $mail->addAddress($to);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);

        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();

        $status = 'Sent';

        addNotification(
            $conn,
            'email',
            'Email trimis',
            'A fost trimis un email catre ' . $to . '.'
        );

    } catch (Exception $e) {

        $status = 'Failed';

        $message .= "\nError: " . $mail->ErrorInfo;

        addNotification(
            $conn,
            'email',
            'Eroare email',
            'Trimiterea emailului catre ' . $to . ' a esuat.'
        );
    }

    $stmt = $conn->prepare("
        INSERT INTO email (
            user_email,
            subject,
            message,
            status,
            created_at
        )
        VALUES (?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        "ssss",
        $to,
        $subject,
        $message,
        $status
    );

    $stmt->execute();
    $stmt->close();

    return $status === 'Sent';
}