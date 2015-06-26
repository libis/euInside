<?php
/**
 * User: NaeemM
 * Date: 27/08/14
 */


require_once("mappingRulesParser.php");
require_once("omekaRecord.php");

/**
 * Class omekaMapping
 */
class omekaMapping {

    /**
     * This is the entry point for mapping records in json format to omeka compliant records.
     * @param $sourceFile
     * @param $mappingRulesFile
     * @param $resultFile
     * @return bool
     */
    public function generateOmekaRecords($sourceFile, $mappingRulesFile, $resultFile){
        $omekaRecords = null;
        $pathInfo = pathinfo($sourceFile);
        $resultFile = $pathInfo['dirname'].'/'.$resultFile;

        $records = $this->getRecords($sourceFile);
        $mappingRules = $this->getMappingRules($mappingRulesFile);
        $mappedRecords = $this->mappRecords($records, $mappingRules);

        if(isset($mappedRecords) && sizeof($mappedRecords) > 0)
            $omekaRecords = json_encode($mappedRecords);

        file_put_contents($resultFile, $omekaRecords);

        return true;
    }

    /**
     * Read mapping rules file and generate mapping rules
     * @param $rulesFile
     * @return array
     */
    function getMappingRules($rulesFile){
        $mappingRules = array();
        $mappingParser = new mappingRulesParser();
        $row = 1;
        if (($handle = fopen($rulesFile, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $mappingRules[] = $mappingParser->mappingCommandParser($data, $row, $handle);
                $row++;
            }
            fclose($handle);
        }
        return $mappingRules;
    }

    /**
     * @param $records
     * @param $mappingRules
     * @return array|null
     */
    function mappRecords($records, $mappingRules){
        if(!isset($records,$mappingRules))
            return null;

        $mappedRecords = array();
        foreach($records as $item){
			$mappedRecords[] = $this->applyMappingRules($item, $mappingRules);
            $counter++;
        }
        return $mappedRecords;
    }

    /**
     * This function applies mapping rules on a record
     * @param $record
     * @param $mappingRules
     * @return omekaRecord
     */
    function applyMappingRules($record, $mappingRules){
        $omekaJsonRecord = new omekaRecord();
        $mappingParser = new mappingRulesParser();

        /** Command which do not have source elements or have multiple source elements. */
        $noSourceValueCommands = array("PUT", "COMBINE", "CONDITION");

        /** Header elements e.g. featured, public. */
        $headerElements = array("public", "featured", "item_type", "collection", "tags");

        foreach($mappingRules as $rule){
            $rule->omekaElement = str_replace("\r\n",'', $rule->omekaElement);
            $rule->caElement = str_replace("\r\n",'', $rule->caElement);

            /**
             * Skip further processing if:
             * where command does not have source element or have multiple source elements
             * AND
             * element does not exist in the record
             *
             */
            if(!in_array($rule->command, $noSourceValueCommands) && !array_key_exists($rule->caElement, $record))
                continue;

            $isHeaderElement = false;
            if(in_array($this->getTargetElement($rule->omekaElement), $headerElements))
                $isHeaderElement = true;

            /** If CONDITION command, first evaluate conditions into mapping rule. */
            if($rule->command === 'CONDITION'){
                if(isset($rule->fields['conditions']) && sizeof($rule->fields['conditions']) > 0){
                    $elseCondition = isset($rule->fields['conditions'][1])? $rule->fields['conditions'][1] : null;
                    $conditionRule = $mappingParser->conditionParser($record, $rule->fields['conditions'][0], $elseCondition, 'CAJSON');
                    if(isset($conditionRule)){
                        $this->executeCommand($record, $conditionRule, $omekaJsonRecord, $isHeaderElement);
                    }
                }
            }
            else
                $this->executeCommand($record, $rule, $omekaJsonRecord, $isHeaderElement);
        }
        return $omekaJsonRecord;
    }

    /**
     * This is the core function for mapping records to omeka compliant records.
     * @param $record
     * @param $rule
     * @param $omekaJsonRecord
     * @param $isHeaderElement
     */
    function executeCommand($record, $rule, &$omekaJsonRecord, $isHeaderElement){
        switch($rule->command){
            case 'COPY':        /** Copies source value to the target field. */
                if(array_key_exists($rule->caElement, $record))
                    $value = $record[$rule->caElement];
                $this->addElement($omekaJsonRecord, $value, $rule->omekaElement, $isHeaderElement);
                break;

            /** Appends provided value at the end of the source value and assigns it to the target field. */
            case 'APPEND':
                if(array_key_exists($rule->caElement, $record))
                    $value = $record[$rule->caElement];
                if(isset($rule->fields['appendtext']) && strlen($rule->fields['appendtext']) > 0){
                    $valuetoAppend = $value.' '.$rule->fields['appendtext'];
                    $this->addElement($omekaJsonRecord, $valuetoAppend, $rule->omekaElement, $isHeaderElement);
                }
                break;

            /** Appends provided value at the beginning of the source value and assigns it to the target field. */
            case 'PREPEND':
                if(array_key_exists($rule->caElement, $record))
                    $value = $record[$rule->caElement];
                if(isset($rule->fields['appendtext']) && strlen($rule->fields['appendtext']) > 0){
                    $valuetoPrepend = $rule->fields['appendtext'].' '.$value;
                    $this->addElement($omekaJsonRecord, $valuetoPrepend, $rule->omekaElement, $isHeaderElement);
                }
                break;

            /** Splits provided value and assigns to the target field(s). */
            case 'SPLIT':
                if(array_key_exists($rule->caElement, $record))
                    $value = $record[$rule->caElement];
                if(isset($value)){
                    if(isset($rule->fields['splitby']) && strlen($rule->fields['splitby']) > 0)
                        $splitBy = $rule->fields['splitby'];
                    else
                        $splitBy = ' ';
						
                    // Convert html codes to html characters, and encode before splitting.
                    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
					
                    $splitValues = explode($splitBy, $value);

                    $omekaElements = explode(';', $rule->omekaElement);
                    $counter = 0;
                    foreach($splitValues as $item){
                        if(isset($omekaElements[$counter])){
                            $this->addElement($omekaJsonRecord, $item, $omekaElements[$counter], $isHeaderElement);
                        }
                        $counter++;
                    }
                }
                break;

            /** Combines values of multiple source fields and assigns it to the target field. */
            case 'COMBINE':
                $caElements = explode(';', $rule->caElement);
                $combinedValue = '';
                $firstElement = true;

                $totalElements = sizeof($caElements);
                $elementCounter = 0;
                foreach($caElements as $element){
                    if(array_key_exists($element, $record))
                        $value = $record[$element];

                    if(isset($value) && strlen($value) > 0){

                        if($elementCounter === $totalElements-1){
                            $elementValue = $rule->fields['separatorstart'].' '. $value. ' '.$rule->fields['separatorend'];
                        }
                        else
                            $elementValue = $value;

                        if($firstElement){
                            $combinedValue = $elementValue;
                            $firstElement = false;
                        }
                        else
                            $combinedValue .=' '. $elementValue;
                    }
                    $elementCounter++;
                }
                if(strlen($combinedValue) > 0)
                    $this->addElement($omekaJsonRecord, $combinedValue, $rule->omekaElement, $isHeaderElement);
                break;

            /** Limits the source value to an asked length and assigns it to the target field. */
            case 'LIMIT':
                if(array_key_exists($rule->caElement, $record))
                    $value = $record[$rule->caElement];
                if(isset($value)){
                    if(isset($rule->fields['limitto']) && is_numeric($rule->fields['limitto']) )
                        $value = substr($value, 0, $rule->fields['limitto']);

                    $this->addElement($omekaJsonRecord, $value, $rule->omekaElement, $isHeaderElement);
                }
                break;

            /** Puts(copy) the provided value to the target field. */
            case 'PUT':
                if(isset($rule->fields['puttext']) && strlen($rule->fields['puttext']) > 0){
                    $valuetoPut = str_replace('||', ',', $rule->fields['puttext']);
                    $this->addElement($omekaJsonRecord, $valuetoPut, $rule->omekaElement, $isHeaderElement);
                }
                break;

            /**
             * Replaces a certain string in the source value and assigns it to the target field.
             * The value to be replaced is provided in the mapping rule.
             */
            case 'REPLACE':
                if(array_key_exists($rule->caElement, $record))
                    $value = $record[$rule->caElement];
                if(isset($value)){
                    if(isset($rule->fields['replace'], $rule->fields['replaceby']) && strlen($rule->fields['replace']) > 0)
                        $value = str_replace($rule->fields['replace'], $rule->fields['replaceby'], $value);

                    $this->addElement($omekaJsonRecord, $value, $rule->omekaElement, $isHeaderElement);
                }
                break;
        }
    }


    /**
     * @param $element
     * @return mixed
     */
    function getTargetElement($element){
        $parts = explode("::",$element);
        if(is_array($parts))
            return end($parts);
        else
            return $element;
    }

    /**
     * This function adds value to an omeka record
     * @param $omekaJsonRecord
     * @param $value
     * @param $omekaElement
     * @param $isHeaderElement
     */
    function addElement(&$omekaJsonRecord, $value, $omekaElement, $isHeaderElement){
        /** Do not proceed if value is null. */
        if(!isset($value))
            return;

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

                case 'tags';
                    $omekaJsonRecord->tags = $value;
                    break;					
            }
            return;
        }
        $valueToAdd = array();
        $isHtml = true;

