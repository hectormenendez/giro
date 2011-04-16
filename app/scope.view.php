<?php
echo "this is the view\n";
print_r(get_defined_vars());
$consts = get_defined_constants(true);
print_r($consts['user']);