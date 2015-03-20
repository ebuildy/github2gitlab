<?php

namespace ebuildy\github2gitlab\migrator;


class ProjectCollaboratorMigrator extends BaseProjectAwareMigrator
{
    /**
     * @param bool $dry
     * @throws \Exception
     */
    public function run($dry = true)
    {
        $githubProjectCollaborators = $this->githubClient->repository()->collaborators()->all($this->organization, $this->project['name']);

        foreach($githubProjectCollaborators as $githubProjectCollaborator)
        {
            $gitlabUser = $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectCollaborator, false);

            $this->output("\t" . '[project] Add collaborator "' . $githubProjectCollaborator['login'] . '" to "' . $this->project['name'] . '"');

            if (!$dry)
            {
                try
                {
                    $this->gitlabClient->projects->addMember($this->project['id'], $gitlabUser['id'], 30);
                }
                catch (\Exception $e)
                {
                    $this->output("\t" . "Already a project member " . $e->getMessage(), self::OUTPUT_ERROR);
                }
            }
        }
    }
}