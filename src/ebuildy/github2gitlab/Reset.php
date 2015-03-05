<?php

namespace ebuildy\github2gitlab;

use ebuildy\github2gitlab\migrator\BaseMigrator;

class Reset extends BaseMigrator
{
    public function run()
    {
        $projects = $this->gitlabClient->projects->all(1, 100);

        foreach($projects as $project)
        {
            $this->output('Remove project "' . $project['name'] . '"');

            $this->gitlabClient->projects->remove($project['id']);
        }
    }
}