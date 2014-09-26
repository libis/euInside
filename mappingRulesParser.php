<?php
/**
 * User: NaeemM
 * Date: 23/09/14
 */
require_once("omekaMappingRules.php");

class mappingRulesParser {

    ////parsing mapping commands
    function marcMappingCommandParser($data, $row, $handle){
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
                $mappingRule->caElement = $data[2];
                $mappingRule->omekaElement = $data[3];
                $mappingRule->fields['appendtext'] = $data[1];
                break;

            case 'SPLIT':
                $mappingRule->command = 'SPLIT';
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
                $mappingRule->marcElement = $data[1];
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
        //unset($mappingRule);

    }
}