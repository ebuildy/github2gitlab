<?php

namespace ebuildy\github2gitlab\migrator;


class ProjectMigrator extends BaseMigrator
{
    public function run($dry = true)
    {
        $githubProjects   = $this->githubClient->organization()->repositories($this->organization);
        $gitlabProjects   = $this->gitlabClient->projects->all(0, 100);

        foreach($githubProjects as $githubProject)
        {
            $existingGitlabProject = null;

            list($_org, $githubProjectName) = explode('/', str_replace('https://github.com/', '', $githubProject['html_url']));

            foreach ($gitlabProjects as $gitlabProject)
            {
                if ($gitlabProject['name'] === $githubProjectName)
                {
                    $existingGitlabProject = $gitlabProject;

                    break;
                }
            }

            if (empty($existingGitlabProject))
            {
                $this->output('[project] Create project "' . $githubProjectName . '"', self::OUTPUT_SUCCESS);

                if (!$dry)
                {
                    $existingGitlabProject = $this->gitlabClient->projects->create($githubProjectName, [
                        'description'      => $githubProject['description'],
                        'issues_enabled'   => $githubProject['has_issues'],
                        'wiki_enabled'     => $githubProject['has_wiki'],
                        'snippets_enabled' => true,
                        'public'           => !$githubProject['private'],
                        'import_url'       => $githubProject['clone_url']
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
                $gitlabUser = $this->usersMap[$githubProjectCollaborator['id']];

                $this->output("\t" . '[project] Add collaborator "' . $githubProjectCollaborator['login'] . '" to "' . $githubProjectName . '"');

                if (!$dry)
                {
                    try
                    {
                        $this->gitlabClient->projects->addMember($existingGitlabProject['id'], $gitlabUser['id'], 30);
                    }
                    catch (\Exception $e)
                    {
                        $this->output("\t" . "Already a project member", self::OUTPUT_ERROR);
                    }
                }
            }

            if ($existingGitlabProject['issues_enabled'])
            {
                $issueMigrator = new IssueMigrator($this->githubClient, $this->gitlabClient, $this->organization, $existingGitlabProject, $this->usersMap);

                $issueMigrator->run($dry);
            }
        }
    }

}