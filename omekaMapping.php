<?php
/**
 * User: NaeemM
 * Date: 27/08/14
 */


//require_once("omekaMappingRules.php");
require_once("mappingRulesParser.php");
require_once("omekaRecord.php");

class omekaMapping {

    public function generateOmekaRecords($sourceFile, $mappingRulesFile, $resultFile){
        $mappingRulesFile = '/var/www/html/euInside_new/files/mappingrules.csv'; //TEMPORARY

        $pathInfo = pathinfo($sourceFile);
        $resultFile = $pathInfo['dirname'].'/'.$resultFile;

        $records = $this->getRecords($sourceFile);
        $mappingRules = $this->getMappingRules($mappingRulesFile);
        $mappeRecords = $this->mappRecords($records, $mappingRules);

        file_put_contents($resultFile.'20', print_r( $records, true)."\r\n", FILE_APPEND);
        file_put_contents($resultFile.'21', print_r( $mappingRules, true)."\r\n", FILE_APPEND);

        file_put_contents($resultFile, json_encode($mappeRecords));
        file_put_contents('/var/www/html/euInside_new/files/tbr/tempoutputrecord.json', print_r( json_encode($mappeRecords), true)."\r\n", FILE_APPEND);
        return true;
    }

    function mappRecords($records, $mappingRules){
        if(!isset($records,$mappingRules))
            return null;
        $mappedRecords = array();
        //$counter = 0;
        foreach($records as $item){
            //if($counter == 0)
                $mappedRecords[] = $this->applyMappingRules($item, $mappingRules);
            //$counter++;
        }
        return $mappedRecords;
    }

    function applyMappingRules($record, $mappingRules){
        $omekaJsonRecord = new omekaRecord();
        $omekaJsonRecord->public = true;
        $omekaJsonRecord->featured = false;
        $omekaJsonRecord->collection = null;

        foreach($mappingRules as $rule){
            $rule->omekaElement = str_replace("\r\n",'', $rule->omekaElement);
            $rule->caElement = str_replace("\r\n",'', $rule->caElement);
            if(!array_key_exists($rule->caElement, $record))
                continue;

            switch($rule->command){
                case 'COPY':
                    $value = $record[$rule->caElement];
                    $this->addElement($omekaJsonRecord, $value, $rule->omekaElement);
                    break;
                case 'APPEND':
                case 'PREPEND':
                case 'SPLIT':
                case 'COMBINE':
                case 'LIMIT':
                case 'PUT':
                case 'REPLACE':
                    break;
            }
        }
        return $omekaJsonRecord;
    }

    function addElement(&$omekaJsonRecord, $value, $omekaElement){
        $valueToAdd = array();
        $isHtml = false;
        if(is_object($value)){  //an object
            $objectValue = $this->objToArray($value);
            $flatValues = $this->flattenValue($objectValue);

            if(is_array($flatValues)){
                foreach($flatValues as $item)
                    $valueToAdd [] = $item;
            }
            else
                $valueToAdd [] = $flatValues;
        } //not an object
        else
            $valueToAdd [] = $value;

        foreach($valueToAdd as $valueItem){
            $omekaJsonRecord->element_texts[] = array(
                'html' => $isHtml,
                'text' => $valueItem,
                'element_set' => null,
                'element' => $omekaElement
            );
        }
    }

    function objToArray($value){  //converts objects of array, iterates untill a non-object value is reached
        $extractedValue = array();
        $valueObject = get_object_vars($value);
        if(is_array($valueObject)){
            foreach($valueObject as $subValue){
                $extractedValue [] = (is_object($subValue)) ? $this->objToArray($subValue) : $subValue;
            }
        }
        else
            $extractedValue [] = $valueObject;
        return $extractedValue;
    }

    function flattenValue($value){
        if(!is_array($value))
            return $value;
        $returnValues = array();
        array_walk_recursive($value, function($a) use (&$returnValues) { $returnValues[] = $a; });
        return $returnValues;
    }

    function getRecords($recordFile){
        $records = array();
        $data = file_get_contents ($recordFile);
        $jsonArray = json_decode($data);
        $recordsJson = $jsonArray->{'results'};
        foreach($recordsJson as $item){
            //$records[] = $this->objectToArray($item);
            $records[] = $this->objectKeyValues($item);
        }
        return $records;
    }

    function getMappingRules($rulesFile){
        $mappingRules = array();
        $mappingParser = new mappingRulesParser();
        $row = 1;
        if (($handle = fopen($rulesFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $mappingRules[] = $mappingParser->marcMappingCommandParser($data, $row, $handle);    //mapping rules
                $row++;
            }
            fclose($handle);
        }
        return $mappingRules;
    }

    function objectKeyValues($array){
        $result = array();
        foreach ($array as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }


} 