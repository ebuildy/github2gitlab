<?php

require 'vendor/autoload.php';

include('secret.php');

error_reporting(E_ALL);

/**
 * Create Gitlab client
 */
$gitlabClient = new \Gitlab\Client(GITLAB_URL);

$gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

/**
 * Create Github client
 */
$githubClient = new \Github\Client();

$githubClient->authenticate(GITHUB_TOKEN, null, \Github\Client::AUTH_URL_TOKEN);


/**
 * Reset all project and user (optional)
 */
$reset = new \ebuildy\github2gitlab\Reset(null, $gitlabClient, $org);

$reset->run();


/**
 * Run migration
 */
$migrator = new \ebuildy\github2gitlab\Migrator($githubClient, $gitlabClient, $org);

$migrator->run(false);