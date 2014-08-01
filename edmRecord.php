<?php
/**
 * Created by PhpStorm.
 * User: NaeemM
 * Date: 21/05/14
 */

class edmRecord {

    public $providedCHO;
    public $aggregation;
    public $webResources;

    public function __construct(){
        $this->providedCHO = new providedCHO();
        $this->aggregation = new Aggregation();
        $this->webResources = new webResources();
    }

    function findRecordByID($records, $id) {
        foreach($records as $record){
            if($record->providedCHO->providedCHOId === $id)
                return $record;
        }
        return null;
    }

    function findRecordPosition($records, $id) {
        $counter = 0;
        foreach($records as $record){
            if($record->providedCHO->providedCHOId === $id)
                return $counter;
            $counter++;
        }
        return null;
    }

    function addRecordValue($edmElement, $value, $marcElement){
        switch($edmElement){
            case 'edm:object':
                if(!isset($this->aggregation->fields['edm:object'][0])){
                    $this->aggregation->fields['edm:object'] = $value;
                    unset($this->aggregation->fields['edm:object'][1]);
                }

                break;
            case 'edm:isShownBy':
            case 'edm:isShownAt':
                if(isset($value[0]))
                    $this->aggregation->fields[$edmElement] = $value[0];
                $this->aggregation->fields['edm:hasView'][]['resource'] = $value;

                if(!isset($this->aggregation->fields['edm:object'][0])){
                    $this->aggregation->fields['edm:object'] = $value;
                    unset($this->aggregation->fields['edm:object'][1]);
                }

                $this->webResources->webResourceId[] = $value;
                break;

            case 'edm:rights':
                $this->aggregation->fields[$edmElement] = $value;
                break;

            case 'dc:rights':
                if (strpos($marcElement,'marc845') !== false || strpos($marcElement,'marc856') !== false) {
                    $this->webResources->fields[$edmElement][] = $value;
                }
                break;

            case 'edm:provider':
            case 'edm:dataProvider':
                $this->aggregation->fields[$edmElement] = $value;
                break;

/*            case 'edm:provider':
                break;*/

            default;
                if (strpos($marcElement,'marc845') === false && strpos($marcElement,'marc856') === false) {
                    $this->providedCHO->fields[$edmElement][] = $value;
                }


        }
    }
}

class providedCHO {
    public $providedCHOId;
    public $fields = array();

}

class Aggregation {
    public $aggregationId;
    public $aggrigatedCHO;
    public $fields = array();

}

class webResources {
    public $webResourceId;
    public $fields = array();

}