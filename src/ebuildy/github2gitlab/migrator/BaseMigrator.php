<?php

namespace ebuildy\github2gitlab\migrator;

use ebuildy\github2gitlab\DIC;

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
     * @var DIC
     */
    public $dic;


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

        $this->dic = DIC::getInstance();
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

            if ($type === self::OUTPUT_ERROR)
            {
                $message = "\033[31m" . $message;
            }
            elseif ($type === self::OUTPUT_SUCCESS)
            {
                $message = "\033[36m" . $message;
            }

            echo $message . "\033[0m";
        }

        echo PHP_EOL;
    }
}