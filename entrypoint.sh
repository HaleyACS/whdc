#!/bin/bash
#
echo
mv -f /tmp/release.txt /var/www/files/release.txt
cat /var/www/files/release.txt
# Load variables
if [ -f  /etc/os-release ]
then
    . /etc/os-release
fi

echo " > $PRETTY_NAME"

echo
echo -n " => Installing: "

PREFIX=/var/www/html/site
FILES=/var/www/files
mkdir -p  $PREFIX $FILES

for file in whdc.php whdclist.php showhide.js style.css whdctokens.php favicon.ico webhook-logo.svg
do
    mv -f /tmp/${file} ${PREFIX}/${file}
    echo -n "${file} "
done

#tar zxf /var/www/html/websocket-php.tar.gz -C /var/www/html
#rm -f /var/www/html/websocket-php.tar.gz
mv -f /tmp/auth.inc ${FILES}/auth.inc
mv -f /tmp/LICENSE.md ${FILES}/LICENSE.md
mv -f /tmp/whdc.sql ${FILES}/whdc.sql
echo -n "auth.inc LICENSE.md whdc.sql"

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
chmod 660 ${FILES}/tokens.inc

HTCFG=${FILES}/.htconf.inc
if [ ! -f $HTCFG ]
then
    echo "<?php" > $HTCFG
    for VAR in MYSQL_DATABASE MYSQL_HOST MYSQL_PASSWORD MYSQL_USER
    do
        eval VARC=\$$VAR
        echo "define(\"$VAR\", \"$VARC\");" >> $HTCFG
    done
    echo " " >> $HTCFG
    echo "?>" >> $HTCFG
fi
chmod 660 $HTCFG
echo

echo " => Handling database backend"
# Install mysqldb if not existent
/tmp/install_mysqldb.sh

# remove installer script
rm -f /tmp/install_mysqldb.sh ${FILES}/whdc.sql

# Make sure it is not visible by php-fpm.
unset MYSQL_ROOT_PASSWORD

sync
echo
echo " => Setup done. Launching!"
echo
# Run php-fpm
exec /usr/local/sbin/php-fpm -F "$@"
