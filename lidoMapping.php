<?php

class lidoMapping {

    function getExistingRecordValue($existingXML, $elementPath){
        $returnValue = array();
        $xml=simplexml_load_file($existingXML);

//        $this->createMapping($existingXML, $elementPath);

        $query = $xml->xpath($this->xQueryPath($elementPath));


        if(isset($query[0]))
            return $query[0];
        else
            return $returnValue;
    }


    function copyMapping($existingXML, $existingElementPath, $edmElement, $xmlEDM, $edmRecordIds){

        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($existingXML);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('lido');
        $query = $this->xPathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {

            $entries = $xpath->query($param->getNodePath().$query);
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
        $query = $this->xPathQuery($existingElementPath);

        $i = 0;
        foreach ($params as $param) {

            $entries = $xpath->query($param->getNodePath().$query);
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

        $i = 0;
        foreach ($params as $param) {
            $this->addXMLNode($xmlEDM, $edmElement,  $edmRecordIds[$i], $valueToPut);
            $i++;
        }
    }


//OLD
    function xQueryPath($elementPath){
        $prefix = 'lido:';
        $attribute = "";

        $strPath = explode('@', $elementPath);

        //element path
        $strElement = str_replace('/', '/'.$prefix, $strPath[0]);

        //attribute
        if( isset($strPath[1])){
            $strAttribute = explode('=', $strPath[1]);
            $attributeName = $prefix.$strAttribute[0];
            $attributeValue = $strAttribute[1];
            $attribute = '[@'.$attributeName.'="'.$attributeValue.'"]';
        }
        return $prefix.'lido'.$strElement.$attribute;
    }


    function xPathQuery($elementPath){
        $prefix = 'lido:';
        $attribute = "";

        $strPath = explode('@', $elementPath);

        //element path
        $strElement = str_replace('/', '/'.$prefix, $strPath[0]);

        //attribute
        if( isset($strPath[1])){
            $strAttribute = explode('=', $strPath[1]);
            $attributeName = $prefix.$strAttribute[0];
            $attributeValue = $strAttribute[1];
            $attribute = '[@'.$attributeName.'="'.$attributeValue.'"]';
        }
        return $strElement.$attribute;
    }

    function initEDMXML($xmlFile){
        $domDoc = new DOMDocument('1.0', 'UTF-8');

        $rootElt = $domDoc->createElementNS(' ','rdf:RDF');
        $domDoc->appendChild($rootElt);

        $domDoc->save($xmlFile);
        return $xmlFile;
    }
//old
    function addXMLElement($xmlFile, $appendElement, $appendToElement, $value){

        $addToElement = "";
        $domDoc = new DOMDocument();
        $domDoc->load($xmlFile);

        if(!isset($appendToElement))
            $addToElement = $domDoc->documentElement;                // Add to the Root node
        else
            $addToElement = $appendToElement;                       // Add to the given element

        $childNode = $domDoc->createElementNS(' ', $appendElement); //create node element
        $nodeValue = $domDoc->createTextNode($value);               //create value item
        $child = $addToElement->appendChild($childNode);            //add newley created node to root or the given node
        $child->appendChild($nodeValue);                            //assign value to the newly created node element

        $domDoc->save($xmlFile);
    }

    function addXMLNode($xmlFile, $appendElement, $edmRecordId, $value){

        $addToElement = "";
        $domDoc = new DOMDocument();
        $domDoc->load($xmlFile);
        $xpath = new DOMXPath($domDoc);

        $childNode = $domDoc->createElementNS(' ', $appendElement); //create node element
        $nodeValue = $domDoc->createTextNode($value);               //create value item


        $params = $domDoc->getElementsByTagName('ProvidedCHO');
        foreach ($params as $param) {
            if($edmRecordId == $param->getAttribute('rdf:about')){
                file_put_contents('C:/xampp/htdocs/euInside/files/dmttest101.txt',$param->getAttribute('rdf:about')."\n",FILE_APPEND);
                $child = $param->appendChild($childNode);            //add newley created node to root or the given node
                $child->appendChild($nodeValue);                            //assign value to the newly created node element
            }

        }

        $domDoc->save($xmlFile);
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
            $childNode = $domDoc->createElementNS(' ', 'edm:ProvidedCHO'); //create node element

            $attAbout = $domDoc->createAttribute('rdf:about');
            $attAboutText = $domDoc->createTextNode($edmRecordID[$i]);
            $attAbout->appendChild($attAboutText);
            $childNode->appendChild($attAbout);

            $rootNode->appendChild($childNode);
            $domDoc->save($xmlEDM);
            file_put_contents('C:/xampp/htdocs/euInside/files/dmttest100.txt',$edmRecordID[$i]."\n",FILE_APPEND);
        }
        return $edmRecordID;

    }

    function edmRecordId(){
        return md5(uniqid(rand(), true));
    }

    function findNodeByAttribute($xmlEDM, $edmRecordId){
        $dom = new DomDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->load($xmlEDM);
        $xpath = new DOMXPath($dom);

        $params = $dom->getElementsByTagName('ProvidedCHO');
        foreach ($params as $param) {
            if($edmRecordId == $param->getAttribute('rdf:about')){
                file_put_contents('C:/xampp/htdocs/euInside/files/dmttest101.txt',$param->getNodePath()."\n",FILE_APPEND);
                file_put_contents('C:/xampp/htdocs/euInside/files/dmttest101.txt',$param->getAttribute('rdf:about')."\n",FILE_APPEND);
                return $param;
            }

        }

    }

}