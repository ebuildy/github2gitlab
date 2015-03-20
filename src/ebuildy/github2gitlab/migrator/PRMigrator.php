<?php

namespace ebuildy\github2gitlab\migrator;


class PRMigrator extends BaseProjectAwareMigrator
{
    public function run($dry = true)
    {
        $this->dry = $dry;

        $githubProjectPRs    = $this->githubClient->pullRequests()->all($this->organization, $this->project['name'], [
            'state'     => 'all',
            'per_page'  => 500
        ]);

        usort($githubProjectPRs, function($a, $b)
        {
            return $a['id'] - $b['id'];
        });

        $this->output("\t" . "Found " . count($githubProjectPRs) . " PR");

        foreach($githubProjectPRs as $githubPR)
        {
            if (!isset($githubPR['pull_request']) || empty($githubPR['pull_request']))
            {
                $this->output("\t" . '[PR] Create "' . $githubPR['title'] . '"');

                if (!$dry)
                {
                    $gitlabMR  = $this->insertMergeRequest($githubPR);

                    $this->addComments($githubPR['number'], $gitlabMR['id']);
                }
            }

            $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
        }

        file_put_contents(ROOT . '/sql/update-mr-' . $this->project['id'] . '.sql', $this->sqlUpdate);

        $this->gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);
    }


    /**
     * @param $githubPR
     * @return mixed|null
     */
    private function insertMergeRequest($githubPR)
    {
        $gitlabMR = null;

        $gitlabAuthor   = $this->dic->userMigrator->getGitlabUserFromGithub($githubPR['user']);
        $gitlabAssignee = empty($githubPR['assignee']) ? null : $this->dic->userMigrator->getGitlabUserFromGithub($githubPR['assignee']);

        $this->gitlabClient->authenticate($gitlabAuthor['token'], \Gitlab\Client::AUTH_URL_TOKEN);

        while (empty($gitlabMR))
        {
            try
            {
                $gitlabMR = $this->gitlabClient->merge_requests->create(
                    $this->project['id'],
                    $githubPR['head']['ref'],
                    $githubPR['base']['ref'],
                    $githubPR['title'],
                    empty($gitlabAssignee) ? null : $gitlabAssignee['id'],
                    $this->project['id'],
                    $githubPR['body']
                );

                /**
                 * Date time
                 */
                $dateCreated = $githubPR['created_at'];
                $dateUpdated = $githubPR['updated_at'];

                $this->sqlUpdate .= 'UPDATE merge_issues SET created_at=\'' . $dateCreated . '\',updated_at=\'' . $dateUpdated . '\',';

                $this->sqlUpdate .= 'iid=' . $githubPR['number'];

                if ($githubPR['milestone'] !== null)
                {
                    $gitlabMilestoneId = $this->createMilestone($githubPR['milestone'], $this->project);

                    $this->sqlUpdate .= ',milestone_id=' . $gitlabMilestoneId;
                }

                if ($githubPR['state'] !== 'open')
                {
                    $this->sqlUpdate .= ',state=\'closed\'';
                }

                $this->sqlUpdate .= ' WHERE id = ' . $gitlabMR['id'] . ';' . PHP_EOL;

                $this->output("\t" . 'Ok!', self::OUTPUT_SUCCESS);
            }
            catch (\Exception $e)
            {
                $this->output("\t" . '"' . $e->getMessage() . '" cannot create MR, adding ' . $gitlabAuthor['name'] . ' as a project member',
                    self::OUTPUT_ERROR);

                if (strpos($e->getMessage(), 'This merge request already exists') !== false)
                {
                    return null;
                }

                $this->addProjectMember($gitlabAuthor);
            }
        }

        return $gitlabMR;
    }

    /**
     * Fetch all Github issue comments and add Gitlab notes.
     *
     * @param integer $githubIssueNumber
     * @param integer $gitlabMRId
     * @throws \Exception
     */
    protected function addComments($githubIssueNumber, $gitlabMRId)
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
                        $this->gitlabClient->merge_requests->addComment($this->project['id'], $gitlabMRId, $githubIssueComment['body']);

                        /**
                         * @todo Cannot get inserted Note Id ;-(
                         */

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