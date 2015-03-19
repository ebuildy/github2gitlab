<?php

namespace ebuildy\github2gitlab\migrator;


class PRMigrator extends BaseMigrator
{
    /**
     * @var array
     */
    private $project;

    public function __construct($project)
    {
        parent::__construct();

        $this->project  = $project;
    }

    public function run($dry = true)
    {
        $this->dry = $dry;

        $githubProjectPRs    = $this->githubClient->pullRequests()->all($this->organization, $this->project['name'], [
            'state' => 'all'
        ]);

        $this->output("\t" . "Found " . count($githubProjectPRs) . " PR");

        foreach($githubProjectPRs as $githubProjectIssue)
        {
            $gitlabMilestoneId = null;

            if ($githubProjectIssue['milestone'] !== null)
            {
                $gitlabMilestoneId = $this->createMilestone($githubProjectIssue['milestone'], $this->project);
            }

            if (!isset($githubProjectIssue['pull_request']) || empty($githubProjectIssue['pull_request']))
            {
                $this->output("\t" . '[PR] Create "' . $githubProjectIssue['title'] . '"');

                if (!$dry)
                {
                    $gitlabAuthor           = $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectIssue['user']);
                    $gitlabAssignee         = empty($githubProjectIssue['assignee']) ? null : $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectIssue['assignee']);
                    $insertedGitlabIssue    = null;

                    $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                    while (empty($insertedGitlabIssue))
                    {
                        try
                        {
                            $insertedGitlabIssue = $this->gitlabClient->merge_requests->create(
                                $this->project['id'],
                                $githubProjectIssue['head']['ref'],
                                $githubProjectIssue['base']['ref'],
                                $githubProjectIssue['title'],
                                empty($gitlabAssignee) ? null : $gitlabAssignee['id'],
                                $this->project['id'],
                                $githubProjectIssue['body']
                            );

                            $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);
                        }
                        catch (\Exception $e)
                        {
                            $this->output("\t" . '"' . $e->getMessage() . '" cannot create MR, adding ' . $gitlabAuthor['name'] . ' as a project member',
                                self::OUTPUT_ERROR);

                            if (strpos($e->getMessage(), 'This merge request already exists') !== false)
                            {
                                break;
                            }

                            try
                            {
                                $this->addProjectMember($gitlabAuthor);
                            }
                            catch (\Exception $e)
                            {
                                $this->output("\t" . '"' . $e->getMessage() . '" Already a project member , try as admin ...',
                                    self::OUTPUT_ERROR);

                                $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
                            }
                        }
                    }
/*
                    if ($githubProjectIssue['state'] !== 'open')
                    {
                        while (true)
                        {
                            try
                            {
                                $this->output("\t" . 'Closing issue');

                                $this->gitlabClient->issues->update($this->project['id'], $insertedGitlabIssue['id'], [
                                    'state_event' => 'close'
                                ]);

                                $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);

                                break;
                            }
                            catch (\Exception $e)
                            {
                                $this->output("\t" . '"' . $e->getMessage() . '" cannot update PR, adding ' . $gitlabAuthor['name'] . ' as a project member',
                                    self::OUTPUT_ERROR);

                                try
                                {
                                    $this->addProjectMember($gitlabAuthor);
                                }
                                catch (\Exception $e)
                                {
                                    $this->output("\t" . '"' . $e->getMessage() . '" Already a project member , try as admin ...',
                                        self::OUTPUT_ERROR);

                                    $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN,
                                        \Gitlab\Client::AUTH_URL_TOKEN);
                                }
                            }
                        }
                    }
*/
                }
            }

            $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

            $githubIssueComments = $this->githubClient->pullRequest()->comments()->all($this->organization, $this->project['name'], $githubProjectIssue['number'], 1, 1000);

            foreach($githubIssueComments as $githubIssueComment)
            {
                $gitlabAuthor           = $this->dic->userMigrator->getGitlabUserFromGithub($githubIssueComment['user']);

                $this->output("\t" . 'Add ' . $gitlabAuthor['name'] . " comments", self::OUTPUT_SUCCESS);

                if (!$dry)
                {
                    $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                    while(true)
                    {
                        try
                        {
                            $this->gitlabClient->merge_requests->addComment($this->project['id'], $insertedGitlabIssue['id'], $githubIssueComment['body']);

                            $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);

                            break;
                        }
                        catch (\Exception $e)
                        {
                            $this->output("\t" . '"' . $e->getMessage() . '" cannot comment MR, adding ' . $gitlabAuthor['name'] . ' as a project member' , self::OUTPUT_ERROR);

                            if (strpos($e->getMessage(), 'SSLRead') !== false)
                            {
                                break;
                            }

                            try
                            {
                                $this->addProjectMember($gitlabAuthor);
                            }
                            catch (\Exception $e)
                            {
                                $this->output("\t" . '"' . $e->getMessage() . '" Already a project member , try as admin ...' , self::OUTPUT_ERROR);

                                $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
                            }
                        }
                    }
                }
            }
        }

        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
    }

    private function addProjectMember($user)
    {
        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

        $this->gitlabClient->projects->addMember($this->project['id'], $user['id'], 30);

        $this->gitlabClient->authenticate($user['token'], \Gitlab\Client::AUTH_URL_TOKEN);
    }
}