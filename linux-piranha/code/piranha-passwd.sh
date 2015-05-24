#!/bin/sh
# This shell script makes use of the htpasswd utility normally
# installed by the apache web server rpm.
DEST=/etc/sysconfig/ha/conf

if [ -z "$1" ]; then
	echo "Sorry, you should supply a single password as the argument to this command"
	echo "eg piranha-passwd password"
	echo
	exit 1;
fi

# Two possibilities,.. the file exists and the file doesn't exist

if [ -f $DEST/piranha.passwd ]; then
	/usr/bin/htpasswd -b $DEST/piranha.passwd piranha $1
fi

if [ ! -f $DEST/piranha.passwd ]; then
	/usr/bin/htpasswd -c -b $DEST/piranha.passwd piranha $1
fi

# Paranoia, unfortunately the web server itself must be able to read the file

chmod 600 $DEST/piranha.passwd
chown piranha.piranha $DEST/piranha.passwd

