<?php

namespace ebuildy\github2gitlab\migrator;


class ProjectLabelMigrator extends BaseProjectAwareMigrator
{
    public function run($dry = true)
    {
        $githubLabels = $this->githubClient->repository()->labels()->all($this->organization, $this->project['name']);

        foreach($githubLabels as $githubLabel)
        {
            $this->output("\t" . "Add label " . $githubLabel['name'], self::OUTPUT_SUCCESS);

            if (!$dry)
            {
                try
                {
                    $this->gitlabClient->projects->addLabel($this->project['id'], [
                        'name'  => $githubLabel['name'],
                        'color' => '#' . $githubLabel['color']
                    ]);
                }
                catch (\Exception $e)
                {
                    $this->output($e->getMessage(), BaseMigrator::OUTPUT_ERROR);
                }
            }
        }
    }
}