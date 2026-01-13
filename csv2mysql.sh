#!/bin/sh

CREDENTIALS="--user=root --password=m1l1ce --host=mysql.ksfraser.com" 
CSVOPTIONS="$CREDENTIALS --local --delete --lock-tables --fields-terminated-by=; --fields-optionally-enclosed-by=\" --lines-terminated-by=\n" 
FOLDER='.' 
DB=fhs_frontaccounting 

#files=wc-product-export-30-7-2024.csv 

#for i in $files
for i in wc-product-export-30-7-2024.csv 
do 
    # get table name from file name
    TABLE="$(basename -- $i)"
    TABLE="${TABLE%.*}"

    # create the table
    COMMAND="DROP TABLE IF EXISTS $TABLE; CREATE TABLE $TABLE ( $(head -1 $FOLDER/$i | sed -e 's/;/ varchar(255),\n/g') varchar(255) );"
    mysql $CREDENTIALS $DB -e "$COMMAND"

    # fill in data
    mysqlimport $CSVOPTIONS --ignore-lines=1 $DB "$FOLDER/$i"

done

