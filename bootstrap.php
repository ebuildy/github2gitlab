<?php

require 'vendor/autoload.php';

include('secret.php');

$gitlabClient = new \Gitlab\Client(GITLAB_URL);

$gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

$reset = new \ebuildy\github2gitlab\Reset(null, $gitlabClient, $org);

$reset->run();

$migrator = new \ebuildy\github2gitlab\Migrator($githubClient, $gitlabClient, $org);

$migrator->run(false);