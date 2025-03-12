#!/bin/sh

PREFIX=/var/www/files

if [ ! -f ${PREFIX}/.INSTALLED ]
then

    echo " > Running BD installer script"

    # Required for the user configuration
    TMPDB="${PREFIX}/.config.cnf"
    touch $TMPDB
    chmod 600 $TMPDB
    echo "[client]" > $TMPDB
    echo "user = root" >> $TMPDB
    echo "password = $MYSQL_ROOT_PASSWORD" >> $TMPDB
    echo "host = $MYSQL_HOST" >> $TMPDB
    sync

    echo -n " > Create tables if these don't exist: "
    # echo "    mariadb --skip-ssl -v -h $MYSQL_HOST --defaults-extra-file=${PREFIX}/.config.cnf $MYSQL_DATABASE < /var/www/files/whdc.sql";
    mariadb --defaults-file="${PREFIX}/.config.cnf" --skip-ssl $MYSQL_DATABASE < /var/www/files/whdc.sql
    echo "Done"
     
    echo " > Cleaning up installation"   
    rm -f $TMPDB
    
    echo "Deployed `date`" >> ${PREFIX}/.INSTALLED
else
    echo " > Database existing. Skipped!"
fi
