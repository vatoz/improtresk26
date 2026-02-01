<?php
namespace App\Controllers;


use App\Models\User;


class AuthController extends BaseController
{
    public function login()
    {
        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('login', $_POST['_csrf'] ?? null)) {
                $error = 'Neplatný CSRF token';
            } else {
                $email = trim($_POST['email'] ?? '');
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
                $error = 'Nesprávný e-mail nebo heslo.';
            }
        }
        echo $this->twig->render('login.html.twig', [
        'error' => $error,
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
                    header('Location: /profile');
                    exit;
                }
            }
        }
        echo $this->twig->render('register.html.twig', [
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
}