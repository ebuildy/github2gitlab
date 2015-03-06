<?php

namespace ebuildy\github2gitlab\migrator;


class TeamMigrator extends BaseMigrator
{
    public function run($dry = true)
    {
        $githubTeams    = $this->githubClient->organization()->teams()->all($this->organization);
        $gitlabGroups   = $this->gitlabClient->groups->all(1, 100);

        foreach($githubTeams as $githubTeam)
        {
            $existingGroup = null;

            foreach($gitlabGroups as $gitlabGroup)
            {
                if ($gitlabGroup['name'] === $githubTeam['name'])
                {
                    $existingGroup = $gitlabGroup;

                    break;
                }
            }

            if (empty($existingGroup))
            {
                $this->output('[Group] Insert ' . $githubTeam['name']);

                if (!$dry)
                {
                    $existingGroup = $this->gitlabClient->groups->create($githubTeam['name'], $githubTeam['slug'],
                        $githubTeam['description']);
                }
            }

            $githubMembers = $this->githubClient->organization()->teams()->members($githubTeam['id']);

            foreach($githubMembers as $githubMember)
            {
                $gitlabUser  = $this->dic->userMigrator->getGitlabUserFromGithub($githubMember);

                $this->output('[Group] Add member ' . $gitlabUser['name'] . ' to group ' . $githubTeam['name']);

                if (!$dry)
                {
                    try
                    {
                        $this->gitlabClient->groups->addMember($existingGroup['id'], $gitlabUser['id'], 30);
                    }
                    catch (\Exception $e)
                    {

                    }
                }
            }
        }
    }
}