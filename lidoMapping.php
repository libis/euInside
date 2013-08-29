<?php

class lidoMapping {

    function copyMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('lido');
        $xPathQuery = $this->pathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record

//            $entries = $xpath->query($param->getNodePath().$query);
            $entries = $xpath->query($param->getNodePath().$xPathQuery);

            foreach ($entries as $entry) {
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $entry->nodeValue);
            }
            $i++;
        }
    }

    function appendMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds, $appendText){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('lido');
        $xPathQuery = $this->pathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record

//            $entries = $xpath->query($param->getNodePath().$query);
            $entries = $xpath->query($param->getNodePath().$xPathQuery);
            foreach ($entries as $entry) {
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $entry->nodeValue. ' '.$appendText);
            }
            $i++;
        }
    }

    function putMapping($existingXML, $edmElement, $xmlEDM, $edmRecordIds, $valueToPut){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);

        $params = $dom->getElementsByTagName('lido');
        $changedValueToPut = str_replace('||', ',', $valueToPut);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
//            $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $valueToPut);
            $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $changedValueToPut);
            $i++;
        }
    }

    function limitMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds, $limitTo){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('lido');
        $xPathQuery = $this->pathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record

//            $entries = $xpath->query($param->getNodePath().$query);
            $entries = $xpath->query($param->getNodePath().$xPathQuery);
            foreach ($entries as $entry) {
                $limited = substr($entry->nodeValue, 0,$limitTo);
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $limited);
            }
            $i++;
        }
    }

    function replaceMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds, $replace, $replaceBy){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('lido');
        $xPathQuery = $this->pathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record

//            $entries = $xpath->query($param->getNodePath().$query);
            $entries = $xpath->query($param->getNodePath().$xPathQuery);
            foreach ($entries as $entry) {
                $replacedValue = str_replace($replace, $replaceBy, $entry->nodeValue);
                $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $replacedValue);
            }
            $i++;
        }
    }

    function combineMapping($existingXML, $existingElementsPath, $edmElement, $xmlEDM, $edmRecordIds){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $elements = explode(';', $existingElementsPath);

        $params = $dom->getElementsByTagName('lido');

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            $combinedValue = "";

            for($j = 0; $j<sizeof($elements); $j++){
                $xPathQuery = $this->pathQuery($elements[$j]);
                $entries = $xpath->query($param->getNodePath().$xPathQuery);
                foreach ($entries as $entry) {
                    $combinedValue = $combinedValue." ".$entry->nodeValue." ";
                }
            }

            $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $combinedValue);
            $i++;
        }
    }

    function splitMapping($existingXML, $existingElementPath, $edmElements, $xmlEDM, $edmRecordIds, $splitBy){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        if($splitBy == '') $splitBy = ' ';

//        $elements = explode(';', (trim($edmElements, '()')));
        $elements = explode(';', $edmElements);

        $params = $dom->getElementsByTagName('lido');
//        $query = $this->xPathQuery($existingElementPath);
        $xPathQuery = $this->pathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {       //iterates for each record
            $splitData = "";
//            $entries = $xpath->query($param->getNodePath().$query);
            $entries = $xpath->query($param->getNodePath().$xPathQuery);

            $valueNumber = 0;
            foreach ($entries as $entry) {
                if ($valueNumber >0)    break;
                $splitData = explode($splitBy, $entry->nodeValue);  // split first found element
            }

            for($j = 0; $j<sizeof($elements); $j++){

                if(isset($splitData[$j]))
                    $nodeValue = $splitData[$j];
                else
                    $nodeValue = '';

                $this->addXMLNode($xmlEDM, $elements[$j],  $edmRecordIds[$i], $nodeValue);
            }

            $i++;
        }
    }


    function pathQuery($elementPath){
        $prefix = 'lido:';

        $strElement = str_replace('/', '/'.$prefix, $elementPath);
        $strElement = str_replace('@', '[@', $strElement);

        $needle = "@";
        $lastPos = 0;
        $positions = array();


        while ($lastPos = strpos($strElement, $needle, $lastPos)) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + strlen($needle);
        }
        $addition = 0;
        $pathLenght = strlen($strElement);
        $strElementQueryPath = $strElement;
        foreach ($positions as $position) {

            for($i=$position; $i<$pathLenght; $i++){

                if($i+1 == $pathLenght){
                    $strElementQueryPath = substr_replace($strElementQueryPath, ']', $i+1, 1);
                    break;
                }
                if(substr($strElement,$i, 1) == '/'){
                    $strElementQueryPath = substr_replace($strElementQueryPath, ']', $i+$addition, 0);
                    $addition++;
                    $pathLenght = $pathLenght + $addition;
                    break;
                }
            }

        }

        return $strElementQueryPath;

    }

    function initEDMXML($xmlFile){

        libxml_use_internal_errors(true);

        $domDoc = new DOMDocument('1.0', 'UTF-8');

        $rootElt = $domDoc->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#','rdf:RDF');

        $attDc = $domDoc->createAttribute('xmlns:dc');
        $attDc->value = 'http://purl.org/dc/elements/1.1/';
        $rootElt->appendChild($attDc);

        $attDcTerms = $domDoc->createAttribute('xmlns:dcterms');
        $attDcTerms->value = 'http://purl.org/dc/terms/';
        $rootElt->appendChild($attDcTerms);

        $attEdm = $domDoc->createAttribute('xmlns:edm');
        $attEdm->value = 'http://www.europeana.eu/schemas/edm/';
        $rootElt->appendChild($attEdm);

        $attEnrichment = $domDoc->createAttribute('xmlns:enrichment');
        $attEnrichment->value = 'http://www.europeana.eu/schemas/edm/enrichment/';
        $rootElt->appendChild($attEnrichment);

        $attOre = $domDoc->createAttribute('xmlns:ore');
        $attOre->value = 'http://www.openarchives.org/ore/terms/';
        $rootElt->appendChild($attOre);

        $attOwl = $domDoc->createAttribute('xmlns:owl');
        $attOwl->value = 'http://www.w3.org/2002/07/owl#';
        $rootElt->appendChild($attOwl);

//        $attRdf = $domDoc->createAttribute('xmlns:rdf');
//        $attRdf->value = "http://www.w3.org/1999/02/22-rdf-syntax-ns#";
//        $rootElt->appendChild($attRdf);

        $attSkos = $domDoc->createAttribute('xmlns:skos');
        $attSkos->value = 'http://www.w3.org/2004/02/skos/core#';
        $rootElt->appendChild($attSkos);

        $attWgs = $domDoc->createAttribute('xmlns:wgs84');
        $attWgs->value = 'http://www.w3.org/2003/01/geo/wgs84_pos#';
        $rootElt->appendChild($attWgs);

        $attXsi = $domDoc->createAttribute('xmlns:xsi');
        $attXsi->value = 'http://www.w3.org/2001/XMLSchema-instance';
        $rootElt->appendChild($attXsi);

//        $attXsiLocation = $domDoc->createAttribute('xsi:schemaLocation');
//        $attXsiLocation->value = 'http://www.w3.org/1999/02/22-rdf-syntax-ns# EDM.xsd';
//        $rootElt->appendChild($attXsiLocation);

        $domDoc->appendChild($rootElt);
        $domDoc->save($xmlFile);
        return $xmlFile;
    }

    function addXMLNode($xmlFile, $appendElement, $edmRecordId, $value){

        $domDoc = new DOMDocument();

        $domDoc->formatOutput = true;
        $domDoc->preserveWhiteSpace = false;

        $domDoc->load($xmlFile);
        $xpath = new DOMXPath($domDoc);

        $appendElement = str_replace(array("\r","\n"), '', $appendElement); //remove any empty line at the end of the element name

//        $childNode = $domDoc->createElementNS(' ', $appendElement); //create node element
        $childNode = $domDoc->createElement($appendElement); //create node element
        $nodeValue = $domDoc->createTextNode($value);               //create value item


        $params = $domDoc->getElementsByTagName('ProvidedCHO');
        foreach ($params as $param) {

            if($edmRecordId == $param->getAttribute('rdf:about')){

                if($appendElement == 'edm:object'){
                    //1. add web resource
                    $this->addWebResource($domDoc, $value);
                    //2. add web resource in ore:Aggregation element
                    $aggregators = $domDoc->getElementsByTagName('Aggregation');
                    foreach($aggregators as $aggregator){
                        if($aggregator->getAttribute('rdf:about') == $edmRecordId.'-aggregation')
                        {
//                            $aggNode = $domDoc->createElementNS(' ', 'edm:hasView');
                            $aggNode = $domDoc->createElement('edm:hasView');
                            $attAggNode = $domDoc->createAttribute('rdf:resource');
                            $attAggNode->value = $value;
                            $aggNode->appendChild($attAggNode);
                            $aggregator->appendChild($aggNode);
                        }
                    }
                }
                else{
                    $child = $param->appendChild($childNode);            //add newley created node to root or the given node
                    $child->appendChild($nodeValue);                            //assign value to the newly created node element
                }
            }
        }
        $domDoc->save($xmlFile);
    }


    function addWebResource($domDoc, $value){

        $rootNode = $domDoc->documentElement;
        //$resourceNode = $domDoc->createElementNS(' ', 'edm:WebResource'); //create resource element
        $resourceNode = $domDoc->createElement('edm:WebResource'); //create resource element
        $attResource = $domDoc->createAttribute('rdf:about');            //create resource attribute
        $attResource->value = $value;                                    //assigne value to resource
        $resourceNode->appendChild($attResource);                        //add attribute to resource element

//        $resourceEDMRights = $domDoc->createElementNS(' ', 'edm:rights'); //create resource element
        $resourceEDMRights = $domDoc->createElement('edm:rights'); //create resource element
        $resourceNode->appendChild($resourceEDMRights);
//        $resourceDCRights = $domDoc->createElementNS(' ', 'dc:rights'); //create resource element
        $resourceDCRights = $domDoc->createElement('dc:rights'); //create resource element
        $resourceNode->appendChild($resourceDCRights);

        $rootNode->appendChild($resourceNode);                           //add resource element to root
    }

    function initEDMRecord($existingXML, $xmlEDM){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);

        $domDoc = new DOMDocument();
        $domDoc->load($xmlEDM);

        $edmRecordID = array();

        $params = $dom->getElementsByTagName('lido');

        for($i=0; $i<$params->length; $i++){
            $edmRecordID[] = $this->edmRecordId();

            $rootNode = $domDoc->documentElement;

            //create empty edm record
//            $childNode = $domDoc->createElementNS(' ', 'edm:ProvidedCHO'); //create node element
            $childNode = $domDoc->createElement('edm:ProvidedCHO'); //create node element
            $attAbout = $domDoc->createAttribute('rdf:about');
            $attAboutText = $domDoc->createTextNode($edmRecordID[$i]);
            $attAbout->appendChild($attAboutText);
            $childNode->appendChild($attAbout);
            $rootNode->appendChild($childNode); //append edm record to root element

            //create aggregation node with edm:aggregatedCHO element
            $this->createAggregationNode($domDoc, $rootNode, $edmRecordID[$i]);

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

        $dom = new DomDocument;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('lido');
        $xPathQuery = $this->pathQuery($existingElementPath);

        $foundValues = array();
        $i = 0;
        foreach ($params as $param) {       //iterates for each record
//            $entries = $xpath->query($param->getNodePath().$query);
            $entries = $xpath->query($param->getNodePath().$xPathQuery);
            foreach ($entries as $entry) {
                $foundValues[$i][] = $entry->nodeValue;
            }
            $i++;
        }
        return $foundValues;
    }

}