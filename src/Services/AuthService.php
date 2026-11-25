<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Security;
use App\Core\Session;
use PDO;

/**
 * Handles customer & admin authentication, password hashing/auto-upgrade, and OTP resets.
 */
final class AuthService
{
    private PDO $dbh;
    private MailService $mailer;

    public function __construct(PDO $dbh, ?MailService $mailer = null)
    {
        $this->dbh = $dbh;
        $this->mailer = $mailer ?? new MailService($dbh);
    }

    /**
     * Verifies password against modern hash or legacy 32-char MD5, auto-upgrading MD5 to Argon2/bcrypt.
     */
    private function verifyAndUpgradeHash(string $plain, string $storedHash, string $table, int $id): bool
    {
        if ($storedHash === '') {
            return false;
        }

        // Modern password_hash()
        if (str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$argon2')) {
            if (!password_verify($plain, $storedHash)) {
                return false;
            }
            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                $newHash = password_hash($plain, PASSWORD_DEFAULT);
                $stmt = $this->dbh->prepare("UPDATE `{$table}` SET Password = :p WHERE id = :id");
                $stmt->execute([':p' => $newHash, ':id' => $id]);
            }
            return true;
        }

        // Legacy 32-char hex MD5 fallback -> transparent upgrade on valid login
        if (strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals(strtolower($storedHash), md5($plain))) {
            $newHash = password_hash($plain, PASSWORD_DEFAULT);
            $stmt = $this->dbh->prepare("UPDATE `{$table}` SET Password = :p WHERE id = :id");
            $stmt->execute([':p' => $newHash, ':id' => $id]);
            return true;
        }

