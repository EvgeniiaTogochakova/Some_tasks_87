<?php

namespace Geekbrains\Application1\Domain\Models;

use Geekbrains\Application1\Application\Application;
use Geekbrains\Application1\Infrastructure\Storage;

class User
{

    private ?int $idUser;
    private ?string $userName;

    private ?string $userLastName;
    private ?int $userBirthday;
    private ?string $login;

    private ?string $userPasswordHash;

    private static string $storageAddress = '/storage/birthdays.txt';

    public function __construct(
        int    $idUser = null,
        string $login = null,
        string $name = null,
        string $lastName = null,
        int    $birthday = null,
        string $rawPassword = null
    )
    {
        $this->idUser = $idUser;
        $this->login = $login;
        $this->userName = $name;
        $this->userLastName = $lastName;
        $this->userBirthday = $birthday;
        if (!empty($rawPassword)) {
            $this->userPasswordHash = $this->createPasswordHash($rawPassword);
        } else $this->userPasswordHash = null;
    }

    public function createPasswordHash(string $rawPassword): string
    {
        return password_hash($rawPassword, PASSWORD_BCRYPT);
    }

    public function getPasswordHash(): string
    {

        return $this->userPasswordHash;
    }

    public function setName(string $userName): void
    {
        $this->userName = $userName;
    }

    public function setUserId(int $idUser): void
    {
        $this->idUser = $idUser;
    }

