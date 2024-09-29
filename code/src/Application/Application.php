<?php

namespace Geekbrains\Application1\Application;

use Geekbrains\Application1\Domain\Controllers\AbstractController;
use Geekbrains\Application1\Domain\Models\User;
use Geekbrains\Application1\Infrastructure\Config;
use Geekbrains\Application1\Infrastructure\Storage;
use Geekbrains\Application1\Application\Auth;
use Geekbrains\Application1\Application\Memory;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Handler\FirePHPHandler;

class Application
{

    private const APP_NAMESPACE = 'Geekbrains\Application1\Domain\Controllers\\';

    private string $controllerName;
    private string $methodName;

    public static Config $config;

    public static Storage $storage;

    public static Auth $auth;
    public static Logger $logger;
    public static Memory $memory;

    public function __construct()
    {
        Application::$config = new Config();
        Application::$storage = new Storage();
        Application::$auth = new Auth();
        Application::$logger = new Logger('application_logger');
        Application::$logger->pushHandler(
            new StreamHandler(
                $_SERVER['DOCUMENT_ROOT'] . "/../log/" . Application::$config->get()['log']['LOGS_FILE']
                . "-" . date("Y-m-d") . ".log",
                Level::Debug
            )
        );
        Application::$logger->pushHandler(new FirePHPHandler());
        Application::$memory = new Memory();

    }

    public function runApp(): string
    {
        $memory_start = memory_get_usage();

        $result = $this->run();

        $memory_end = memory_get_usage();

        if (Application::$config->get()['log']['DB_MEMORY_LOG']) {
            Application::$memory->saveMemoryLogInDb($memory_end - $memory_start);
        }
        return $result;

    }

    public function run(): string
    {
        session_start();
        Application::$auth->restorePreviousSessionData();

        $routeArray = explode('/', $_SERVER['REQUEST_URI']);

        if (isset($routeArray[1]) && $routeArray[1] != '') {
            $controllerName = $routeArray[1];
        } else {
            $controllerName = "page";
        }

        $this->controllerName = Application::APP_NAMESPACE . ucfirst($controllerName) . "Controller";

        if (class_exists($this->controllerName)) {
            if (isset($routeArray[2]) && $routeArray[2] != '') {
                $methodName = $routeArray[2];
            } else {
                $methodName = "index";
            }

            $this->methodName = "action" . ucfirst($methodName);

            if (method_exists($this->controllerName, $this->methodName)) {
                $controllerInstance = new $this->controllerName();

                if ($controllerInstance instanceof AbstractController) {
                    if ($this->checkAccessToMethod($controllerInstance, $this->methodName)) {
                        return call_user_func_array(
                            [$controllerInstance, $this->methodName],
                            []
                        );
                    } else {
                        $logMessage = "Попытка вызова адреса " . $_SERVER['REQUEST_URI'] . " не администратором";
                        $logMessage = $this->userToBlame($logMessage);
                        Application::$logger->error($logMessage);
                        return "У вас нет доступа к этому методу";
                    }
                } else {
                    return call_user_func_array(
                        [$controllerInstance, $this->methodName],
                        []
                    );
                }
            } else {
                $logMessage = "Метод " . $this->methodName . " не существует в контроллере " . $this->controllerName . " | ";
                $logMessage .= "Попытка вызова адреса " . $_SERVER['REQUEST_URI'];
                $logMessage = $this->userToBlame($logMessage);
                Application::$logger->error($logMessage);
                return "Метод не существует";
            }
        } else {
            $logMessage = "Класс " . $this->controllerName . " не существует | ";
            $logMessage .= "Попытка вызова адреса " . $_SERVER['REQUEST_URI'];
            $logMessage = $this->userToBlame($logMessage);
            Application::$logger->error($logMessage);
            return "Класс $this->controllerName не существует";
        }
    }

    private function checkAccessToMethod(AbstractController $controllerInstance, string $methodName): bool
    {
        //        $userRoles = $controllerInstance->getUserRoles();
        $userRoles = User::getUserRoles();


        $rules = $controllerInstance->getActionsPermissions($methodName);

        if (count($rules) < 1) {
            $rules[] = 'user';
        }

        $isAllowed = false;

        if (in_array($rules[0], $userRoles)) {
            $isAllowed = true;
        }

        return $isAllowed;
    }

    private function userToBlame($logMessage): string
    {
        if (isset($_SESSION['auth']['id_user'])) {
            $logMessage .= " id " . $_SESSION['auth']['id_user'];
        }
        return $logMessage;
    }
}
