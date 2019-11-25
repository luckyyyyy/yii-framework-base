#!/bin/bash
set -xe
filePath=$(realpath $0)
baseDir=$(dirname $filePath)
siteDir=$(realpath $baseDir/../../)

# git pull
$siteDir/static/scripts/auto-pull.sh >> /data/www/logs/haiman.auto-pull.log 2>&1 &

# crontab
/etc/init.d/cron restart
crontab $siteDir/static/cron.tab
