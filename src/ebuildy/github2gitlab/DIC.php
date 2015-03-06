<?php

namespace ebuildy\github2gitlab;


use ebuildy\github2gitlab\migrator\UserMigrator;

class DIC
{
    /**
     * @var UserMigrator
     */
    public $userMigrator;

    static private $instance;

    static public function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new DIC();
        }

        return self::$instance;
    }
}