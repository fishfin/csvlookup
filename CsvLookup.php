<?php
/***
 * CsvLookup is a PHP class that provides powerful 'lookup' features for data
 * in a CSV file.
*/

namespace App;

class CsvLookup {
  private $emptyData, $ignoreChars, $parsedData, $saveArea;

  //private function strtolower($string) {
  //  return $this->mbExtnAvl ? mb_strtolower($string, mb_detect_encoding($string)) : strtolower($string);
  //}

  private function colPattern($string) {
    return preg_replace("/^[\n\r\s\t\x0B]*([#])+[#\n\r\s\t\x0B]*(.*?)[#\n\r\s\t\x0B]*$/", '$1$2', $string);
  }
  public function __construct($csvPath
      , $ignoreCharsString='.'
      , $matchWordsColName='match_words'
      , $headerMarker='_') {

    $this->fileEncoding = null;
    $this->emptyData = array();
    $this->ignoreChars = str_split($ignoreCharsString);
    $this->parsedData = array();
    $this->saveArea = array();

    $matchBlock = array();

    $headerMarker = mb_strtolower($headerMarker);
    $matchWordsColName = mb_strtolower(ltrim($this->colPattern($matchWordsColName), '#'));

    // Reading csv file that contains mapping. There is not much error handling (yet),
    // hopefully I will add it shortly.
    if (($handle = fopen($csvPath, 'r')) !== false) {
      while (($csvRow = fgetcsv($handle)) !== false) {
        if (!$this->fileEncoding) {
          $this->fileEncoding = mb_detect_encoding($csvRow[0]);
          if ($this->fileEncoding == 'UTF-8') {
            $csvRow[0] = preg_replace("/^" . pack('H*', 'EFBBBF') . "/", '', $csvRow[0]);  // removing bom
          }
        }
        $rowMarker = mb_strtolower(trim($csvRow[0]));
        unset($csvRow[0]);

        switch (mb_substr($rowMarker, 0, 1)) {
          case '#':                       // ignoring a comment row
            break;
          case $headerMarker:             // a header row
            $rowMarker = trim(mb_substr($rowMarker, 1));
            if (!isset($matchBlock[$rowMarker]['h'])) {            // if there was a header already read for this block, ignore this header
              //$matchBlock[$rowMarker]['hr'] = array();             // the reference header array, i.e.
              //$matchBlock[$rowMarker]['h'] = array();              // the reference header array, i.e.
              //$matchBlock[$rowMarker]['xr'] = array();             // the cross-reference array
              foreach ($csvRow as $c => $headerCol) {
                $crossRefCol = '';
                $headerCol = $this->colPattern($headerCol);
                if (strpos($headerCol, '=') !== false) {               // full column copy is possible, and will be overridden by individual values
                  $parts = explode('=', $headerCol);
                  $headerCol = $this->colPattern($parts[0]);
                  $crossRefCol = mb_strtolower(ltrim($this->colPattern($parts[1])));
                }
                $headerColRef = mb_strtolower(ltrim($headerCol, '#'));
                if ($headerColRef == ''
                      or (isset($matchBlock[$rowMarker]['hr'])
                          and in_array($headerColRef, $matchBlock[$rowMarker]['hr']))) {
                  continue;
                }
                $matchBlock[$rowMarker]['hr'][$c] = $headerColRef;    // header reference column (lower case, trimmed, uncommented
                $matchBlock[$rowMarker]['h'][$c] = $headerCol;        // header column - as-as
                if ($crossRefCol != '') {
                  $matchBlock[$rowMarker]['xr'][$c] = $crossRefCol;
                }
                if ($headerColRef == $matchWordsColName) {
                  $matchBlock[$rowMarker]['m'] = $c;
                }
                if (mb_substr($headerCol, 0, 1) != '#') {
                  $this->emptyData[$rowMarker][$headerCol] = '';      // empty output
                }
              }
              if (isset($this->emptyData[$rowMarker])) {
                ksort($this->emptyData[$rowMarker]);
              }
            }
            break;
          default:
            $matchBlock[$rowMarker]['d'][] = array_map('trim', $csvRow);
            break;
        }
      }
      fclose($handle);
    }

    foreach ($matchBlock as $blockId => $blockRows) {
      if (!isset($this->emptyData[$blockId])                        // not even a column of output
            or !isset($matchBlock[$blockId]['m'])) {                // matchcol not detected
        echo "Please enter header row with first column as '_{$blockId}' with at least 2 columns"
          . ", one being '{$matchWordsColName}'."; exit();
      }
      
      if (!isset($blockRows['d'])) {
        continue;
      }
      
      foreach ($blockRows['d'] as $blockDataRow) {
        $matchWordsValue = mb_strtolower($blockDataRow[$matchBlock[$blockId]['m']]); // we now want to make match words data as our primary key
        if (isset($this->parsedData[$blockId][$matchWordsValue])) {                   // if same match word value was already encountered earlier
          continue;                                                                   // then this data row is ignored, this is in line with only reading first occurance
        }
        $dataRowAsDict = array();
        foreach ($blockDataRow as $c => $_) {                              // we now want to make match words data as our primary key
          if (!isset($matchBlock[$blockId]['h'][$c])                       // the header column was removed, so this data column is not processed
                or mb_substr($matchBlock[$blockId]['h'][$c], 0, 1) == '#') {
            continue;
          }
          $locateColNum = $c; $circularRefCheck = array();
          while (true) {
            $circularRefCheck[] = $locateColNum;
            $blockDataCol = trim($blockDataRow[$locateColNum], '\'"');
            if ($blockDataCol == '' and isset($matchBlock[$blockId]['xr'][$locateColNum])) {
              $blockDataCol = $blockDataRow[$locateColNum] = "={$matchBlock[$blockId]['xr'][$locateColNum]}";
            }
            if (mb_substr($blockDataCol, 0, 1) != '=') {
              break;
            }
            $blockDataColRef = mb_substr($blockDataCol, 1);
            $locateCol = mb_strtolower(ltrim($this->colPattern($blockDataColRef), '#'));
            $locateColNum = array_search($locateCol, $matchBlock[$blockId]['hr']);
            if ($locateColNum === false) {
              break;
            }
            if (in_array($locateColNum, $circularRefCheck)) {
              $circularRefColNums = implode(',', $circularRefCheck);
              echo "Circular reference in data row for block '{$blockId}', {$matchWordsColName} '{$matchWordsValue}', columns {$circularRefColNums}";
              exit();
            }
          }
          $dataRowAsDict[$matchBlock[$blockId]['h'][$c]] = $blockDataCol;
        }
        ksort($dataRowAsDict);
        $this->parsedData[$blockId][$matchWordsValue] = $dataRowAsDict;
      }
    }
    return;
  }

