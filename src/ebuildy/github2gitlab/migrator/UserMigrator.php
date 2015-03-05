<?php

namespace ebuildy\github2gitlab\migrator;

class UserMigrator extends BaseMigrator
{
    /**
     * @var array
     */
    private $usersMap = [];

    public function getUsersMap()
    {
        return $this->usersMap;
    }

    public function run($dry = true)
    {
        $githubUsers  = $this->githubClient->organization()->members()->all($this->organization);
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
}