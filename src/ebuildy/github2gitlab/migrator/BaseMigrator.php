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

    const OUTPUT_ERROR = 'error';
    const OUTPUT_SUCCESS = 'success';

    protected function output($message, $type = null)
    {
        if (!empty($message))
        {
            if ($message[0] !== "\t")
            {
                echo date("H:i") . ' > ';
            }

            if ($type === OUTPUT_ERROR)
            {
                echo "\033[43m";
            }
            elseif ($type === OUTPUT_SUCCESS)
            {
                echo "\033[42m";
            }

            echo $message;

            echo "\033[0m";
        }

        echo PHP_EOL;
    }
}