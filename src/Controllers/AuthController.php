<?php
namespace App\Controllers;


use App\Models\MailQueue;
use App\Models\User;


class AuthController extends BaseController
{
    public function login()
    {
        $email='';

        $error = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('login', $_POST['_csrf'] ?? null)) {
                $error = 'Neplatný CSRF token';
            } else {
                $email = trim($_POST['email'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error='Nejde o email.';
                    exit;
                }
                $password = $_POST['password'] ?? '';
                $user = User::findByEmail($this->db, $email);
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    ];
                    header('Location: /profile');
                    
                    exit;
                } 
                $error = 'Nesprávný  e-mail nebo heslo.';
            }
        }else{
            //$error="neplatná metoda";
        }
        echo $this->twig->render('pages/login.twig', [
        'error' => $error,
        'email' =>   $email,
        'csrf' => csrf_token('login')
        ]);
    }


    public function register()
    {
        $error = null; $ok = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('register', $_POST['_csrf'] ?? null)) {
                $error = 'Neplatný CSRF token';
            } else {
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';


                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Neplatný e-mail.';
                } elseif (strlen($password) < 6) {
                    $error = 'Heslo musí mít alespoň 6 znaků.';
                } elseif (User::findByEmail($this->db, $email)) {
                    $error = 'E-mail je již registrován.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $userId = User::create($this->db, $name, $email, $hash);
                    $_SESSION['user'] = [
                        'id' => $userId,
                        'name' => $name,
                        'email' => $email,
                        'role' => 'user',
                    ];

                    $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . $_SERVER['HTTP_HOST'] . '/login';

                    MailQueue::addWithTemplate(
                        $this->db,
                        $email,
                        'Vítej na Improtřesku 2026!',
                        'registration.twig',
                        ['name' => $name, 'email' => $email, 'loginUrl' => $loginUrl]
                    );

                    header('Location: /profile');
                    exit;
                }
            }
        }
        echo $this->twig->render('pages/registration.twig', [
        'error' => $error,
        'csrf' => csrf_token('register')
        ]);
    }


    public function logout()
    {
        session_destroy();
        header('Location: /');
        exit;
    }

    /**
     * Request password reset - sends email with reset link
     */
    public function requestPasswordReset()
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Neplatná metoda']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $email = trim($data['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Neplatný e-mail']);
            exit;
        }

        // Generate token (returns false if user not found)
        $token = User::createPasswordResetToken($this->db, $email);

        if ($token) {
            // Send email with reset link
            $resetUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . $_SERVER['HTTP_HOST'] . '/reset-password?token=' . $token;

            MailQueue::addWithTemplate(
                        $this->db,
                        $email,
                        'Obnova hesla - Improtřesk 2026',
                    'password-reset.twig',
                    [
                        'resetUrl' => $resetUrl,
                        'email' => $email
                    ]
                    ); 
        }

        // Always return success to prevent email enumeration
        echo json_encode([
            'success' => true,
            'message' => 'Pokud e-mail existuje v systému, byl na něj odeslán odkaz na obnovu hesla.'
        ]);
        exit;
    }

    /**
     * Show reset password form.
     * Without ?token  → email-entry form (step 1).
     * With ?token     → new-password form (step 2).
     */
    public function showResetPasswordForm()
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            echo $this->twig->render('pages/reset-password2.twig', [
                'csrf' => csrf_token('request_reset'),
            ]);
            exit;
        }

        $email = User::verifyPasswordResetToken($this->db, $token);

        if (!$email) {
            echo $this->twig->render('pages/reset-password2.twig', [
                'error' => 'Neplatný nebo expirovaný odkaz na obnovu hesla.',
            ]);
            exit;
        }

        echo $this->twig->render('pages/reset-password2.twig', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Process password reset
     */
    public function resetPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /');
            exit;
        }

        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // Validate input
        if (empty($token) || empty($password)) {
            echo $this->twig->render('pages/reset-password.twig', [
                'token' => $token,
                'error' => 'Všechna pole jsou povinná.'
            ]);
            exit;
        }

        if (strlen($password) < 6) {
            $email = User::verifyPasswordResetToken($this->db, $token);
            echo $this->twig->render('pages/reset-password.twig', [
                'token' => $token,
                'email' => $email,
                'error' => 'Heslo musí mít alespoň 6 znaků.'
            ]);
            exit;
        }

        if ($password !== $passwordConfirm) {
            $email = User::verifyPasswordResetToken($this->db, $token);
            echo $this->twig->render('pages/reset-password.twig', [
                'token' => $token,
                'email' => $email,
                'error' => 'Hesla se neshodují.'
            ]);
            exit;
        }

        // Reset password
        $success = User::resetPassword($this->db, $token, $password);

        if (!$success) {
            echo $this->twig->render('pages/reset-password.twig', [
                'error' => 'Neplatný nebo expirovaný odkaz na obnovu hesla.'
            ]);
            exit;
        }

        // Success - redirect to login
        echo $this->twig->render('pages/login.twig', [
            'success' => 'Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.'
        ]);
    }
}