<?php

namespace ebuildy\github2gitlab\migrator;

class UserMigrator extends BaseMigrator
{
    /**
     * @var array
     */
    protected $usersMap;


    /**
     * @var array
     */
    protected $gitlabUsers;


    /**
     * @param array $githubUser
     * @param bool $login
     * @return mixed
     * @throws \Exception
     */
    public function getGitlabUserFromGithub($githubUser, $login = true)
    {
        if (empty($githubUser))
        {
            throw new \Exception('getGitlabUserFromGithub(NULL) ?');
        }

        $githubUserId = is_array($githubUser) ? $githubUser['id'] : $githubUser;

        // 1. Look on Gitlab existing users
        if (!isset($this->usersMap[$githubUserId]))
        {
            foreach($this->gitlabUsers as $gitlabUser)
            {
                if ($gitlabUser['name'] === $githubUser['login'])
                {
                    $this->usersMap[$githubUserId] = $gitlabUser;

                    break;
                }
            }
        }

        // 2. Else create user
        if (!isset($this->usersMap[$githubUserId]))
        {
            $this->output('[User] Insert ' . $githubUser['login'], self::OUTPUT_SUCCESS);

            $this->usersMap[$githubUserId] = $this->gitlabClient->users->create($githubUser['login'] . '@email.com',
                DEFAULT_PASSWORD, [
                    'extern_uid'  => $githubUser['id'],
                    'name'        => $githubUser['login'],
                    'username'    => $githubUser['login'],
                    'admin'       => $githubUser['site_admin'],
                    'confirm'     => false
                ]);
        }

        if (!isset($this->usersMap[$githubUserId]))
        {
            throw new \Exception('Cannot retrieve Github user ' . $githubUserId);
        }

        $gitlabUser = $this->usersMap[$githubUserId];

        if ($login && !isset($gitlabUser['token']))
        {
            $this->output('Login ' . $gitlabUser['username'] . ' ...');

            while(true)
            {
                try
                {
                    $session = $this->gitlabClient->users->login($gitlabUser['username'], DEFAULT_PASSWORD);

                    $this->usersMap[$githubUserId]['token'] = $session['private_token'];

                    break;
                }
                catch (\Exception $e)
                {
                    $this->output('<!> ' . $e->getMessage() . ', wait 25 secondes...', self::OUTPUT_ERROR);

                    sleep(25);
                }
            }
        }

        return $this->usersMap[$githubUserId];
    }

    public function run($dry = true)
    {
        $githubUsers    = $this->githubClient->organization()->members()->all($this->organization);
        $gitlabUsers    = $this->gitlabClient->users->all(null, 1, 500);

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
                $this->output('[User] Insert ' . $githubMember['login'], self::OUTPUT_SUCCESS);

                if (!$dry)
                {
                    $gitlabUser = $this->gitlabClient->users->create($githubMember['login'] . '@email.com',
                        DEFAULT_PASSWORD, [
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
                $gitlabUser = $searchExistingUser;
            }

            $this->usersMap[$githubMember['id']] = $gitlabUser;
        }

        $this->gitlabUsers = $gitlabUsers;
    }
}