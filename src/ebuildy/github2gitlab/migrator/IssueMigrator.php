<?php

namespace ebuildy\github2gitlab\migrator;


class ProjectAwareMigrator extends BaseProjectAwareMigrator
{
    public function run($dry = true)
    {
        $this->dry = $dry;

        $page = 1;

        $githubProjectIssues = [];

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

        usort($githubProjectIssues, function($a, $b)
        {
            return $a['id'] - $b['id'];
        });

        $sqlUpdateIssues = '';

        foreach($githubProjectIssues as $githubProjectIssue)
        {
            if (isset($githubProjectIssue['pull_request']) && empty($githubProjectIssue['pull_request']))
            {
                continue;
            }

            /**
             * Milestone
             */
                $gitlabMilestoneId = null;

                if ($githubProjectIssue['milestone'] !== null)
                {
                    $gitlabMilestoneId = $this->createMilestone($githubProjectIssue['milestone'], $this->project);
                }

            /**
             * Labels
             */
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

                        /**
                         * Date time
                         */
                        $dateCreated = $githubProjectIssue['created_at'];
                        $dateUpdated = $githubProjectIssue['updated_at'];

                        $sqlUpdateIssues .= 'UPDATE issues SET created_at = \'' . $dateCreated . '\', updated_at = \'' . $dateUpdated . '\' ' .
                            'WHERE id = ' . $insertedGitlabIssue['id'] . ';' .
                            PHP_EOL;

                        $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);
                    }
                    catch (\Exception $e)
                    {
                        $this->output("\t" . '"' . $e->getMessage() . '" cannot create issue, adding ' . $gitlabAuthor['name'] . ' as a project member',
                            self::OUTPUT_ERROR);

                        $this->addProjectMember($gitlabAuthor);
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

                            $this->addProjectMember($gitlabAuthor);
                        }
                    }
                }
            }


        }

        file_put_contents(ROOT . '/sql/update-issues-' . $this->project['id'] . '.sql', $sqlUpdateIssues);

        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
    }

    /**
     * Fetch all Github issue comments and add Gitlab notes.
     *
     * @param integer $githubIssueNumber
     * @param integer $gitlabIssueId
     * @throws \Exception
     */
    protected function addNotesFromIssue($githubIssueNumber, $gitlabIssueId)
    {
        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

        $githubIssueComments = $this->githubClient->issue()->comments()->all($this->organization, $this->project['name'], $githubIssueNumber, 1, 1000);

        foreach($githubIssueComments as $githubIssueComment)
        {
            $gitlabAuthor           = $this->dic->userMigrator->getGitlabUserFromGithub($githubIssueComment['user']);

            $this->output("\t" . 'Add ' . $gitlabAuthor['name'] . " comments", self::OUTPUT_SUCCESS);

            if (!$this->dry)
            {
                $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

                while(true)
                {
                    try
                    {
                        $insertedGitlabNote = $this->gitlabClient->issues->addComment($this->project['id'], $gitlabIssueId, $githubIssueComment['body']);

                        /**
                         * Date time
                         */
                        $dateCreated = $githubIssueComment['created_at'];
                        $dateUpdated = $githubIssueComment['updated_at'];

                        $this->sqlUpdate .= 'UPDATE notes SET created_at = \'' . $dateCreated . '\', updated_at = \'' . $dateUpdated . '\' ' .
                            'WHERE id = ' . $insertedGitlabNote['id'] . ';' .
                            PHP_EOL;

                        $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);

                        break;
                    }
                    catch (\Exception $e)
                    {
                        $this->output("\t" . '"' . $e->getMessage() . '" cannot comment issue, adding ' . $gitlabAuthor['name'] . ' as a project member' , self::OUTPUT_ERROR);

                        $this->addProjectMember($gitlabAuthor);
                    }
                }
            }
        }
    }
}