  public function search($subject, $blockId='') {
    $subject = mb_strtolower(trim($subject));
    $blockId = mb_strtolower(trim($blockId));
    foreach ($this->ignoreChars as $ignoreChar) {
      $subject = str_replace($ignoreChar, '', $subject);
    }

    if (isset($this->saveArea[$blockId][$subject])) {
      return $this->saveArea[$blockId][$subject];
    }

    $matched = false;

    if (isset($this->parsedData[$blockId])) {
      foreach ($this->parsedData[$blockId] as $matchWord => $rowData) {
        $allowedMatches = explode('|', $matchWord);
        foreach ($allowedMatches as $allowedMatch) {
          $matched = true;
          $mustContains = explode('&', $allowedMatch);
          foreach ($mustContains as $mustContain) {
            if (mb_substr($mustContain, 0, 1) == '!') {
              $mustNotContain = mb_substr($mustContain, 1);
              if (mb_substr($mustNotContain, 0, 1) == '=' and $subject != mb_substr($mustNotContain, 1)) {
                break;
              } else if (strpos($subject, $mustNotContain) !== false) {
                $matched = false;
                break;
              }
            } else {
              if (mb_substr($mustContain, 0, 1) == '=' and $subject == mb_substr($mustContain, 1)) {
                break;
              } else if (strpos($subject, $mustContain) === false) {
                $matched = false;
                break;
              }
            }
          }
          if ($matched) {
            break 2;
          }
        }
      }
    }
	
    if ($matched) {
      $matchedData = $rowData;
      $matchedData['ERROR_CODE'] = 0;
    } else {
      $matchedData = isset($this->emptyData[$blockId])
          ? $this->emptyData[$blockId] : array();
      $matchedData['ERROR_CODE'] = 1;
    }

    return $this->saveArea[$blockId][$subject] = $matchedData;
  }
}