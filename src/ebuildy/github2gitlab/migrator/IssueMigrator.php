<?php

namespace ebuildy\github2gitlab\migrator;


class IssueMigrator extends BaseMigrator
{
    /**
     * @var array
     */
    private $project;

    public function __construct($githubClient, $gitlabClient, $organization, $project, $userMap)
    {
        parent::__construct($githubClient, $gitlabClient, $organization);

        $this->project  = $project;
        $this->usersMap = $userMap;
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
                $labels .= $githubLabel['title'] . ',';
            }

            $labels = trim($labels, ',');
//var_dump($githubProjectIssue);die();
            $this->output("\t" . '[issue] Create "' . $githubProjectIssue['title'] . '"');

            if (!$dry)
            {
                $gitlabAuthor           = $this->usersMap[$githubProjectIssue['user']['id']];
                $insertedGitlabIssue    = null;

                $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                while(empty($insertedGitlabIssue))
                {
                    try
                    {
                        $insertedGitlabIssue = $this->gitlabClient->issues->create($this->project['id'], [
                            'title'        => $githubProjectIssue['title'],
                            'description'  => $githubProjectIssue['body'],
                            'assignee_id'  => $this->usersMap[$githubProjectIssue['assignee']['id']]['id'],
                            'milestone_id' => $gitlabMilestoneId,
                            'labels'       => $labels
                        ]);
                    }
                    catch (\Exception $e)
                    {
                        $this->output("\t" . '"' . $e->getMessage() . '" cannot create issue!', self::OUTPUT_ERROR);

                        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
                    }
                }

                if ($githubProjectIssue['state'] !== 'open')
                {
                    $this->gitlabClient->issues->update($this->project['id'], $insertedGitlabIssue['id'], [
                        'state_event' => 'close'
                    ]);
                }
            }

            $githubIssueComments = $this->githubClient->issue()->comments()->all($this->organization, $this->project['name'], $githubProjectIssue['number'], 1, 1000);

            foreach($githubIssueComments as $githubIssueComment)
            {
                $gitlabAuthor = $this->usersMap[$githubIssueComment['user']['id']];

                $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                $this->gitlabClient->issues->addComment($this->project['id'], $insertedGitlabIssue['id'], $githubIssueComment['body']);
            }
        }

        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
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