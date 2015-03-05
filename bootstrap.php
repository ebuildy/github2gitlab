<?php

require 'vendor/autoload.php';

include('secret.php');

$reset = new \ebuildy\github2gitlab\Reset(null, $gitlabClient, $org);

$reset->run();

$migrator = new \ebuildy\github2gitlab\Migrator($githubClient, $gitlabClient, $org);

$migrator->run(false);