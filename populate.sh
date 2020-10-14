#!/bin/bash
YMD=`/bin/date +%Y_%m_%d`
/usr/bin/php /var/www/html/dia-a-dia/servidor/populateDb.php > /var/www/html/dia-a-dia/servidor/logs/log_$YMD.txt
