<?php

namespace ebuildy\github2gitlab\migrator;


class IssueMigrator extends BaseMigrator
{
    /**
     * @var array
     */
    private $project;

    public function __construct($githubClient, $gitlabClient, $organization, $project)
    {
        parent::__construct($githubClient, $gitlabClient, $organization);

        $this->project  = $project;
    }

    public function run($dry = true)
    {
        $this->dry = $dry;

        $page = 1;

        $githubProjectIssues = array();

        do
        {
            $buffer = $this->githubClient->issues()->all($this->organization, $this->project['name'], [
                'state' => 'all',
                'page'  => $page++
            ]);

            $githubProjectIssues = array_merge($githubProjectIssues, $buffer);
        }
        while (count($buffer) > 0);

        $this->output("\t" . "Found " . count($githubProjectIssues) . " issues");

        foreach($githubProjectIssues as $githubProjectIssue)
        {
            if (isset($githubProjectIssue['pull_request']) && empty($githubProjectIssue['pull_request']))
            {
                continue;
            }

            $gitlabMilestoneId = null;

            if ($githubProjectIssue['milestone'] !== null)
            {
                $gitlabMilestoneId = $this->createMilestone($githubProjectIssue['milestone'], $this->project);
            }

            $labels = '';

            foreach($githubProjectIssue['labels'] as $githubLabel)
            {
                $labels .= $githubLabel['name'] . ',';
            }

            $labels = trim($labels, ',');

            $this->output("\t" . '[issue] Create "' . $githubProjectIssue['title'] . '"');

            if (!$dry)
            {
                $gitlabAuthor = $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectIssue['user']);
                $gitlabAssignee = empty($githubProjectIssue['assignee']) ? null : $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectIssue['assignee']);
                $insertedGitlabIssue = null;

                $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                while (empty($insertedGitlabIssue))
                {
                    try
                    {
                        $insertedGitlabIssue = $this->gitlabClient->issues->create($this->project['id'], [
                            'title'        => $githubProjectIssue['title'],
                            'description'  => $githubProjectIssue['body'],
                            'assignee_id'  => empty($gitlabAssignee) ? null : $gitlabAssignee['id'],
                            'milestone_id' => $gitlabMilestoneId,
                            'labels'       => $labels
                        ]);

                        $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);
                    }
                    catch (\Exception $e)
                    {
                        $this->output("\t" . '"' . $e->getMessage() . '" cannot create issue, adding ' . $gitlabAuthor['name'] . ' as a project member',
                            self::OUTPUT_ERROR);

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
                            $this->output("\t" . '"' . $e->getMessage() . '" cannot update issue, adding ' . $gitlabAuthor['name'] . ' as a project member',
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
            }

            $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

            $githubIssueComments = $this->githubClient->issue()->comments()->all($this->organization, $this->project['name'], $githubProjectIssue['number'], 1, 1000);

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
                            $this->gitlabClient->issues->addComment($this->project['id'], $insertedGitlabIssue['id'], $githubIssueComment['body']);

                            $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);

                            break;
                        }
                        catch (\Exception $e)
                        {
                            $this->output("\t" . '"' . $e->getMessage() . '" cannot comment issue, adding ' . $gitlabAuthor['name'] . ' as a project member' , self::OUTPUT_ERROR);

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