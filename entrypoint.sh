#!/bin/bash
#
echo
echo " => Web Hook data collector"
echo

echo -n " => Installing: "
for file in whdc.php whdclist.php showhide.js style.css whdctokens.php favicon.ico webhook-logo.svg
do
    mv -f /tmp/${file} /var/www/html/site/${file}
    echo -n "${file} "
done

tar zxf /var/www/html/websocket-php.tar.gz -C /var/www/html
rm -f //var/www/html/websocket-php.tar.gz
mv -f /tmp/auth.inc /var/www/files/auth.inc
mv -f /tmp/LICENSE.md /var/www/files/LICENSE.md
echo -n "auth.inc LICENSE.md"

if [ ! -f /var/www/files/tokens.inc ]
then
   cat <<EOF | tee /var/www/files/tokens.inc
<?php
// Dynamically created secret at first deployment.
// Do not change this secret - it would invalidate all existing tokens
\$secret="`tr -dc A-Za-z0-9 </dev/urandom | head -c 16; echo`"

?>
EOF
fi
# Do not permit world to access this file.
chmod 660 /var/www/files/tokens.inc

echo
sync
echo " => Setup done. Launching!"
echo
# Run php-fpm
exec /usr/local/sbin/php-fpm -F "$@"
