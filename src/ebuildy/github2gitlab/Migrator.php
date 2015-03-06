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

        $dic->userMigrator = new UserMigrator($this->githubClient, $this->gitlabClient, $this->organization);

        $dic->userMigrator->run($dry);

        $teamMigrator = new TeamMigrator($this->githubClient, $this->gitlabClient, $this->organization);

        $teamMigrator->run($dry);

        $projectMigrator    = new ProjectMigrator($this->githubClient, $this->gitlabClient, $this->organization);

        $projectMigrator->run($dry);
    }

}