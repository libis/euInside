<?php

require_once("marcRecord.php");
require_once("edmRecord.php");
require_once("marcDataField.php");

class marcMapping {
    private $aggregatorEDMValues = array();

    function loadXML($xmlFile){     //old: LINKED WITH IF
        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($xmlFile);
        return $dom;
    }

    function nodeValue($existingXML, $existingElementPath){     //old: LINKED WITH IF
        $dom = $this->loadXML($existingXML);
        $params = $this->findElements($existingElementPath, $dom);
        $tagCode = $this->findTagCode($existingElementPath);

        $foundValues = array();

        foreach ($params as $param) {       //iterates for each record
            $nodeValue = $this->findElementValue($param, $tagCode);
            if(isset($nodeValue))
                $foundValues[] = $nodeValue;
        }
        return $foundValues;
    }

    function findElements($existingElementPath,$dom){     //old: LINKED WITH IF
        $marcCode = explode('marc',$existingElementPath);
        if(isset($marcCode[1])){
            if($marcCode[1] === '001')  //controlefiled
                return $dom->getElementsByTagName('controlefield');
            else                       //datafield
               return $dom->getElementsByTagName('datafield');
        }
    }

    function findElementValue($param, $tagCode){       //old: LINKED WITH IF
        if(isset($tagCode[0])){
            if ($param->getAttribute('tag') === $tagCode[0])
            {
                if($tagCode[0] === '001')   //control field
                    return $param->nodeValue;
                else{                       //datafield(subfield)
                    $params2 = $param->getElementsByTagName('subfield');
                    foreach($params2 as $param2){
                        if(isset($tagCode[1])){
                            if ($param2->getAttribute('code') === $tagCode[1])
                                return $param2->nodeValue;
                        }
                        else
                            return $param2->nodeValue;
                    }
                }

            }
        }

    }

    function findTagCode($existingElementPath){     //old: LINKED WITH IF
        if(strlen($existingElementPath) === 7){
            $tag = substr($existingElementPath,-3);
            return array($tag);
        }

        if(strlen($existingElementPath) === 8){
            $tag = substr($existingElementPath, -4,-1);
            $code = substr($existingElementPath,-1);
            return array($tag, $code);
        }

    }

    function initEDMXML($xmlFile){

        libxml_use_internal_errors(true);

        $domDoc = new DOMDocument('1.0', 'UTF-8');
        $rootElt = $domDoc->createElement('list');

        $domDoc->appendChild($rootElt);
        $domDoc->save($xmlFile);
        return $xmlFile;
    }

