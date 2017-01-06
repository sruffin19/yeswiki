<?php
public function getUserTablePrefix()
{
    if (isset($this->config['user_table_prefix']) && !empty($this->config['user_table_prefix'])) {
        return $this->config['user_table_prefix'];
    } else {
        return $this->config["table_prefix"];
    }
}

public function LoadUser($name, $password = 0)
{
    return $this->database->loadSingle("select * from " . $this->getUserTablePrefix() . "users where name = '" . $this->database->escapeString($name) . "' " . ($password === 0 ? "" : "and password = '" . $this->database->escapeString($password) . "'") . " limit 1");
}

public function LoadUsers()
{
    return $this->database->loadAll("select * from " . $this->getUserTablePrefix() . "users order by name");
}