        if(is_object($value)){  /** An object. */
            $objectValue = $this->objToArray($value);
            $flatValues = $this->flattenValue($objectValue);

            if(is_array($flatValues)){
                foreach($flatValues as $item)
                    $valueToAdd [] = $item;
            }
            else
                $valueToAdd [] = $flatValues;
        }
        else /** Not an object. */
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

    /**
     * Converts objects of array, iterates untill a non-object value is reached
     * @param $value
     * @return array
     */
    function objToArray($value){
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

    /**
     * @param $value
     * @return array
     */
    function flattenValue($value){
        if(!is_array($value))
            return $value;
        $returnValues = array();
        array_walk_recursive($value, function($a) use (&$returnValues) { $returnValues[] = $a; });
        return $returnValues;
    }

    /**
     * @param $recordFile
     * @return array
     */
    function getRecords($recordFile){
        $records = array();
        $data = file_get_contents ($recordFile);
        $jsonArray = json_decode($data);

        if(!isset($jsonArray->{'results'}))
            return null;

        $recordsJson = $jsonArray->{'results'};
        foreach($recordsJson as $item){
            $records[] = $this->objectKeyValues($item);
        }
        return $records;
    }

    /**
     * @param $array
     * @return array
     */
    function objectKeyValues($array){
        $result = array();
        foreach ($array as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }


} 