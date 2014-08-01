<?php
/**
 * User: NaeemM
 * Date: 20/05/14
 */

class marcRecord {
    public $edm;
    public $leader;
    public $dataField = array();
    public $controlField;
    public $controlFieldTag;


    public function addDataField($field){
        array_push($this->dataField, $field);
    }

    public function getValueByMarcCode($marcCode){
        switch($marcCode){
            case 'marc001';
                return array($this->controlField);
                break;

            case 'leader';
                return array($this->leader);
                break;

            default;
                $foundValues = array();
                foreach($this->dataField as $field){
                    $tag = $field->tag;
                    $code = $field->subField->code;
                    if($marcCode === 'marc'.$tag.$code)
                        $foundValues[] = $field->subField->value;
                }

                return $foundValues;
        }
    }

} 