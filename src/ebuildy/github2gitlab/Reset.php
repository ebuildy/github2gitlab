<?php

namespace ebuildy\github2gitlab;

use ebuildy\github2gitlab\migrator\BaseMigrator;

class Reset extends BaseMigrator
{
    public function run()
    {
        $this->projects();

        $this->users();
    }

    private function users()
    {
        $users = $this->gitlabClient->users->all(null, 1, 100);

        foreach($users as $user)
        {
            if ($user['name'] !== 'Administrator')
            {
                $this->output('Remove user "' . $user['name'] . '"');

                $this->gitlabClient->users->remove($user['id']);
            }
        }
    }

    private function projects()
    {
        $projects = $this->gitlabClient->projects->all(1, 100);

        foreach($projects as $project)
        {
            $this->output('Remove project "' . $project['name'] . '"');

            $this->gitlabClient->projects->remove($project['id']);
        }
    }
}