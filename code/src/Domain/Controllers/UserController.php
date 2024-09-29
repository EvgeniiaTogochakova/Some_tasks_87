<?php

namespace Geekbrains\Application1\Domain\Controllers;

use Geekbrains\Application1\Application\Application;
use Geekbrains\Application1\Application\Render;
use Geekbrains\Application1\Application\Auth;
use Geekbrains\Application1\Domain\Models\User;

class UserController extends AbstractController
{

    protected array $actionsPermissions = [
        // 'actionHash' => ['admin'],
        // 'actionSave' => ['admin'],
        // 'actionEdit' => ['admin'],
        // 'actionIndex' => ['admin']
        'actionHash' => ['admin'],
        'actionSave' => ['admin'],
        'actionEdit' => ['admin']
    ];

    public function actionIndex(): string
    {
        if (!isset($_SESSION['auth']['id_user'])) {
            throw new \Exception("Пожалуйста, залогиньтесь для работы с системой");
        }
        $users = User::getAllUsersFromStorage();

        $render = new Render();

        if (!$users) {
            return $render->renderPage(
                'user-empty.twig',
                [
                    'title' => 'Список пользователей в хранилище',
                    'message' => "Список пуст или не найден"
                ]
            );
        } else {
            return $render->renderPage(
                'user-index.twig',
                [
                    'title' => 'Список пользователей в хранилище',
                    'users' => $users,
                    'isAdmin' => User::isAdmin($_SESSION['auth']['id_user']) ?? null
                ]
            );
        }
    }

    public function actionSave(): string
    {
        if (User::validateRequestData()) {
            $user = new User();
            $user->setParamsFromRequestData();
            $user->saveToStorage();

            header("Location: /user");
            die();
        } else {
            throw new \Exception("Переданные данные некорректны");
        }
    }

    public function actionDelete(): string
    {
        if (User::exists($_GET['user_id'])) {
            User::deleteFromStorage($_GET['user_id']);

            header('Location: /user');
            die();
        } else {
            throw new \Exception("Пользователь не существует");
        }
    }

    public function actionEdit(): string
    {
        $render = new Render();

        $action = '/user/save';
        if (isset($_GET['user_id'])) {
            $userId = $_GET['user_id'];
            $action = '/user/update';
            $userData = User::getUserDataByID($userId);
        }

        return $render->renderPageWithForm(
            'user-form.twig',
            [
                'title' => 'Форма создания пользователя',
                'user_data' => $userData ?? [],
                'action' => $action
            ]
        );
    }

    public function actionUpdate(): string
    {
        if (User::exists($_POST['user_id'])) {
            $user = new User();
            $user->setUserId($_POST['user_id']);

            $arrayData = [];

            if (isset($_POST['name']))
                $arrayData['user_name'] = $_POST['name'];

            if (isset($_POST['lastname'])) {
                $arrayData['user_lastname'] = $_POST['lastname'];
            }

            if (isset($_POST['login'])) {
                $arrayData['login'] = $_POST['login'];
            }

            $user->updateUser($arrayData);
        } else {
            throw new \Exception("Пользователь не существует");
        }

        $render = new Render();
        return $render->renderPage(
            'user-created.twig',
            [
                'title' => 'Пользователь обновлен',
                'message' => "Обновлен пользователь " . $user->getUserId()
            ]
        );
    }

    public function actionAuth(): string
    {
        $render = new Render();

        return $render->renderPageWithForm(
            'user-auth.twig',
            [
                'title' => 'Форма логина'
            ]
        );
    }

    public function actionHash(): string
    {
        if (isset($_GET['pass_string']) && !empty($_GET['pass_string'])) {
            return Auth::getPasswordHash($_GET['pass_string']);
        } else {
            throw new \Exception("Невозможно сгенерировать хэш. Не передан пароль");
        }
    }

    public function actionLogin(): ?string
    {
        $result = false;

        if (isset($_POST['login']) && isset($_POST['password'])) {
            $result = Application::$auth->proceedAuth($_POST['login'], $_POST['password']);
            if ($result) {
                $token = Application::$auth->generateToken($_SESSION['auth']['id_user']);

                User::setToken($_SESSION['auth']['id_user'], $token);
                if (isset($_POST['user-remember']) && $_POST['user-remember'] == 'remember') {
                    setcookie('auth_token', $token, time() + 60 * 60 * 24 * 30, '/');
                }
                header('Location: /');
                return "";
            } else {
                $render = new Render();

                return $render->renderPageWithForm(
                    'user-auth.twig',
                    [
                        'title' => 'Форма логина'
                    ]
                );
            }
        }
    }

    public function actionLogout(): void
    {
        $tokenFromStorage = User::getTokenFromStorageById($_SESSION['auth']['id_user']);
        setcookie('auth_token', $tokenFromStorage, time() - 60, '/');
        User::destroyToken();
        session_destroy();
        unset($_SESSION['auth']);
        header("Location: /");
        die();
    }

    public function actionIndexRefresh(): string|false
    {
        $data = json_decode(file_get_contents('php://input'));
        $maxId = $data->maxId;
        $limit = $maxId;
        $users = User::getAllUsersFromStorage($limit);
        $usersData = [];
        if (count($users) > 0) {
            foreach ($users as $user) {
                $usersData[] = $user->getUserDataAsArray();
            }
        }
        header("Content-Type: application/json, charset=utf-8");
        return json_encode($usersData);
    }

    public function actionCheckIfFewerUsers(): string | false
    {
        $idsFromFrontend = json_decode(file_get_contents('php://input'));
        $idsFromBackend = User::getAllIdsFromStorage();
        if (count($idsFromFrontend) > count($idsFromBackend)) {
            $answer = $idsFromBackend;
        } else {
            $answer = [];
        }
        return json_encode($answer);
    }

    public function actionIsAdmin(): string
    {
        return json_encode(User::isAdmin($_SESSION['auth']['id_user']));
    }
}
