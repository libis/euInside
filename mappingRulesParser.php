<?php
/**
 * User: NaeemM
 * Date: 23/09/14
 */
require_once("omekaMappingRules.php");

class mappingRulesParser {

    function mappingCommandParser($data, $row, $handle){
        $mappingRule = new omekaMappingRules();
        switch(strtoupper($data[0])){
            case 'COPY':
                $mappingRule->command = 'COPY';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[2];
                $mappingRule->fields = null;
                break;

            case 'APPEND':
                $mappingRule->command = 'APPEND';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[3];
                $mappingRule->fields['appendtext'] = $data[2];
                break;

            case 'PREPEND':
                $mappingRule->command = 'PREPEND';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[3];
                $mappingRule->fields['appendtext'] = $data[2];
                break;

            case 'SPLIT':
                $mappingRule->command = 'SPLIT';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[3];
                $mappingRule->fields['splitby'] = $data[2];
                break;

            case 'SPLITTOONE':
                $mappingRule->command = 'SPLITTOONE';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[3];
                $mappingRule->fields['splitby'] = $data[2];
                break;

            case 'COMBINE':
                $mappingRule->command = 'COMBINE';
                $mappingRule->caElement = $data[2];
                $mappingRule->omekaElement = $data[4];
                $mappingRule->fields['separatorstart'] = $data[1];
                $mappingRule->fields['separatorend']   = $data[3];
                break;

            case 'LIMIT':
                $mappingRule->command = 'LIMIT';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[3];
                $mappingRule->fields['limitto'] = $data[2];
                break;

            case 'PUT':
                $mappingRule->command = 'PUT';
                $mappingRule->caElement = null;
                $mappingRule->omekaElement = $data[2];
                $mappingRule->fields['puttext'] = $data[1];
                break;

            case 'REPLACE':
                $mappingRule->command = 'REPLACE';
                $mappingRule->caElement = $data[1];
                $mappingRule->omekaElement = $data[4];
                $mappingRule->fields['replace'] = $data[2];
                $mappingRule->fields['replaceby'] = $data[3];
                break;

            case 'CONDITION':
                $conditionData = array();
                for($i=$row; $i<1000; $i++){
                    $lineData = fgets($handle);

                    if (strpos($lineData,"}")!== false)
                        break;
                    $conditionData[] = $lineData;
                }
                $mappingRule->command = 'CONDITION';
                $mappingRule->fields['conditions'] = $conditionData;
                break;

            default:
                break;
        }
        if(isset($mappingRule->command))
            return $mappingRule;
    }

    function conditionParser($recordData, $ifConditionData, $elseConditionData, $sourceFormat){     //for all formats

        $ifCondition = $ifConditionData;
        $ifPosition = strpos($ifCondition,'IF');
        if($ifPosition === false) return; //IF condition not found

        $ifData = explode('DO', str_replace(array( '[', ']' ), '', substr($ifCondition, $ifPosition+2)));
        $ifConditionPart = explode(',', $ifData[0]);
        $ifDoData = explode(',', substr(trim($ifData[1]),1,-1));



        $result = $this->conditionIf($recordData, $ifConditionPart, $sourceFormat);

        if(isset($result)){
            switch($result){
                case 1: //condition positive
                    return $this->mappingCommandParser($ifDoData, null, null);
                    break;
                case 2: //condition negative
                    if(isset($elseConditionData)){
                        $elseData = explode(',', trim(str_replace(array( 'ELSE[', ']' ), '', $elseConditionData)));
                        return $this->mappingCommandParser($elseData, null, null);
                    }
                    break;
            }
        }
    }

    function conditionIf($record, $ifData, $sourceFormat){
        $conditionType = strtoupper($ifData[1]); //e.g EQUAL
        $conditionValue =  $ifData[2];
        $conditionValue =  trim(str_replace('||', ',', $conditionValue),'"');

        switch($sourceFormat){
            case 'CAJSON':
                if(array_key_exists($ifData[0], $record))
                    $elementVaule = $record[$ifData[0]];
                break;
        }

        if(isset($elementVaule) && sizeof($elementVaule) > 0){
            switch($conditionType){
                case 'EQUALS':          //equals
                    if(strcmp($conditionValue, $elementVaule) == 0)
                        return 1;  //values are equal, means condition positive
                    else
                        return 2;//values are not equal, condition not positive

                    break;

                case 'NOT EQUAL':       //not equal
                    if(strcmp($conditionValue, $elementVaule) != 0)
                        return 1;  //values are not equal, means condition positive
                    else
                        return 2;//values are  equal, condition not positive

                    break;

                case 'CONTAINS':        //case sensitive contains
                    if (strpos($elementVaule,$conditionValue) !== false)
                        return 1;  //contains value, means condition positive
                    else
                        return 2;//does not contain value, condition not positive

                    break;

                case 'CONTAIN NOT':     //case sensitive contain not
                    if (strpos($elementVaule,$conditionValue) !== false)
                        return 2;  //contains value, means condition negative
                    else
                        return 1;//does not contain value, condition positive

                    break;

                case 'ICONTAINS':   //case insensitive contains
                    if (strpos(strtolower($elementVaule),strtolower($conditionValue)) !== false)
                        return 1;  //contains value, means condition positive
                    else
                        return 2;//does not contain value, condition not positive

                    break;

                case 'ICONTAIN NOT':        //case insensitive contain not
                    if (strpos(strtolower($elementVaule),strtolower($conditionValue)) !== false)
                        return 2;  //contains value, means condition negative
                    else
                        return 1;//does not contain value, condition positive

                    break;
            }
        }
        return 0; //invalid condition type
    }
}