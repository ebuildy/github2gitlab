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
                    $this->output("\t" . '[milestone] Create "' . $githubProjectIssue['milestone']['title'] . '""');

                    if (!$dry)
                    {
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

            $this->output("\t" . '[issue] Create "' . $githubProjectIssue['title'] . '""');

            if (!$dry)
            {
                $insertedGitlabIssue = $this->gitlabClient->issues->create($this->project['id'], [
                    'title'         => $githubProjectIssue['title'],
                    'description'   => $githubProjectIssue['body'],
                    'assignee_id'   => $this->usersMap[$githubProjectIssue['assignee']['id']],
                    'milestone_id'  => $gitlabMilestoneId,
                    'labels'        => $labels
                ]);

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
                $this->gitlabClient->issues->addComment($this->project['id'], $insertedGitlabIssue['id'], $githubIssueComment['body']);
            }
        }
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