    public function edmRecord($marcRecord, $mappingRules){
        $edmRecord =  new edmRecord();
        $id = $this->edmRecordId();         //generate id for edm record

        $edmRecord->providedCHO->providedCHOId = $id;
        $edmRecord->aggregation->aggrigatedCHO = $id;
        $edmRecord->aggregation->aggregationId = $id.'-aggregation';

        //for each rule
        $recordEmpty = true;
        foreach($mappingRules as $rule){
            $rule->edmElement = str_replace("\r\n",'', $rule->edmElement);

            switch($rule->command){
                case 'COPY':
                    $value = $marcRecord->getValueByMarcCode($rule->marcElement);
                    if(isset($value)){
                        $edmRecord->addRecordValue($rule->edmElement, $value, $rule->marcElement);
                        $recordEmpty = false;
                    }
                    break;

                case 'APPEND':
                    $append_value = "";
                    $value = $marcRecord->getValueByMarcCode($rule->marcElement);
                    if(isset($value)){

                        if(is_array($value)){
                            foreach($value as $item){
                                if(isset($rule->fields['appendtext'])){
                                    $append_value = $item.' '.$rule->fields['appendtext'];
                                    $edmRecord->addRecordValue($rule->edmElement, $append_value, $rule->marcElement);
                                    $recordEmpty = false;
                                }
                            }
                        }
                        else{
                            if(isset($rule->fields['appendtext'])){
                                $append_value = $value.' '.$rule->fields['appendtext'];
                                $edmRecord->addRecordValue($rule->edmElement, $append_value, $rule->marcElement);
                                $recordEmpty = false;
                            }
                        }

                    }
                    break;

                case 'SPLIT':
                    $value = $marcRecord->getValueByMarcCode($rule->marcElement);
                    $edmElements = explode(';', $rule->edmElement);
                    if(isset($value)){
                        if(isset($rule->fields['splitby']) && strlen($rule->fields['splitby']) > 0)
                            $splitBy = $rule->fields['splitby'];
                        else
                            $splitBy = ' ';
                        $splitValue = explode($splitBy, $value);
                        $counter = 0;
                        foreach($splitValue as $item){
                            if(isset($edmElements[$counter])){
                                $edmRecord->addRecordValue($edmElements[$counter], $item, $rule->marcElement);
                                $recordEmpty = false;
                            }
                            $counter++;
                        }
                    }
                    break;

                case 'COMBINE':
                    $marcElements = explode(';', $rule->marcElement);
                    $combinedValue = '';
                    $firstElement = true;
                    foreach($marcElements as $marcElement){
                        $value = $marcRecord->getValueByMarcCode($marcElement);
                        if(isset($value[0])){
                            if($firstElement){
                                $combinedValue = $value[0];
                                $firstElement = false;
                            }
                            else
                                $combinedValue .=' '. $value[0];
                        }
                    }
                    if(strlen($combinedValue) > 0){
                        $edmRecord->addRecordValue($rule->edmElement, $combinedValue, $rule->marcElement);
                        $recordEmpty = false;
                    }
                    break;

                case 'LIMIT':
                    $value = $marcRecord->getValueByMarcCode($rule->marcElement);
                    if(isset($value)){
                        if(isset($rule->fields['limitto']) && is_numeric($rule->fields['limitto']))
                            $value = substr($value, 0, $rule->fields['limitto']);

                        $edmRecord->addRecordValue($rule->edmElement, $value, $rule->marcElement);
                        $recordEmpty = false;
                    }
                    break;

                case 'PUT':

                    if(isset($rule->fields['puttext']) && strlen($rule->fields['puttext']) > 0){
                        $valuetoPut = str_replace('||', ',', $rule->fields['puttext']);
                        $edmRecord->addRecordValue($rule->edmElement, $valuetoPut, $rule->marcElement);
                        $recordEmpty = false;
                    }
                    break;

                case 'REPLACE':
                    $value = $marcRecord->getValueByMarcCode($rule->marcElement);
                    if(isset($value)){
                        if(isset($rule->fields['replace']) && isset($rule->fields['replaceby']))
                            $value = str_replace($rule->fields['replace'], $rule->fields['replaceby'], $value);

                        $edmRecord->addRecordValue($rule->edmElement, $value, $rule->marcElement);
                        $recordEmpty = false;
                    }
                    break;



            }//switch end
        }

        if(!$recordEmpty)
            return $edmRecord;
    }

    function edmRecordId(){
        return md5(uniqid(rand(), true));
    }

    public function getMarcRecords($marcXMLFile){
        $records = array();
        $xmlRecords = simplexml_load_file($marcXMLFile);
        foreach($xmlRecords->record as $item){
            $marcRecord = new marcRecord();
            if(isset($item->leader))
                $marcRecord->leader = (string)$item->leader;

            if(isset($item->controlefield)){
                $marcRecord->controlField = (string)$item->controlefield;
                $marcRecord->controlFieldTag = '001';
            }

            if(isset($item->datafield)){
                foreach($item->datafield as $subItem){
                    $tempDataField= new marcDataField();

                    $attributes = $subItem->attributes();

                    if(isset($attributes->tag))
                        $tempDataField->tag = (string)$attributes->tag;

                    if(isset($attributes->ind1))
                        $tempDataField->ind1 = (string)$attributes->ind1;

                    if(isset($attributes->ind2))
                        $tempDataField->ind2 = (string)$attributes->ind2;

                    $subNode = $subItem->subfield;
                    if(isset($subNode)){
                        $tempDataField->subField->value = (string)$subNode;
                        $tempDataField->subField->code = (string)$subNode->attributes();
                    }

                    $marcRecord->addDataField($tempDataField);
                    unset($tempDataField);
                }
            }
            $records[]=$marcRecord;
            unset($marcRecord);
        }

        return $records;
    }

