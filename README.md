# [[fishfin] csvlookup](https://github.com/fishfin/csvlookup)

> `#php`

CsvLookup is a PHP class that provides powerful 'lookup' features for data in a CSV file. The CSV file is largely generic in nature, but with a special format (see below), it defines blocks of data (see it like table within a table), each having a header row. There is one column within this block which is the lookup column (the input is 'looked up' on this column based on some rules), and on a match, the rest of the values in the row are returned as a JSON with the headers as keys.

### Requirements

Requires ``mbstring`` extension to be enabled in PHP.

### Input CSV Format

- Column 1 - Marker column
  - If it starts with `#`, it is treated as a comment and is not read by the program. It is provided for adding documentation to the CSV itself.
  - If it starts with ` _`, it is a header row. Example: ` _currency` in column 1 makes that row the header row of all data rows that belong to `currency` block. 
  - If it does not start with a row (blank or non-blank), it signifies a data block. Example: `currency` in column 1 makes that row a data row that belongs to the `currency` block, whose header is the row which has ` _currency` in column 1

 * Columns 2 onwards

   - On a header row (Column 1 starts with ` _`), the columns signify the column names of the block.

     - If blank, the whole column (header and data rows of the block) is ignored
     - If the first character is `#`, it is a hidden column, i.e. not sent in the output; such columns can be referenced from other fields (with the `=Hidden_Column_Name` construct)
     - If the column name is `match_words` or `#match_words` (hidden), it is the lookup column which will be used for matching (see matching rules lower in this document). It is possible to use column name other than `match_words` in the CSV, while instantiating the class, the name can be passed.
     - Otherwise sent as key in the same alphabet case in the output
   - On a data row (Column 1 is does not start with `#` or ` _`), they are the values of the columns of the block.

> **Special Note:** If there are multiple occurrences of rows or columns, only the first one is considered, all succeeding occurrences are ignored.


### Match Rules

| Symbol | Description                                                  |
| ------ | ------------------------------------------------------------ |
| \|     | OR operator - e.g. OR the input haystack contains this word  |
| &      | AND operator - e.g. AND the input haystack contains this word |
| =      | EQUAL operator - e.g. the input haystack is exactly equal to this word |
| !      | NOT operator - e.g. the input haystack does not contain this word |

Examples:

| #match_words            | Input             | Will Match | Description                                                  |
| ----------------------- | ----------------- | ---------- | ------------------------------------------------------------ |
| india                   | Republic of India | Yes        | Input may contain 'india'                                    |
| =india                  | Republic of India | No         | Input must exactly match 'india'                             |
| =usa                    | U.S.A.            | Yes        | Input must exactly match 'usa', dots are removed by default (behaviour can be modified when instantiating the class) |
| burma\|myanmar          | Burma             | Yes        | Input may contain 'burma' or 'myanmar'                       |
| burma\|myanmar          | Myanmar           | Yes        | Input may contain 'burma' or 'myanmar'                       |
| timor&leste\|east&timor | Timor             | No         | Input must contain ('timor' and 'leste') or ('east' and 'timor') |
| timor&leste\|east&timor | Timor Leste       | Yes        | Input must contain ('timor' and 'leste') or ('east' and 'timor') |
| timor&leste\|east&timor | Timor East        | Yes        | Input must contain ('timor' and 'leste') or ('east' and 'timor') |
| !bissau&guinea          | Guinea            | Yes        | Input must contain 'guinea' and not contain 'bissau'         |
| !bissau&guinea          | Guinea-Bissau     | No         | Input must contain 'guinea' and not contain 'bissau'         |



| _rate | #match_words                | currency | #prev2 | prev1=prev2 | to_inr_today | #notes                                                       |
| ----- | --------------------------- | -------- | ------ | ----------- | ------------ | ------------------------------------------------------------ |
| rate  | india                       | INR      | 1.0000 |             | =prev1       | prev1 copied from prev2 if blank (column-level copy); to_inr_today copied from prev1 at cell-level |
| rate  | kyrgyzstan\|kyrgyz&republic | KGS      | 1.2031 | 1.2015      | 1.1998       |                                                              |
| #rate | venezuela                   | VER      | 0.0003 | 0.0002      | 0.0003       | This data row is not processed                               |
| **_fish** | **#match_words** | **color** |||||
| fish | emperor&angelfish | white,blue |        |             |              |                                                              |
| fish | discus | white,orange |        |             |              |                                                              |
| fish | rainbow&parrotfish | blue,silver,grey |        |             |              |                                                              |
| fish | clownfish | white,black,orange |        |             |              |                                                              |

### Example Calls

```php
<?php
include CsvLookup.php

// Parm 1=Csv File
// Parm 2=Ignore Chars as string, default '.'
// Parm 3=Match col name, default 'match_words'
// Parm 4=Header identifier char, default '_'
$lookup = new App\CsvLookup('c:\project\sample_lookup.csv');
print_r($lookup('Republic of India', 'rate'));
/* Array {
     [currency] => INR
     [prev1] => 1.0000
     [to_inr_today] => 1.0000
     [ERROR_CODE] => 0
   }
*/
print_r($lookup('Venezuela', 'rate'));
/* Array {
     [ERROR_CODE] => 1
   }
*/
print_r($lookup('Clownfish', 'fish'));
/* Array {
     [color] => white,black,orange
     [ERROR_CODE] => 0
   }
*/
print_r($lookup('Royal Gramma', 'fish'));
/* Array {
     [ERROR_CODE] => 1
   }
*/
print_r($lookup('India', 'model'));
/* Array {
     [ERROR_CODE] => 1
   }
*/
```