    public function setLastName(string $userLastName): void
    {
        $this->userLastName = $userLastName;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getUserLogin(): string
    {
        return $this->login;
    }


    // public function getUserId(): string
    public function getUserId(): ?int
    {
        return $this->idUser;
    }

    public function getUserLastName(): string
    {
        return $this->userLastName;
    }

    public function getUserBirthday(): ?int
    {
        return $this->userBirthday;
    }

    public function setBirthdayFromString(string $birthdayString): void
    {
        $this->userBirthday = strtotime($birthdayString);
    }

    public static function getAllUsersFromStorage(?int $limit = null): array
    {
        $sql = "SELECT * FROM users";

        if (isset($limit) && $limit > 0) {
            $sql .= " WHERE id_user > " . (int)$limit;
        }

        $handler = Application::$storage->get()->prepare($sql);
        $handler->execute();
        $result = $handler->fetchAll();

        $users = [];

        foreach ($result as $item) {
            $user = new User($item['id_user'], $item['login'], $item['user_name'], $item['user_lastname'], $item['user_birthday_timestamp']);
            $users[] = $user;
        }

        return $users;
    }

    public static function getAllIdsFromStorage(): array
    {
        $users = User::getAllUsersFromStorage();
        $userIds = [];
        foreach ($users as $user) {
            $id = $user->getUserId();
            $userIds[] = $id;
        }
        return $userIds;
    }

    public static function validateRequestData(): bool
    {
        $result = true;

        if (!(
            isset($_POST['name']) && !empty($_POST['name']) &&
            isset($_POST['lastname']) && !empty($_POST['lastname']) &&
            isset($_POST['birthday']) && !empty($_POST['birthday']) &&
            isset($_POST['login']) && !empty($_POST['login']) &&
            isset($_POST['rawPassword']) && !empty($_POST['rawPassword']))) {
            $result = false;
        }

        if (preg_match('/<([^>]+)>/', $_POST['name']) || preg_match('/<([^>]+)>/', $_POST['lastname'])) {
            $result = false;
        }


        if (!preg_match('/^(\d{2}-\d{2}-\d{4})$/', $_POST['birthday'])) {
            $result = false;
        }

        if (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^\s\w\d])(^\S{8,16})$/', $_POST['rawPassword'])) {
            $result = false;
        }

        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] != $_POST['csrf_token']) {
            $result = false;
        }

        return $result;
    }

    public function setParamsFromRequestData(): void
    {
        $this->userName = htmlspecialchars($_POST['name']);
        $this->userLastName = htmlspecialchars($_POST['lastname']);
        $this->login = htmlspecialchars($_POST['login']);
        $this->userPasswordHash = $this->createPasswordHash($_POST['rawPassword']);
        $this->setBirthdayFromString($_POST['birthday']);
    }

    public static function getUserDataByID(int $userID): array
    {
        $userSql = "SELECT * FROM users WHERE id_user = :id";


        $handler = Application::$storage->get()->prepare($userSql);
        $handler->execute(['id' => $userID]);
        return $handler->fetch();
    }

    public static function exists(int $id): bool
    {
        $sql = "SELECT count(id_user) as user_count FROM users WHERE id_user = :id_user";

        $handler = Application::$storage->get()->prepare($sql);
        $handler->execute([
            'id_user' => $id
        ]);

        $result = $handler->fetchAll();

        if (count($result) > 0 && $result[0]['user_count'] > 0) {
            return true;
        }
        return false;

    }

    public function updateUser(array $userDataArray): void
    {
        $sql = "UPDATE users SET ";

        $counter = 0;
        foreach ($userDataArray as $key => $value) {
            $sql .= $key . " = :" . $key;

            if ($counter != count($userDataArray) - 1) {
                $sql .= ",";
            }

            $counter++;
        }
        $sql .= " WHERE id_user = " . $this->getUserId();


        $handler = Application::$storage->get()->prepare($sql);
        $handler->execute($userDataArray);
    }

    public static function destroyToken(): array
    {
        $userSql = "UPDATE users SET token = :token WHERE id_user = :id";

        $handler = Application::$storage->get()->prepare($userSql);
        $handler->execute(['token' => bin2hex(random_bytes(16)), 'id' => $_SESSION['auth']['id_user']]);
        $result = $handler->fetchAll();

        return $result[0] ?? [];
    }

    public static function verifyToken(string $token): array
    {
        $userSql = "SELECT * FROM users WHERE token = :token";

        $handler = Application::$storage->get()->prepare($userSql);
        $handler->execute(['token' => $token]);
        $result = $handler->fetchAll();

        return $result[0] ?? [];
    }

    public static function setToken(int $userID, string $token): void
    {
        $userSql = "UPDATE users SET token = :token WHERE id_user = :id";


        $handler = Application::$storage->get()->prepare($userSql);
        $handler->execute(['id' => $userID, 'token' => $token]);
    }

    public static function getTokenFromStorageById(int $userID): string
    {
        $userSql = "SELECT * from users WHERE id_user = :id";
        $handler = Application::$storage->get()->prepare($userSql);
        $handler->execute(['id' => $userID]);
        $result = $handler->fetch();
        if (!empty($result)) {
            return $result['token'];
        } else {
            return '';
        }
    }

    public static function deleteFromStorage(int $user_id): void
    {
        $sql = "DELETE FROM users WHERE id_user = :id_user";

        $handler = Application::$storage->get()->prepare($sql);
        $handler->execute(['id_user' => $user_id]);
    }

    public function saveToStorage(): void
    {
        $sql = "INSERT INTO users(`login`, user_name, user_lastname, user_birthday_timestamp, password_hash) 
                VALUES (:user_login, :user_name, :user_lastname, :user_birthday, :password_hash)";

        $handler = Application::$storage->get()->prepare($sql);
        $handler->execute([
            'user_login' => $this->login,
            'user_name' => $this->userName,
            'user_lastname' => $this->userLastName,
            'user_birthday' => $this->userBirthday,
            'password_hash' => $this->userPasswordHash
        ]);
    }

    public static function getUserRoles(): array
    {
        $roles = [];
        $roles[] = 'user';

        if (isset($_SESSION['auth']['id_user'])) {
            $rolesSql = "SELECT * FROM user_roles WHERE id_user = :id";

            $handler = Application::$storage->get()->prepare($rolesSql);
            $handler->execute(['id' => $_SESSION['auth']['id_user']]);
            $result = $handler->fetchAll();

            if (!empty($result)) {
                foreach ($result as $role) {
                    $roles[] = $role['role'];
                }
            }
        }

        return $roles;
    }

    public function getUserDataAsArray(): array
    {
        $userArray = [
            'id' => $this->idUser,
            'username' => $this->userName,
            'userlastname' => $this->userLastName,
            'userbirthday' => date('d.m.Y', $this->userBirthday),
        ];
        return $userArray;
    }

    public static function isAdmin(?int $idUser): bool
    {
        return in_array('admin', self::getUserRoles());
    }
}
