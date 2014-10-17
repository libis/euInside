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
        $pathInfo = pathinfo($sourceFile);
        $resultFile = $pathInfo['dirname'].'/'.$resultFile;

        $records = $this->getRecords($sourceFile);
        $mappingRules = $this->getMappingRules($mappingRulesFile);
        $mappeRecords = $this->mappRecords($records, $mappingRules);
        file_put_contents($resultFile, json_encode($mappeRecords));
        return true;
    }

    function mappRecords($records, $mappingRules){
        if(!isset($records,$mappingRules))
            return null;
        $mappedRecords = array();
        foreach($records as $item){
            $mappedRecords[] = $this->applyMappingRules($item, $mappingRules);
        }
        return $mappedRecords;
    }

    function applyMappingRules($record, $mappingRules){
        $omekaJsonRecord = new omekaRecord();

        $noSourceValueCommands = array("PUT");                                       // rules which do not have source elements
        $headerElements = array("public", "featured", "item_type", "collection");    // header elements e.g. featured, public


        foreach($mappingRules as $rule){
            $rule->omekaElement = str_replace("\r\n",'', $rule->omekaElement);
            $rule->caElement = str_replace("\r\n",'', $rule->caElement);

            if(!in_array($rule->command, $noSourceValueCommands) && !array_key_exists($rule->caElement, $record))
                continue;

            $isHeaderElement = false;
            if(in_array($this->getTargetElement($rule->omekaElement), $headerElements))
                $isHeaderElement = true;

            switch($rule->command){
                case 'COPY':
                    $value = $record[$rule->caElement];
                    $this->addElement($omekaJsonRecord, $value, $rule->omekaElement, $isHeaderElement);
                    break;

                case 'APPEND':
                    $value = $record[$rule->caElement];
                    if(isset($rule->fields['appendtext']) && strlen($rule->fields['appendtext']) > 0){
                        $valuetoAppend = $value.' '.$rule->fields['appendtext'];
                        $this->addElement($omekaJsonRecord, $valuetoAppend, $rule->omekaElement, $isHeaderElement);
                    }
                    break;

                case 'PREPEND':
                    $value = $record[$rule->caElement];
                    if(isset($rule->fields['appendtext']) && strlen($rule->fields['appendtext']) > 0){
                        $valuetoPrepend = $rule->fields['appendtext'].' '.$value;
                        $this->addElement($omekaJsonRecord, $valuetoPrepend, $rule->omekaElement, $isHeaderElement);
                    }
                    break;
                case 'SPLIT':
                case 'COMBINE':
                case 'LIMIT':
                case 'PUT':
                    if(isset($rule->fields['puttext']) && strlen($rule->fields['puttext']) > 0){
                        $valuetoPut = str_replace('||', ',', $rule->fields['puttext']);
                        $this->addElement($omekaJsonRecord, $valuetoPut, $rule->omekaElement, $isHeaderElement);
                    }
                    break;
                case 'REPLACE':
                    break;
            }
        }
        return $omekaJsonRecord;
    }

    function getTargetElement($element){
        $parts = explode("::",$element);
        if(is_array($parts))
            return end($parts);
        else
            return $element;
    }

    function addElement(&$omekaJsonRecord, $value, $omekaElement, $isHeaderElement){

        if($isHeaderElement === true){
            switch($this->getTargetElement($omekaElement)){
                case 'public':
                    $omekaJsonRecord->public = $value;
                    break;

                case 'featured':
                    $omekaJsonRecord->featured = $value;
                    break;

                case 'item_type':
                    $omekaJsonRecord->item_type = $value;
                    break;

                case 'collection';
                    $omekaJsonRecord->collection = $value;
                    break;
            }
            return;
        }
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