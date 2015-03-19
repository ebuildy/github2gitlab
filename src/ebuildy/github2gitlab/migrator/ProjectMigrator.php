<?php

namespace ebuildy\github2gitlab\migrator;


use ebuildy\github2gitlab\DIC;

class ProjectMigrator extends BaseMigrator
{
    public function getGithubProjects()
    {
        return $this->githubClient->organization()->repositories($this->organization);
    }

    public function getGitlabProjectByName($name)
    {
        $projects = $this->gitlabClient->projects->search($name);

        foreach($projects as $project)
        {
            if ($project['name'] === $name)
            {
                return $project;
            }
        }

        return null;
    }

    public function importProject($githubProject, $dry = true)
    {
        $existingGitlabProject = null;

        list($_org, $githubProjectName) = explode('/', str_replace('https://github.com/', '', $githubProject['html_url']));

        $existingGitlabProject = $this->getGitlabProjectByName($githubProjectName);

        if (empty($existingGitlabProject))
        {
            $this->output('[project] Create project "' . $githubProjectName . '"', self::OUTPUT_SUCCESS);

            if (!$dry)
            {
                $cloneURL = str_replace('https://', 'https://' . GITHUB_TOKEN . '@', $githubProject['clone_url']);

                $existingGitlabProject = $this->gitlabClient->projects->create($githubProjectName, [
                    'description'      => $githubProject['description'],
                    'issues_enabled'   => $githubProject['has_issues'],
                    'wiki_enabled'     => $githubProject['has_wiki'],
                    'snippets_enabled' => true,
                    'public'           => !$githubProject['private'],
                    'import_url'       => $cloneURL
                ]);
            }
        }
        else
        {
            $this->output('[project] Existing project "' . $githubProjectName . '"');
        }

        $githubProjectCollaborators = $this->githubClient->repository()->collaborators()->all($this->organization, $githubProjectName);

        foreach($githubProjectCollaborators as $githubProjectCollaborator)
        {
            $gitlabUser = $this->dic->userMigrator->getGitlabUserFromGithub($githubProjectCollaborator, false);

            $this->output("\t" . '[project] Add collaborator "' . $githubProjectCollaborator['login'] . '" to "' . $githubProjectName . '"');

            if (!$dry)
            {
                try
                {
                    $this->gitlabClient->projects->addMember($existingGitlabProject['id'], $gitlabUser['id'], 30);
                }
                catch (\Exception $e)
                {
                    $this->output("\t" . "Already a project member " . $e->getMessage(), self::OUTPUT_ERROR);
                }
            }
        }

        (new LabelMigrator())->run($dry, $existingGitlabProject);

        if ($existingGitlabProject['issues_enabled'])
        {
            (new IssueMigrator($existingGitlabProject))->run($dry);
        }

        //(new PRMigrator($existingGitlabProject))->run($dry);
    }

}