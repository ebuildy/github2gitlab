<?php

namespace ebuildy\github2gitlab;

use ebuildy\github2gitlab\migrator\BaseMigrator;
use ebuildy\github2gitlab\migrator\ProjectMigrator;
use ebuildy\github2gitlab\migrator\TeamMigrator;
use ebuildy\github2gitlab\migrator\UserMigrator;

class Migrator extends BaseMigrator
{
    public function run($dry = true)
    {
        $dic = DIC::getInstance();

        $dic->userMigrator = new UserMigrator();

        $dic->userMigrator->run($dry);

        $teamMigrator = new TeamMigrator();

      // $teamMigrator->run($dry);

        $projectMigrator = new ProjectMigrator();

        foreach($projectMigrator->getGithubProjects() as $githubProject)
        {
            list($_org, $githubProjectName) = explode('/', str_replace('https://github.com/', '', $githubProject['html_url']));

            if ($githubProjectName !== 'Admin')
            {
                $projectMigrator->importProject($githubProject, $dry);
            }
        }
    }

}