        return false;
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function attemptCustomerLogin(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        $bucket = 'login_user:' . Security::clientIp();

        if (!Security::checkRateLimit($this->dbh, $bucket, 8, 900)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes and try again.'];
        }

        $stmt = $this->dbh->prepare('SELECT id, EmailId, FullName, Password FROM tblusers WHERE EmailId = :id1 OR ContactNo = :id2 LIMIT 1');
        $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
        $user = $stmt->fetch();

        if (!$user || !$this->verifyAndUpgradeHash($password, (string) $user->Password, 'tblusers', (int) $user->id)) {
            Security::hitRateLimit($this->dbh, $bucket);
            return ['success' => false, 'message' => 'Invalid email/phone or password.'];
        }

        Security::clearRateLimit($this->dbh, $bucket);
        Session::regenerate();
        $_SESSION['user_id'] = (int) $user->id;
        $_SESSION['login'] = (string) $user->EmailId;
        $_SESSION['fname'] = (string) $user->FullName;

        return ['success' => true, 'message' => 'Welcome back, ' . $user->FullName . '!'];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function attemptAdminLogin(string $username, string $password): array
    {
        $username = trim($username);
        $bucket = 'login_admin:' . Security::clientIp();

        if (!Security::checkRateLimit($this->dbh, $bucket, 6, 900)) {
            return ['success' => false, 'message' => 'Too many failed admin attempts. Try again in 15 minutes.'];
        }

        $stmt = $this->dbh->prepare('SELECT id, UserName, Password FROM admin WHERE UserName = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !$this->verifyAndUpgradeHash($password, (string) $admin->Password, 'admin', (int) $admin->id)) {
            Security::hitRateLimit($this->dbh, $bucket);
            return ['success' => false, 'message' => 'Invalid admin username or password.'];
        }

        Security::clearRateLimit($this->dbh, $bucket);
        Session::regenerate();
        $_SESSION['admin_id'] = (int) $admin->id;
        $_SESSION['alogin'] = (string) $admin->UserName;

        return ['success' => true, 'message' => 'Signed in as administrator.'];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function registerCustomer(string $fullName, string $email, string $mobile, string $password): array
    {
        $fullName = trim($fullName);
        $email = strtolower(trim($email));
        $mobile = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please enter a valid full name and email address.'];
        }
        if (strlen($mobile) < 10 || strlen($mobile) > 15) {
            return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }

        $chk = $this->dbh->prepare('SELECT id FROM tblusers WHERE EmailId = :email LIMIT 1');
        $chk->execute([':email' => $email]);
        if ($chk->fetch()) {
            return ['success' => false, 'message' => 'An account with this email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $this->dbh->prepare('INSERT INTO tblusers (FullName, EmailId, ContactNo, Password, Role) VALUES (:name, :email, :mobile, :pass, :role)');
        $ok = $ins->execute([
            ':name' => $fullName,
            ':email' => $email,
            ':mobile' => $mobile,
            ':pass' => $hash,
            ':role' => 'customer',
        ]);

        if (!$ok) {
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }

        return ['success' => true, 'message' => 'Registration successful! You can now sign in.'];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function updateCustomerPassword(string $email, string $currentPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }

        $stmt = $this->dbh->prepare('SELECT id, Password FROM tblusers WHERE EmailId = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !$this->verifyAndUpgradeHash($currentPassword, (string) $user->Password, 'tblusers', (int) $user->id)) {
            return ['success' => false, 'message' => 'Your current password is incorrect.'];
        }

        $upd = $this->dbh->prepare('UPDATE tblusers SET Password = :p WHERE id = :id');
        $upd->execute([':p' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => (int) $user->id]);

        return ['success' => true, 'message' => 'Your password has been updated successfully.'];
    }

    /**
     * @return array{success:bool, message:string}
     */
    public function updateAdminPassword(string $username, string $currentPassword, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }

        $stmt = $this->dbh->prepare('SELECT id, Password FROM admin WHERE UserName = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !$this->verifyAndUpgradeHash($currentPassword, (string) $admin->Password, 'admin', (int) $admin->id)) {
            return ['success' => false, 'message' => 'Your current password is not valid.'];
        }

        $upd = $this->dbh->prepare('UPDATE admin SET Password = :p WHERE id = :id');
        $upd->execute([':p' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => (int) $admin->id]);

        return ['success' => true, 'message' => 'Admin password updated successfully.'];
    }

    /**
     * Requests a 6-digit OTP for password recovery.
     *
     * @return array{success:bool, message:string, local_preview_otp?:string}
     */
    public function requestPasswordResetOtp(string $email): array
    {
        $email = strtolower(trim($email));
        $bucket = 'otp_req:' . Security::clientIp();

        if (!Security::checkRateLimit($this->dbh, $bucket, 5, 900)) {
            return ['success' => false, 'message' => 'Too many reset requests. Please try again later.'];
        }
        Security::hitRateLimit($this->dbh, $bucket);

        $stmt = $this->dbh->prepare('SELECT id, FullName FROM tblusers WHERE EmailId = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            // Generic response avoids account enumeration
            return ['success' => true, 'message' => 'If that email is registered, a 6-digit verification code has been dispatched.'];
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);

        $this->dbh->prepare('UPDATE tblpasswordresets SET used_at = NOW() WHERE email = :email AND used_at IS NULL')
            ->execute([':email' => $email]);

        $ins = $this->dbh->prepare('INSERT INTO tblpasswordresets (email, otp_hash, expires_at) VALUES (:email, :hash, DATE_ADD(NOW(), INTERVAL 15 MINUTE))');
        $ins->execute([':email' => $email, ':hash' => $otpHash]);

        $body = '<p>Hello ' . Security::e($user->FullName) . ',</p>'
            . '<p>Your DriveX Assam password reset verification code is: <strong style="font-size:20px;">' . $otp . '</strong></p>'
            . '<p>This code expires in 15 minutes. If you did not request this, please ignore this email.</p>';

        $mailResult = $this->mailer->send($email, (string) $user->FullName, 'DriveX Assam - Password Reset Code', $body);

        $res = ['success' => true, 'message' => 'Verification code sent. Please check your email (valid for 15 minutes).'];
        if (Env::get('APP_ENV', 'local') === 'local' && $mailResult['mode'] === 'outbox_fallback') {
            $res['message'] .= ' (Local dev mode: SMTP is disabled, check Admin Mail Outbox or use code ' . $otp . '.)';
            $res['local_preview_otp'] = $otp;
        }
        return $res;
    }

    /**
     * Verifies OTP and updates password.
     *
     * @return array{success:bool, message:string}
     */
    public function resetPasswordWithOtp(string $email, string $otp, string $newPassword): array
    {
        $email = strtolower(trim($email));
        $otp = trim($otp);

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }

        $stmt = $this->dbh->prepare(
            'SELECT id, otp_hash, attempts, (expires_at < NOW()) AS is_expired FROM tblpasswordresets
             WHERE email = :email AND used_at IS NULL
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $reset = $stmt->fetch();

        if (!$reset) {
            return ['success' => false, 'message' => 'No active reset request found. Please request a new code.'];
        }
        if ((int) $reset->attempts >= 5) {
            return ['success' => false, 'message' => 'Too many invalid codes entered. Please request a new code.'];
        }
        if ((int) $reset->is_expired === 1) {
            return ['success' => false, 'message' => 'Verification code has expired. Please request a new one.'];
        }

        if (!password_verify($otp, (string) $reset->otp_hash)) {
            $this->dbh->prepare('UPDATE tblpasswordresets SET attempts = attempts + 1 WHERE id = :id')
                ->execute([':id' => (int) $reset->id]);
            return ['success' => false, 'message' => 'Invalid verification code.'];
        }

        $this->dbh->beginTransaction();
        try {
            $this->dbh->prepare('UPDATE tblusers SET Password = :p WHERE EmailId = :email')
                ->execute([':p' => password_hash($newPassword, PASSWORD_DEFAULT), ':email' => $email]);

            $this->dbh->prepare('UPDATE tblpasswordresets SET used_at = NOW() WHERE id = :id')
                ->execute([':id' => (int) $reset->id]);

            $this->dbh->commit();
            return ['success' => true, 'message' => 'Your password has been reset successfully. You may now sign in.'];
        } catch (\Throwable $e) {
            $this->dbh->rollBack();
            return ['success' => false, 'message' => 'Could not reset password. Please try again.'];
        }
    }

    public static function logoutCustomer(): void
    {
        Session::start();
        unset($_SESSION['login'], $_SESSION['user_id'], $_SESSION['fname']);
        session_regenerate_id(true);
    }

    public static function logoutAdmin(): void
    {
        Session::start();
        unset($_SESSION['alogin'], $_SESSION['admin_id']);
        session_regenerate_id(true);
    }
}
