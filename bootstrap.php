<?php

require 'vendor/autoload.php';

include('secret.php');

error_reporting(E_ALL);

define('ROOT', __DIR__);


/**
 * Create Gitlab client
 */
$gitlabHttpClient = new \Buzz\Client\Curl();

$gitlabHttpClient->setOption(CURLOPT_SSL_VERIFYHOST, false);
$gitlabHttpClient->setVerifyPeer(false);

$gitlabClient = new \Gitlab\Client(GITLAB_URL, $gitlabHttpClient);

$gitlabClient->authenticate(GITLAB_ADMIN_TOKEN, \Gitlab\Client::AUTH_URL_TOKEN);

/**
 * Create Github client
 */
$githubClient = new \Github\Client();

$githubClient->authenticate(GITHUB_TOKEN, null, \Github\Client::AUTH_URL_TOKEN);

/**
 * Initialize DI container
 */
\ebuildy\github2gitlab\DIC::getInstance()->init($githubClient, $gitlabClient, $org);

/**
 * Reset all project and user (optional)
 */
$reset = new \ebuildy\github2gitlab\Reset();

$reset->run();


/**
 * Run migration
 */
$migrator = new \ebuildy\github2gitlab\Migrator();

$migrator->run(false);