    function initEDMRDF($domDoc, $edmXMLFile){
        $rdfNode = $domDoc->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#','rdf:RDF');

        $attDc = $domDoc->createAttribute('xmlns:dc');
        $attDc->value = 'http://purl.org/dc/elements/1.1/';
        $rdfNode->appendChild($attDc);

        $attDcTerms = $domDoc->createAttribute('xmlns:dcterms');
        $attDcTerms->value = 'http://purl.org/dc/terms/';
        $rdfNode->appendChild($attDcTerms);

        $attEdm = $domDoc->createAttribute('xmlns:edm');
        $attEdm->value = 'http://www.europeana.eu/schemas/edm/';
        $rdfNode->appendChild($attEdm);

        $attEnrichment = $domDoc->createAttribute('xmlns:enrichment');
        $attEnrichment->value = 'http://www.europeana.eu/schemas/edm/enrichment/';
        $rdfNode->appendChild($attEnrichment);

        $attOre = $domDoc->createAttribute('xmlns:ore');
        $attOre->value = 'http://www.openarchives.org/ore/terms/';
        $rdfNode->appendChild($attOre);

        $attOwl = $domDoc->createAttribute('xmlns:owl');
        $attOwl->value = 'http://www.w3.org/2002/07/owl#';
        $rdfNode->appendChild($attOwl);

        $attSkos = $domDoc->createAttribute('xmlns:skos');
        $attSkos->value = 'http://www.w3.org/2004/02/skos/core#';
        $rdfNode->appendChild($attSkos);

        $attWgs = $domDoc->createAttribute('xmlns:wgs84');
        $attWgs->value = 'http://www.w3.org/2003/01/geo/wgs84_pos#';
        $rdfNode->appendChild($attWgs);

        $attXsi = $domDoc->createAttribute('xmlns:xsi');
        $attXsi->value = 'http://www.w3.org/2001/XMLSchema-instance';
        $rdfNode->appendChild($attXsi);

        return $rdfNode;


    }

    function writeEDMRecord($domDoc, $edmXMLFile, $edmRecord){
        $rootNode = $domDoc->documentElement;

        $rdfNode = $this->initEDMRDF($domDoc, $edmXMLFile);

        //add provided CHO elements
        $providedCHONode = $this->addProvidedCHO($domDoc, $edmRecord->providedCHO);
        $rdfNode->appendChild($providedCHONode);

        //add aggregation elements
        $aggregatioNode = $this->addAggregation($domDoc, $edmRecord->aggregation);
        $rdfNode->appendChild($aggregatioNode);

        //add webresource elements
        foreach($edmRecord->webResources->webResourceId as $resource){
            $webResourceNodes = $this->addWebResources($domDoc, $resource, $edmRecord->webResources->fields);

            foreach($webResourceNodes as $resourceNode){

                $rdfNode->appendChild($resourceNode);
            }
        }
        $rootNode->appendChild($rdfNode);   //append edm record to root element
    }

    public function addProvidedCHO($domDoc, $providedCHO){
        $providedCHONode = $domDoc->createElement('edm:ProvidedCHO');
        $attAbout = $domDoc->createAttribute('rdf:about');
        $attAboutText = $domDoc->createTextNode($providedCHO->providedCHOId);
        $attAbout->appendChild($attAboutText);
        $providedCHONode->appendChild($attAbout);
        foreach($providedCHO->fields as $element => $values){
            if(isset($element)){
                foreach($values as $item){

                    $isAttribute = false;
                    if($element === 'edm:currentLocation')  //value of these elements goes in attribute section
                        $isAttribute = true;

                    if(!is_array($item)){
                        if($isAttribute)
                            $this->addRecordNodeAttribute($domDoc, $providedCHONode, 'rdf:resource', $element, $item);
                        else
                            $this->addRecordNode($domDoc, $providedCHONode, $element, $item);
                    }
                    else{
                        foreach($item as $value){
                            if($isAttribute)
                                $this->addRecordNodeAttribute($domDoc, $providedCHONode, 'rdf:resource', $element, $value);
                            else
                                $this->addRecordNode($domDoc, $providedCHONode, $element, $value);
                        }
                    }
                }
            }
        }
        return $providedCHONode;
    }

