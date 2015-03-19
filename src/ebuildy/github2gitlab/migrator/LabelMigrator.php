<?php

namespace ebuildy\github2gitlab\migrator;


class LabelMigrator extends BaseMigrator
{
    public function run($dry = true, $project)
    {
        $githubLabels = $this->githubClient->repository()->labels()->all($this->organization, $project['name']);

        foreach($githubLabels as $githubLabel)
        {
            $this->output("\t" . "Add label " . $githubLabel['name'], self::OUTPUT_SUCCESS);

            try
            {
                $this->gitlabClient->projects->addLabel($project['id'], [
                    'name'  => $githubLabel['name'],
                    'color' => '#' . $githubLabel['color']
                ]);
            }
            catch(\Exception $e)
            {
                $this->output($e->getMessage(), BaseMigrator::OUTPUT_ERROR);
            }
        }
    }
}