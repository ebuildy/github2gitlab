<?php

require 'vendor/autoload.php';

include('secret.php');

$migrator = new \ebuildy\github2gitlab\Migrator($githubClient, $gitlabClient, $org);

$migrator->run(true);