    public function addAggregation($domDoc, $aggregation){
        $aggrigationNode = $domDoc->createElement('ore:Aggregation');       //create Aggregation element
        $attAggAbout = $domDoc->createAttribute('rdf:about');
        $attAggAboutText = $domDoc->createTextNode($aggregation->aggregationId);
        $attAggAbout->appendChild($attAggAboutText);
        $aggrigationNode->appendChild($attAggAbout);

        $aggCHONode = $domDoc->createElement('edm:aggregatedCHO');          //create aggregatedCHO element
        $attAggCHO = $domDoc->createAttribute('rdf:resource');                 //create aggregatedCHO attribute
        $attAggCHO->value = $aggregation->aggrigatedCHO;                    //assigne value to attribute
        $aggCHONode->appendChild($attAggCHO);                               //add attribute to aggregatedCHO element
        $aggrigationNode->appendChild($aggCHONode);

        foreach($aggregation->fields as $element => $values){
            $isAttribute = false;
            if($element === 'edm:isShownBy' || $element === 'edm:isShownAt' || $element === 'edm:rights'
                || $element === 'edm:object')   //value of these elements goes in attribute section
                $isAttribute = true;

            if(!is_array($values)){

                if($isAttribute)
                    $this->addRecordNodeAttribute($domDoc, $aggrigationNode, 'rdf:resource', $element, $values);
                else
                    $this->addRecordNode($domDoc, $aggrigationNode, $element, $values);
            }
            else{
                foreach($values as $item){
                    if(!is_array($item)){
                        if($isAttribute)
                            $this->addRecordNodeAttribute($domDoc, $aggrigationNode, 'rdf:resource', $element, $item);
                        else
                            $this->addRecordNode($domDoc, $aggrigationNode, $element, $item);

                    }
                    else{
                        foreach($item as $key => $value){
                            foreach($value as $subValue){
                                if($key === 'resource'){
                                    $this->addRecordNodeAttribute($domDoc, $aggrigationNode, 'rdf:resource', $element, $subValue);
                                }
                                else{
                                    if($isAttribute)
                                        $this->addRecordNodeAttribute($domDoc, $aggrigationNode, 'rdf:resource', $element, $subValue);
                                    else
                                        $this->addRecordNode($domDoc, $aggrigationNode, $element, $subValue);
                                }
                            }
                        }
                    }
                }
            }
        }
        return $aggrigationNode;
    }

    public function addRecordNode($domDoc,$addTo,$element,$value){
        $childNode = $domDoc->createElement($element);               //create node element
        $nodeValue = $domDoc->createTextNode($value);               //create value item
        $subNode = $addTo->appendChild($childNode);
        $subNode->appendChild($nodeValue);
    }

    public function addRecordNodeAttribute($domDoc, $addTo, $attribute, $element, $value){
        $childNode = $domDoc->createElement($element);
        $nodeAttribute = $domDoc->createAttribute($attribute);
        $attValue = $domDoc->createTextNode($value);
        $nodeAttribute->appendChild($attValue);
        $childNode->appendChild($nodeAttribute);
        $addTo->appendChild($childNode);
    }

    public function addWebResources($domDoc, $resource, $subFields){
        $webResourceNodes = array();
        if(is_array($resource)){
            foreach($resource as $item){
                $webResourceNode = $domDoc->createElement('edm:WebResource');
                $attAbout = $domDoc->createAttribute('rdf:about');
                $attAboutText = $domDoc->createTextNode($item);
                $attAbout->appendChild($attAboutText);
                $webResourceNode->appendChild($attAbout);

                if(isset($subFields)){
                    if(is_array($subFields)){
                        foreach($subFields as $key => $values){
                            if(!is_array($values)){
                                $this->addRecordNode($domDoc, $webResourceNode, $key, $values);
                            }
                            else{
                                foreach($values as $value){
                                    foreach($value as $subValue){
                                        $this->addRecordNode($domDoc, $webResourceNode, $key, $subValue);
                                    }

                                }
                            }
                        }
                    }
                }

                $webResourceNodes[] = $webResourceNode;
            }
        }

        return $webResourceNodes;
    }

}