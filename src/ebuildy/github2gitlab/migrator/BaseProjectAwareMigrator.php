<?php

namespace ebuildy\github2gitlab\migrator;


abstract class BaseProjectAwareMigrator extends BaseMigrator
{
    /**
     * @var array
     */
    protected $project;

    /**
     * @var string
     */
    protected $sqlUpdate;

    public function __construct($project)
    {
        parent::__construct();

        $this->project      = $project;
        $this->sqlUpdate    = '';
    }


    protected function addProjectMember($user)
    {
        try
        {
            $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

            $this->gitlabClient->projects->addMember($this->project['id'], $user['id'], 30);

            $this->gitlabClient->authenticate($user['token'], \Gitlab\Client::AUTH_URL_TOKEN);
        }
        catch (\Exception $e)
        {
            $this->output("\t" . '"' . $e->getMessage() . '" Already a project member , try as admin ...',
                self::OUTPUT_ERROR);

            $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
        }
    }
}