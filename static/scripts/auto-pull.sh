#!/bin/sh
#####################
cd /data/www/haiman.io

test ! -d runtime/gitlab  && mkdir -m 0777 runtime/gitlab

# -gt 2; one process is grep; one process is current script
test `pgrep -f auto-pull.sh|wc -l` -gt 2 && echo "auto pull running" && exit

file=runtime/gitlab/haiman-master-push.log
echo -n > $file
tail -f $file | xargs -i sh -c 'git reset --hard; git pull; php yii migrate --interactive=0'
