<?php

require 'vendor/autoload.php';

include('secret.php');

$migrator = new \ebuildy\github2gitlab\Migrator($githubClient, $gitlabClient);

$migrator->run($org, true);