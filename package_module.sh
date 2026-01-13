#!/bin/sh

#When packaging up a module to move environments we need to make sure the requrie* and include* dependencies are met!

echo "ensure dependencies are met! But don't include files that aren't tested"
grep require *
grep include *

cd ..
tar czvf ksf_generate_catalogue.tgz ksf_generate_catalogue ksf_modules_common/


#env var 1 ($1) is class name i.e. woo_customer
#echo "<?php" > conf.$1.php

