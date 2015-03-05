<?php

namespace ebuildy\github2gitlab\migrator;

class BaseMigrator
{
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


    /**
     * @var array
     */
    protected $usersMap;


    /**
     * @param \Github\Client $githubClient
     * @param \Gitlab\Client $gitlabClient
     * @param string $organization
     */
    public function __construct($githubClient, $gitlabClient, $organization)
    {
        $this->githubClient = $githubClient;
        $this->gitlabClient = $gitlabClient;

        $this->organization = $organization;
    }

    public function setUsersMap($usersMap)
    {
        $this->usersMap = $usersMap;

        return $this;
    }

    protected function output($message)
    {
        echo $message . PHP_EOL;
    }
}