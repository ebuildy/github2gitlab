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
        $githubProjectIssues    = $this->githubClient->issues()->all($this->organization, $this->project['name']);
        $gitlabMilestones       = $this->gitlabClient->milestones->all($this->project['id'], 100);

        foreach($githubProjectIssues as $githubProjectIssue)
        {
            $gitlabMilestoneId = null;

            if ($githubProjectIssue['milestone'] !== null)
            {
                $gitlabMilestone = null;

                foreach($gitlabMilestones as $_gitlabMilestone)
                {
                    if ($_gitlabMilestone['title'] === $githubProjectIssue['milestone']['title'])
                    {
                        $gitlabMilestone = $_gitlabMilestone;

                        break;
                    }
                }

                if (empty($gitlabMilestone))
                {
                    $this->output("\t" . '[milestone] Create "' . $githubProjectIssue['milestone']['title'] . '"', self::OUTPUT_SUCCESS);

                    if (!$dry)
                    {
                        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

                        $gitlabMilestone = $this->gitlabClient->milestones->create($this->project['id'],
                            [
                                'title'       => $githubProjectIssue['milestone']['title'],
                                'description' => $githubProjectIssue['milestone']['description'],
                                'state'       => self::resolveMilestoneState($githubProjectIssue['milestone']['state']),
                                'due_date'    => $githubProjectIssue['milestone']['due_on']
                            ]);
                    }
                }

                $gitlabMilestoneId = $gitlabMilestone['id'];
            }

            $labels = '';

            foreach($githubProjectIssue['labels'] as $githubLabel)
            {
                $labels .= $githubLabel['name'] . ',';
            }

            $labels = trim($labels, ',');
//var_dump($githubProjectIssue);die();
            $this->output("\t" . '[issue] Create "' . $githubProjectIssue['title'] . '"');

            if (!$dry)
            {
                $gitlabAuthor           = $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectIssue['user']);
                $gitlabAssignee         = empty($githubProjectIssue['assignee']) ? null : $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectIssue['assignee']);
                $insertedGitlabIssue    = null;

                $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                while(empty($insertedGitlabIssue))
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
                        $this->output("\t" . '"' . $e->getMessage() . '" cannot create issue, adding ' . $gitlabAuthor['name'] . ' as a project member' , self::OUTPUT_ERROR);

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

                if ($githubProjectIssue['state'] !== 'open')
                {
                    while(true)
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
                            $this->output("\t" . '"' . $e->getMessage() . '" cannot update issue, adding ' . $gitlabAuthor['name'] . ' as a project member' , self::OUTPUT_ERROR);

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

    static public function resolveMilestoneState($githubState)
    {
        if ($githubState === 'open')
        {
            return 'active';
        }

        return 'close';
    }
}