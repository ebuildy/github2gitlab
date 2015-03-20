<?php

namespace ebuildy\github2gitlab\migrator;


class ProjectWikiMigrator extends BaseProjectAwareMigrator
{
    public function run()
    {
        $projectSlug        = $this->project['name'];
        $githubWikiUrl      = 'https://' . GITHUB_TOKEN . '@github.com/' . $this->organization . '/' . $projectSlug . '.wiki.git';
        $gitlabWikiUrl      = substr($this->project['ssh_url_to_repo'], 0, -4) . '.wiki.git';

        $commands = [
            'cd ' . ROOT . '/wiki',
            'rm -rf ' . $projectSlug . '.wiki',
            'git clone "' . $githubWikiUrl . '"',
            'cd ' . $projectSlug . '.wiki',
            'rm -rf .git',
            'git init',
            'git remote add origin "' . $gitlabWikiUrl . '"',
            'git add . ',
            'git commit -am "Import Github wiki"',
            'git push origin master'
        ];

        $this->output('Executing ', BaseMigrator::OUTPUT_SUCCESS);

        $this->output(implode(';', $commands));

        shell_exec(implode(' && ', $commands));
    }
}