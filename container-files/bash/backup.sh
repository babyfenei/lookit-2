#/bin/bash

ADDRESS="$DB_ADDRESS"
USERNAME="$DB_USER"           #数据库用户名
PASSWORD="$DB_PASS"    #数据库密码     

cd /var/www/
mkdir -p /var/www/backups
# Remove old backups
find /var/www/backups/* -mtime +5 -exec rm -fr {} \; > /dev/null 2>&1


# Create the filename for the backup
eval `date "+day=%d; month=%m; year=%Y"`
INSTFIL="cacti-backup-$year-$month-$day.tar.gz"

# Dump the MySQL Database
mysqldump -h${ADDRESS} -u${USERNAME} -p${PASSWORD} cacti> $path/cacti-backup.sql

# Gzip the whole folder

tar -Pcpzf /var/www/backups/$INSTFIL $path/*

# Remove the SQL Dump
rm -f $path/cacti-backup.sql


