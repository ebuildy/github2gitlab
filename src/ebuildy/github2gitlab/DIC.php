<?php

namespace ebuildy\github2gitlab;


use ebuildy\github2gitlab\migrator\UserMigrator;

class DIC
{
    /**
     * @var UserMigrator
     */
    public $userMigrator;

    /**
     * @var \Github\Client
     */
    public $githubClient;

    /**
     * @var \Gitlab\Client
     */
    public $gitlabClient;

    /**
     * @var String
     */
    public $organization;

    public function init($githubClient, $gitlabClient, $organisation)
    {
        $this->gitlabClient = $gitlabClient;
        $this->githubClient = $githubClient;
        $this->organization = $organisation;
    }

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