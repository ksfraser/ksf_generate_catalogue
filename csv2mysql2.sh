#!/bin/bash
if [ "$#" -lt 2 ]; then
    if [ "$#" -lt 1 ]; then 
        echo "usage: $0 [path to csv file] <table name> > [sql filename]"
#        exit 1
    fi
    TABLENAME=$1
else
    TABLENAME=$2
fi
echo "CREATE TABLE $TABLENAME ( "
FIRSTLINE=$(head -1 $1)
# convert lowercase characters to uppercase
FIRSTLINE=$(echo $FIRSTLINE | tr '[:lower:]' '[:upper:]')
# remove spaces
FIRSTLINE=$(echo $FIRSTLINE | sed -e 's/ /_/g')
# add tab char to the beginning of line
FIRSTLINE=$(echo "\t$FIRSTLINE")
# add tabs and newline characters
FIRSTLINE=$(echo $FIRSTLINE | sed -e 's/,/,\\n\\t/g')
# add VARCHAR
FIRSTLINE=$(echo $FIRSTLINE | sed -e 's/,/ VARCHAR(255),/g')
# print out result
echo -e $FIRSTLINE" VARCHAR(255));"
