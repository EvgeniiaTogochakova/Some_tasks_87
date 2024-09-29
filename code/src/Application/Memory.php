<?php

namespace Geekbrains\Application1\Application;

class Memory
{
    public function saveMemoryLogInDb(int $memory):void{
        $logSql = "INSERT INTO memory_log(`user_agent`, `log_datetime`, `url`, `memory_volume`) 
            VALUES (:user_agent, :log_datetime, :url, :memory_volume)";


        $handler = Application::$storage->get()->prepare($logSql);
        $handler->execute([
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'log_datetime' => date("Y-m-d H:i:s", $_SERVER['REQUEST_TIME']),
            'url' => $_SERVER['REQUEST_URI'],
            'memory_volume' => $memory
        ]);

    }
}