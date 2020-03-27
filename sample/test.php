<?php
include CsvLookup.php

// Parm 1=Csv File
// Parm 2=Ignore Chars as string, default '.'
// Parm 3=Match col name, default 'match_words'
// Parm 4=Header identifier char, default '_'
$lookup = new App\CsvLookup('c:\project\sample_lookup.csv');

print_r($lookup('Republic of India', 'rate'));
print_r($lookup('Venezuela', 'rate'));
print_r($lookup('Clownfish', 'fish'));
print_r($lookup('Royal Gramma', 'fish'));
print_r($lookup('India', 'model'));