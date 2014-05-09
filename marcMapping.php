<?php

class marcMapping {

    function copyMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds){

        $dom = $this->loadXML($existingXML);

        $params = $this->findElements($existingElementPath, $dom);
        $tagCode = $this->findTagCode($existingElementPath);
        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            if ($existingElementPath === 'marc001')
                $nodeValue = $param->nodeValue;
            else
                $nodeValue = $this->findElementValue($param, $tagCode);

            $elementFound = $this->concernedRecord($param->getNodePath());
            if(isset($nodeValue, $elementFound)){
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$elementFound-1], $nodeValue);
                $i++;
            }

        }
    }

    function appendMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds, $appendText){
        $dom = $this->loadXML($existingXML);

        $params = $this->findElements($existingElementPath, $dom);
        $tagCode = $this->findTagCode($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            $nodeValue = $this->findElementValue($param, $tagCode);

            $elementFound = $this->concernedRecord($param->getNodePath());
            if(isset($nodeValue, $elementFound)){
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$elementFound-1], $nodeValue. ' '.$appendText);
                $i++;
            }

        }
    }

    function putMapping($edmElement, $xmlEDM, $edmRecordIds, $valueToPut){
        $changedValueToPut = str_replace('||', ',', $valueToPut);
        foreach($edmRecordIds as $edmRecord){
            $this->addXMLNode($xmlEDM, $edmElement,  $edmRecord, $changedValueToPut);
        }
    }

    function replaceMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds, $replace, $replaceBy){
        $dom = $this->loadXML($existingXML);
        $params = $this->findElements($existingElementPath, $dom);
        $tagCode = $this->findTagCode($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            $nodeValue = $this->findElementValue($param, $tagCode);

            $elementFound = $this->concernedRecord($param->getNodePath());
            if(isset($nodeValue, $elementFound)){
                $replacedValue = str_replace($replace, $replaceBy, $nodeValue);
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$elementFound-1], $replacedValue);
                $i++;
            }
        }
    }

    function splitMapping($existingXML, $existingElementPath, $edmElements, $xmlEDM, $edmRecordIds, $splitBy){
        $dom = $this->loadXML($existingXML);
        if($splitBy == '') $splitBy = ' ';
        $elements = explode(';', $edmElements);

        $params = $this->findElements($existingElementPath, $dom);
        $tagCode = $this->findTagCode($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            $nodeValue = $this->findElementValue($param, $tagCode);

            $elementFound = $this->concernedRecord($param->getNodePath());
            if(isset($nodeValue, $elementFound)){
                $splitData = explode($splitBy, $nodeValue);  // split first found element
                foreach($splitData as $item){
                    for($j = 0; $j<sizeof($elements); $j++)
                        $this->addXMLNode($xmlEDM, $elements[$j],  $edmRecordIds[$elementFound-1], $item);
                }
                $i++;
            }
        }
    }

    function limitMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds, $limitTo){
        $dom = $this->loadXML($existingXML);
        $params = $this->findElements($existingElementPath, $dom);
        $tagCode = $this->findTagCode($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            $nodeValue = $this->findElementValue($param, $tagCode);

            $elementFound = $this->concernedRecord($param->getNodePath());
            if(isset($nodeValue, $elementFound)){
                $limited = substr($nodeValue, 0, $limitTo);
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$elementFound-1], $limited);
                $i++;
            }
        }
    }


    function combineMapping($existingXML, $existingElementsPath, $edmElement, $xmlEDM, $edmRecordIds){
        $dom = $this->loadXML($existingXML);
        $edmRecordCounter = 1;
        foreach($edmRecordIds as $edmRecord){
            $elements = explode(';', $existingElementsPath);
            $combinedValue = '';
            $firstValue = true;
            foreach($elements as $element){
                $params = $this->findElements($element, $dom);
                $tagCode = $this->findTagCode($element);
                foreach ($params as $param) {       //iterates for each record
                    $nodeValue = $this->findElementValue($param, $tagCode);

                    $elementFound = $this->concernedRecord($param->getNodePath());
                    if(isset($nodeValue, $elementFound)){

                        if($elementFound == $edmRecordCounter){
                            if($firstValue === true){
                                $combinedValue .= $nodeValue;
                                $firstValue = false;
                            }
                            else{
                                $combinedValue .= ' ' . $nodeValue;
                            }
                        }
                    }
                }
            }
            $this->addXMLNode($xmlEDM, $edmElement,  $edmRecord, $combinedValue);
            $edmRecordCounter ++;
        }
    }

    function concernedRecord($nodePath){
        preg_match_all('/\d+/', $nodePath, $recordFoundIn);
        if(isset($recordFoundIn[0][0]))
            return $recordFoundIn[0][0];
    }

    function loadXML($xmlFile){
        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($xmlFile);
        return $dom;
    }

    function findElements($existingElementPath,$dom){
        $marcCode = explode('marc',$existingElementPath);
        if(isset($marcCode[1])){
            if($marcCode[1] === '001')  //controlefiled
                return $dom->getElementsByTagName('controlefield');
            else                       //datafield
               return $dom->getElementsByTagName('datafield');
        }
    }

    function findElementValue($param, $tagCode){
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

    function findTagCode($existingElementPath){
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

    function addXMLNode($xmlFile, $appendElement, $edmRecordId, $value){

        $domDoc = new DOMDocument();

        $domDoc->formatOutput = true;
        $domDoc->preserveWhiteSpace = false;

        $domDoc->load($xmlFile);

        $appendElement = str_replace(array("\r","\n"), '', $appendElement); //remove any empty line at the end of the element name

        $childNode = $domDoc->createElement($appendElement); //create node element
        $nodeValue = $domDoc->createTextNode($value);               //create value item


        $params = $domDoc->getElementsByTagName('ProvidedCHO');
        foreach ($params as $param) {

            if($edmRecordId == $param->getAttribute('rdf:about')){

                if($appendElement == 'edm:object' || $appendElement == 'edm:isShownBy' || $appendElement == 'edm:isShownAt'
                    || $appendElement == 'edm:dataProvider' || $appendElement == 'edm:provider'){

                    $value = str_replace('&','&amp;', $value);

                    //add web resource if isshownby or isshownat
                    if($appendElement == 'edm:isShownBy' || $appendElement == 'edm:isShownAt')
                        $this->addWebResource($domDoc, $param, $value);

                    //add web resource in ore:Aggregation element
                    $aggregators = $domDoc->getElementsByTagName('Aggregation');
                    foreach($aggregators as $aggregator){
                        if($aggregator->getAttribute('rdf:about') == $edmRecordId.'-aggregation')
                        {

                            //add nodes in aggregator
                            $aggregationNode = $aggregator->appendChild($childNode);
                            $aggregationNode->appendChild($nodeValue);

                            if($appendElement == 'edm:isShownBy' || $appendElement == 'edm:isShownAt'){
                                //add hasview for isshownby and isshownat
                                $aggNode = $domDoc->createElement('edm:hasView');
                                $attAggNode = $domDoc->createAttribute('rdf:resource');
                                $attAggNode->value = $value;
                                $aggNode->appendChild($attAggNode);
                                $aggregator->appendChild($aggNode);

                                //first image to edm:object in aggregationi.e. if edm:object does not exist, add it and asign value to it
                                $edmObject = $aggregator->getElementsByTagName('object');
                                if($edmObject->length == 0){
                                    $objectNode = $domDoc->createElement('edm:object');
                                    $objectValue = $domDoc->createTextNode($value);
                                    $object = $aggregator->appendChild($objectNode);
                                    $object->appendChild($objectValue);
                                }
                            }
                        }
                    }

                }else{
                    if($appendElement ==  'dc:rights'){ // add dc rights to webresources
                        $this->addWebResourceChild($domDoc, $param, null, $appendElement, $value);
                    }else{
                        $child = $param->appendChild($childNode);            //add newley created node to root or the given node
                        $child->appendChild($nodeValue);                            //assign value to the newly created node element
                    }

                }

            }
        }
        $domDoc->save($xmlFile);
    }

    function addWebResource($domDoc, $providedChoNode, $value){

        $resourceNode = $domDoc->createElement('edm:WebResource'); //create resource element
        $attResource = $domDoc->createAttribute('rdf:about');            //create resource attribute
        $attResource->value = $value;                                    //assigne value to resource
        $resourceNode->appendChild($attResource);                        //add attribute to resource element
        $providedChoNode->appendChild($resourceNode);                           //add resource element to root
    }

    function addWebResourceChild($domDoc, $providedChoNode, $webResouceNodeId, $child, $value){

        $childNode = $domDoc->createElement($child); //create node element
        $nodeValue = $domDoc->createTextNode($value);               //create value item

        $webResources = $providedChoNode->getElementsByTagName('WebResource');
        if(!isset($webResouceNodeId)){ 

            foreach($webResources as $webResource){
                $webResourceNode = $webResource->appendChild($childNode);
                $webResourceNode->appendChild($nodeValue);
            }

        }


    }

    function initEDMRecord($existingXML, $xmlEDM){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);

        $domDoc = new DOMDocument();
        $domDoc->load($xmlEDM);

        $edmRecordID = array();

        $params = $dom->getElementsByTagName('record');
        for($i=0; $i<$params->length; $i++){
            $edmRecordID[] = $this->edmRecordId();

            $rootNode = $domDoc->documentElement;

            //create empty edm record
//            $childNode = $domDoc->createElementNS(' ', 'edm:ProvidedCHO'); //create node element
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

//        $attRdf = $domDoc->createAttribute('xmlns:rdf');
//        $attRdf->value = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
//        $rdfNode->appendChild($attRdf);

            $attSkos = $domDoc->createAttribute('xmlns:skos');
            $attSkos->value = 'http://www.w3.org/2004/02/skos/core#';
            $rdfNode->appendChild($attSkos);

            $attWgs = $domDoc->createAttribute('xmlns:wgs84');
            $attWgs->value = 'http://www.w3.org/2003/01/geo/wgs84_pos#';
            $rdfNode->appendChild($attWgs);

            $attXsi = $domDoc->createAttribute('xmlns:xsi');
            $attXsi->value = 'http://www.w3.org/2001/XMLSchema-instance';
            $rdfNode->appendChild($attXsi);

//        $attXsiLocation = $domDoc->createAttribute('xsi:schemaLocation');
//        $attXsiLocation->value = 'http://www.w3.org/1999/02/22-rdf-syntax-ns# EDM.xsd';
//        $rdfNode->appendChild($attXsiLocation);


            $childNode = $domDoc->createElement('edm:ProvidedCHO'); //create node element
            $attAbout = $domDoc->createAttribute('rdf:about');
            $attAboutText = $domDoc->createTextNode($edmRecordID[$i]);
            $attAbout->appendChild($attAboutText);
            $childNode->appendChild($attAbout);
            //$rootNode->appendChild($childNode); //append edm record to root element

            ///TEMP START
            $rdfNode->appendChild($childNode);
            $rootNode->appendChild($rdfNode); //append edm record to root element
            ///TEMP END


            //create aggregation node with edm:aggregatedCHO element
//            $this->createAggregationNode($domDoc, $rootNode, $edmRecordID[$i]);

            //TEMP START
            $this->createAggregationNode($domDoc, $rdfNode, $edmRecordID[$i]);
            //TEMP END

            $domDoc->save($xmlEDM);
        }
        return $edmRecordID;
    }

    function createAggregationNode($domDoc, $rootNode, $edmRecordID){
//        $aggrigationNode = $domDoc->createElementNS(' ', 'ore:Aggregation'); //create Aggregation element
        $aggrigationNode = $domDoc->createElement('ore:Aggregation'); //create Aggregation element
        $attAggAbout = $domDoc->createAttribute('rdf:about');
        $attAggAboutText = $domDoc->createTextNode($edmRecordID.'-aggregation');
        $attAggAbout->appendChild($attAggAboutText);
        $aggrigationNode->appendChild($attAggAbout);

        $aggCHONode = $domDoc->createElement('edm:aggregatedCHO'); //create aggregatedCHO element
        $attAggCHO = $domDoc->createAttribute('rdf:about');            //create aggregatedCHO attribute
        $attAggCHO->value = $edmRecordID;                                    //assigne value to attribute
        $aggCHONode->appendChild($attAggCHO);                        //add attribute to aggregatedCHO element
        $aggrigationNode->appendChild($aggCHONode);

        $rootNode->appendChild($aggrigationNode); //append aggrigation node to root element

    }

    function edmRecordId(){
        return md5(uniqid(rand(), true));
    }

    function nodeValue($existingXML, $existingElementPath){
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


}