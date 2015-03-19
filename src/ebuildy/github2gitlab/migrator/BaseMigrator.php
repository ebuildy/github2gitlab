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
     * @var bool
     */
    protected $dry = true;

    /**
     * @var array
     */
    protected $gitlabMilestones = null;


    public function __construct()
    {
        $this->dic = DIC::getInstance();

        $this->githubClient = $this->dic->githubClient;
        $this->gitlabClient = $this->dic->gitlabClient;

        $this->organization = $this->dic->organization;
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

    protected function createMilestone($githubMilestone, $project)
    {
        $gitlabMilestone = null;

        if ($this->gitlabMilestones === null)
        {
            $this->gitlabMilestones = $this->gitlabClient->milestones->all($project['id'], 100);
        }

        foreach($this->gitlabMilestones as $_gitlabMilestone)
        {
            if ($_gitlabMilestone['title'] === $githubMilestone['title'])
            {
                $gitlabMilestone = $_gitlabMilestone;

                break;
            }
        }

        if (empty($gitlabMilestone))
        {
            $this->output("\t" . '[milestone] Create "' . $githubMilestone['title'] . '"', self::OUTPUT_SUCCESS);

            if (!$this->dry)
            {
                $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

                $gitlabMilestone = $this->gitlabClient->milestones->create($project['id'],
                    [
                        'title'       => $githubMilestone['title'],
                        'description' => $githubMilestone['description'],
                        'state'       => self::resolveMilestoneState($githubMilestone['state']),
                        'due_date'    => $githubMilestone['due_on']
                    ]);
            }
        }

        return $gitlabMilestone['id'];
    }

    static public function resolveMilestoneState($githubState)
    {
        if ($githubState === 'open')
        {
            return 'active';
        }

        return 'close';
    }
}