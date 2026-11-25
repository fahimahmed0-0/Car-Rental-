<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\AuthService;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new AuthService($dbh);

    if (isset($_POST['auth_login'])) {
        Csrf::requireValid();
        $res = $auth->attemptCustomerLogin(
            (string) ($_POST['login_id'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
        Security::redirect((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }

    if (isset($_POST['auth_signup'])) {
        Csrf::requireValid();
        $res = $auth->registerCustomer(
            (string) ($_POST['fullname'] ?? ''),
            (string) ($_POST['emailid'] ?? ''),
            (string) ($_POST['mobileno'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
        Security::redirect((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }

    if (isset($_POST['auth_request_otp'])) {
        Csrf::requireValid();
        $res = $auth->requestPasswordResetOtp((string) ($_POST['reset_email'] ?? ''));
        $_SESSION['_reset_email_pending'] = strtolower(trim((string) ($_POST['reset_email'] ?? '')));
        Session::setFlash($res['success'] ? 'info' : 'error', $res['message']);
        Security::redirect((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }

    if (isset($_POST['auth_verify_otp'])) {
        Csrf::requireValid();
        $newPass = (string) ($_POST['newpassword'] ?? '');
        $confirm = (string) ($_POST['confirmpassword'] ?? '');
        if ($newPass !== $confirm) {
            Session::setFlash('error', 'New password and confirm password do not match.');
        } else {
            $res = $auth->resetPasswordWithOtp(
                (string) ($_POST['reset_email'] ?? ''),
                (string) ($_POST['otp_code'] ?? ''),
                $newPass
            );
            if ($res['success']) {
                unset($_SESSION['_reset_email_pending']);
            }
            Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
        }
        Security::redirect((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }

    if (isset($_POST['emailsubscribe'])) {
        Csrf::requireValid();
        $subEmail = strtolower(trim((string) ($_POST['subscriberemail'] ?? '')));
        if (!filter_var($subEmail, FILTER_VALIDATE_EMAIL)) {
            Session::setFlash('error', 'Please enter a valid email address to subscribe.');
        } else {
            $chk = $dbh->prepare('SELECT id FROM tblsubscribers WHERE SubscriberEmail = :e LIMIT 1');
            $chk->execute([':e' => $subEmail]);
            if ($chk->fetch()) {
                Session::setFlash('info', 'This email is already subscribed to DriveX Assam updates.');
            } else {
                $ins = $dbh->prepare('INSERT INTO tblsubscribers (SubscriberEmail) VALUES (:e)');
                $ins->execute([':e' => $subEmail]);
                Session::setFlash('success', 'Subscribed successfully! Thank you for joining our newsletter.');
            }
        }
        Security::redirect((string) ($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }
}
