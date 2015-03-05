<?php

namespace ebuildy\github2gitlab;

class Migrator
{
    /**
     * Reference githubUserID ==> gitlabUserID
     *
     * @var array
     */
    public $usersMap;


    /**
     * @var \Github\Client
     */
    public $githubClient;


    /**
     * @var \Gitlab\Client
     */
    public $gitlabClient;


    /**
     * @param $githubClient
     * @param $gitlabClient
     */
    public function __construct($githubClient, $gitlabClient)
    {
        $this->githubClient = $githubClient;
        $this->gitlabClient = $gitlabClient;

        $this->usersMap = [];
    }


    /**
     * @param string $org
     * @param bool $dry
     */
    public function run($org, $dry = true)
    {
        $this->migrateUsers($org, $dry);

        $this->migrateTeams($org, $dry);
    }

    public function migrateUsers($org, $dry = true)
    {
        $githubUsers  = $this->githubClient->organization()->members()->all($org);
        $gitlabUsers   = $this->gitlabClient->users->all(null, 1, 100);

        foreach($githubUsers as $githubMember)
        {
            $searchExistingUser = null;

            foreach($gitlabUsers as $gitlabUser)
            {
                if ($gitlabUser['username'] === $githubMember['login'])
                {
                    $searchExistingUser = $gitlabUser;

                    break;
                }
            }

            if (empty($searchExistingUser))
            {
                $this->output('[User] Insert ' . $githubMember['login']);

                if (!$dry)
                {
                    $gitlabUserId = $this->gitlabClient->users->create($githubMember['login'] . '@email.com',
                        'password', [
                            'extern_uid'  => $githubMember['id'],
                            'name'        => $githubMember['login'],
                            'username'    => $githubMember['login'],
                            'admin'       => $githubMember['site_admin'],
                            'confirm'     => false
                        ]);
                }
            }
            else
            {
                $gitlabUserId = $searchExistingUser['id'];
            }

            $this->usersMap[$githubMember['id']] = $gitlabUserId;
        }
    }

    public function migrateTeams($org, $dry = true)
    {
        $githubTeams    = $this->githubClient->organization()->teams()->all($org);
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
                $gitlabUserId  = $this->usersMap[$githubMember['id']];

                $this->output('[Group] Add member ' . $gitlabUserId . ' to group ' . $githubTeam['name']);

                if (!$dry)
                {
                    try
                    {
                        $this->gitlabClient->groups->addMember($existingGroup['id'], $gitlabUserId, 30);
                    }
                    catch (\Exception $e)
                    {

                    }
                }
            }
        }
    }

    private function output($message)
    {
        echo $message . PHP_EOL;
    }

}