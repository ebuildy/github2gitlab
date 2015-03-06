Import Github project to Gitlab Gitlab has a native plugin to import a Github repository, but it’s very basic. I wrote a real script that import all Github organization stuff to Gitlab.

### Features

- [x] Members
- [ ] Members email (need to parse .git/logs/HEAD)
- [x] Teams ==> Gitlab groups
- [x] Labels
- [x] Milestones
- [x] Projects
- [x] Issues
- [x] Issues comments
- [ ] Issues date (Gitlab API doesnot offer to specify dates)
- [ ] Pull Request ==> Gitlab merge request

### How to use it

Create a secret.php file on the root of the project (it will be ignored by git):

```php
<?php

define('DEFAULT_PASSWORD', 'password');

define('GITLAB_URL', 'http://192.168.59.103/api/v3/');
define('GITLAB_ADMIN_TOKEN', '*******************');

date_default_timezone_set('Europe/Paris');

define('GITHUB_TOKEN', '*********************');

$org = 'my_org';
```

Then run "php bootstrap.php" on a console.

### How to generate Github token

I let you read Github help https://help.github.com/articles/creating-an-access-token-for-command-line-use/.

### How to generate Gitlab token

Send a POST curl like this:

```bash
➜  .git git:(master) curl "192.168.59.103/api/v3/session?login=root&password=5iveL\!fe" -XPOST
{"name":"Administrator","username":"root","id":1,"state":"active","avatar_url":"http://www.gravatar.com/avatar/e64c7d89f26bd1972efa854d13d7dd61?s=40\u0026d=identicon","created_at":"2015-03-05T17:17:55.290Z","is_admin":true,"bio":null,"skype":"","linkedin":"","twitter":"","website_url":"","email":"admin@example.com","theme_id":2,"color_scheme_id":1,"projects_limit":10000,"identities":[],"can_create_group":true,"can_create_project":true,"private_token":"kZViJx6-H3ri6DAZNGK6"}
```

Here you can copy/paste the private_token!

### Technologies involved Php

- Php (yes I know...)
- Composer for Gitlab and Github API SDK
- Gitlab and Github API
