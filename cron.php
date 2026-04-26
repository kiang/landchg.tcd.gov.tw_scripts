<?php
$scriptPath = __DIR__;
$dataPath = $scriptPath . '/../landchg.tcd.gov.tw';

$now = date('Y-m-d H:i:s');

exec("cd {$dataPath} && /usr/bin/git pull");

exec("php -q {$scriptPath}/01_fetch_new.php");
exec("php -q {$scriptPath}/02_summary.php");

exec("cd {$dataPath} && /usr/bin/git add -A");

exec("cd {$dataPath} && /usr/bin/git commit --author 'auto commit <noreply@localhost>' -m 'auto update @ {$now}'");

exec("cd {$dataPath} && /usr/bin/git push origin master");
