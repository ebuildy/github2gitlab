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
     * @param $githubClient
     * @param $gitlabClient
     */
    public function __construct($githubClient, $gitlabClient, $organization)
    {
        $this->githubClient = $githubClient;
        $this->gitlabClient = $gitlabClient;

        $this->organization = $organization;
    }

    protected function output($message)
    {
        echo $message . PHP_EOL;
    }
}