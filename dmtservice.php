<?php
	require_once("/util/dmtdatafiles.php");
    require_once("lidoMapping.php");

	class service{
		public $MINIMUM_PATH_ITEMS = 4;
		
		private	$inputDataFile;
		private $outputDataFile;
		
		public function _construct(){			 
			 self::__set('inputDataFile', new DataFile  ());  
			 self::__set('outputDataFile', new DataFile  ());
		}

            # Setter  
            public function __set($name, $value)  
             {  
                    switch ($name)  
                     {  
                            case 'inputDataFile':  
                              $this->inputDataFile = $value;  
                            break;
							
                            case 'outputDataFile':  
                              $this->outputDataFile = $value;  
                            break;

                            default:
                              throw new Exception("Attempt to set a non-existing property: $name");  
                            break;  
                     }  
             } 
			 
            # Getter  
            public function __get($name)  
             {  
                    if (in_array($name, array('inputDataFile')))  
                     return $this->$name;  
					 
                    if (in_array($name, array('outputDataFile')))  
                     return $this->$name; 

					 switch ($name)  
                     {  
                            default:  
                              throw new Exception("Attempt to get a non-existing property: $name");  
                            break;  
                     }  
             } 			 
		
		function getRequest($path, $parameters){
			//There should be minimum 4 items in the GET request.
			if (sizeof($path) == $this -> MINIMUM_PATH_ITEMS){
				$strResponse = "";
				$strParameterValue = "";
				$module   = $path[0];
				$provider = $path[1];
				$title    = $path[2];
				$action   = $path[3];
				$str = "module = ".$module."<br>"."provider= ".$provider."<br>"."title= ".$title."<br>"."action= ".$action."<br>" ;
				
				//Retrieve parameter and their corresponding values
				if($parameters){
					$parameterArray = explode("&",$parameters);
					foreach ($parameterArray as $parameter)
					  {
						$array = explode("=",$parameter);
						if($array){
							$param = $array[0];
							$value = $array[1];
							$strParameterValue .= $param." has value ".$value . "<br>";							
						}												
					  }					
				}
				

				return $str."<br>".$strParameterValue;
				
			} else {
				return $invalidMessage = "Invalid URL. <br> Please provide url in '/DataMapping/provider/batch/action?parameters' format.";			
			}
			
			
		}
		
		function validRequest($path){
			//There should be minimum 4 items in the GET request.
			if (sizeof($path) == $this -> MINIMUM_PATH_ITEMS) 
				return true;
			else
				return false;			
		}	

		//create directory(millisecond name)
		//create file
		function dmtDirectory($fileContent, $fileName, $fileType, $title){
			$filePath = dirname(__FILE__)."/files/".strtoupper($title).round(microtime(true) * 1000);
			if (!file_exists($filePath)) {
				mkdir($filePath);
			}
			$file = $filePath."/".$fileName;
			file_put_contents($file, $fileContent);
			
			
			$iFile = new DataFile();  
			$iFile->filePath = $filePath;  
			$iFile->fileName = $fileName; 
			
			$isZip = zip_open($file);			
			if (is_resource($isZip)) {
				zip_close($isZip); 
				$iFile->fileType = "application/zip"; 
			}else 			
				$iFile->fileType = $fileType; 

            return $iFile;
			
		}
		
		function dmtTransformer($dataFile, $rulesFile){

			$oFile = new DataFile();
			//$oFile->fileName = "Transformed_".$dataFile -> fileName; //"Transformed.html"; 
			$oFile->fileName = $dataFile -> fileName."_Transformed.html"; 
			//$oFile->fileName = "Transformed.html"; 
			$oFile->filePath = $dataFile -> filePath;  
			$oFile->fileType = $dataFile -> fileType;  			

			if(false !== ($f = @fopen($oFile->filePath."/".$oFile->fileName, 'w'))) 
			{ 

				$xml = new DOMDocument;
				@$xml->load($dataFile->filePath."/".$dataFile->fileName);

				$xsl = new DOMDocument;
				@$xsl->load($rulesFile->filePath."/".$rulesFile->fileName);

					
				$transformedxml = new DOMDocument;
				@$transformedxml->load($oFile->filePath."/".$oFile->fileName);

				$proc = new XSLTProcessor;
				@$proc->importStyleSheet($xsl); // attach the xsl rules

				//$proc->transformToXML($transformedxml);
				file_put_contents($oFile->filePath."/".$oFile->fileName, $proc->transformToXML($xml));			

			}			
					
			return $oFile;
		}
		
		//function dmtCore($fileContent, $fileName, $fileType, $recordFile, $mappingFile){
        function dmtCore($recordFile, $mappingFile){
            //$mappingFile->fileName = "collectionrules.xsl";
            //$mappingFile->fileName = "marc21ToOaiDc.xsl";
            $this->inputDataFile =$recordFile;

            if($this -> inputDataFile -> fileType == "application/zip"){
				//read zip file, for each file call tranformer				
				$inputFile = $this->inputDataFile -> filePath ."/". $this->inputDataFile -> fileName;
				//extract each file, transform it and remove it
				$zip = new ZipArchive;
				if ($zip->open($inputFile) === true) {								  
					$transformationPath = $this->inputDataFile -> filePath."/transformed";
					if (!file_exists($transformationPath)) {
						mkdir($transformationPath);
					}				
					for($i = 0; $i < $zip->numFiles; $i++) {	
						$tempFile = new DataFile();						
						$zip->extractTo($transformationPath, array($zip->getNameIndex($i)));
						
						$tempFile->fileName = $zip->getNameIndex($i); 
						$tempFile->filePath = $transformationPath;  
						$tempFile->fileType = "application/xml"; 
														   
						$this -> dmtTransformer($tempFile, $mappingFile);
						unlink($tempFile->filePath ."/". $tempFile->fileName);
					}								   
					$zip->close();								  
				}											

				//zip all files
				$zipFile = new DataFile();
				$zipFile->fileName = "Transformed_" . $this -> inputDataFile -> fileName; 
				$zipFile->filePath = $this->inputDataFile ->filePath;  
				$zipFile->fileType = "application/zip"; 				
				
				$zip = new ZipArchive;
				$zip->open($zipFile->filePath."/".$zipFile->fileName, ZipArchive::CREATE);
				foreach (glob($zipFile->filePath."/transformed/*") as $file) {
					$zip->addFile($file);										
				}
				$zip->close();				
								
				$this -> outputDataFile = $zipFile;
				
			}else
			{			
				$this -> outputDataFile = $this -> dmtTransformer($this->inputDataFile, $mappingFile);
			}
            //file_put_contents($this-> outputDataFile->filePath."/status.txt", '1');
            return $this -> outputDataFile;
			 
		}

        function mappRecord($rulesFileContent, $rulesFileName, $recordFile, $sourceFormat, $targetFormat){
            if($sourceFormat == 'LIDO' && $targetFormat == 'EDM')
                $this->generateLIDOEDM($rulesFileContent, $rulesFileName, $recordFile);

//            if($sourceFormat == 'MARC' && $targetFormat == 'EDM')
//                $this->generateMARCEDM();
//
//            if($sourceFormat == 'EAD' && $targetFormat == 'EDM')
//                $this->generateEADEDM();

            }

        function generateLIDOEDM($rulesFileContent, $rulesFileName, $recordFile){

            $lidoMapping =  new lidoMapping();

            $mappingPath = $recordFile->filePath."/mapping";
            if (!file_exists($mappingPath)) {
                mkdir($mappingPath);
            }
            $rulesFile = $mappingPath."/".$rulesFileName;
            file_put_contents($rulesFile, $rulesFileContent);

            $sourceFile = $recordFile;
            $sourceFilePath = $sourceFile->filePath."/".$sourceFile->fileName;

            $fileName = explode(".", $rulesFileName);
            $newXMLFile = $mappingPath."/".$fileName[0]."test.xml";
            $this->initEDMXML($newXMLFile);

//            $xml=simplexml_load_file($sourceFilePath);

        }





        function generateMappingFile($rulesFileContent, $rulesFileName, $recordFile, $sourceFormat, $targetFormat){

            $mappingFile = new DataFile();

            $lidoMapping =  new lidoMapping();

            $mappingPath = $recordFile->filePath."/mapping";
            if (!file_exists($mappingPath)) {
                mkdir($mappingPath);
            }
            $rulesFile = $mappingPath."/".$rulesFileName;
            file_put_contents($rulesFile, $rulesFileContent);

            $sourceFile = $recordFile;
            $sourceFilePath = $sourceFile->filePath."/".$sourceFile->fileName;

            $fileName = explode(".", $rulesFileName);
            $newXMLFile = $mappingPath."/".$fileName[0].".xml";
//            $this->initEDMXML($newXMLFile);
            $lidoMapping->initEDMXML($newXMLFile);
            $edmRecordIds = $lidoMapping->initEDMRecord($sourceFilePath, $newXMLFile);

            $row = 1;
            if (($handle = fopen($rulesFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $xsltData = "";
                    switch($data[0]){
                        case 'COPY':
                              // for marc
//                            $existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);
//                            $this->addXMLElement($newXMLFile, $data[2],null,$existingValues );

                             // for lido
                            $lidoMapping->copyMapping($sourceFilePath, $data[1], $data[2], $newXMLFile, $edmRecordIds);
                            $toCopy = $lidoMapping->getExistingRecordValue($sourceFilePath,$data[1]);
                            $lidoMapping->addXMLElement($newXMLFile, $data[2],null,$toCopy );
                            break;

                        case 'APPEND':          //append to value of an existing record and assign to a new record
                            // for marc
                            //$existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);
                            //$lidoMapping->getExistingRecordValue($sourceFilePath,$data[1] , 1);

                            // for lido
                            $lidoMapping->appendMapping($sourceFilePath, $data[1], $data[3], $newXMLFile, $edmRecordIds, $data[2]);
                            $toAppend = $lidoMapping->getExistingRecordValue($sourceFilePath,$data[1]);
                            $appendedValue = $toAppend.' '.$data[2];
                            $lidoMapping->addXMLElement($newXMLFile, $data[3],null,$appendedValue);
                            break;

                        case 'SPLIT':
                            // for marc
                            //$existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);

                            // for lido
                            $toSplit = $lidoMapping->getExistingRecordValue($sourceFilePath,$data[1]);
                            if($data[2] == '')
                                $data[2] = ' ';
                            $splitData = explode($data[2], $toSplit);
                            $elements = explode(';', (trim($data[3], '()')));

                            for($i = 0; $i<sizeof($elements); $i++){
                                $node = str_replace(' ','',$elements[$i]);
                                if(isset($splitData[$i]))
                                    $nodeValue = $splitData[$i];
                                else
                                    $nodeValue = '';
                                $lidoMapping->addXMLElement($newXMLFile, $node, null,$nodeValue );
                        }

                            break;

                        case 'COMBINE':
                            // for marc
//                            $existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);

                            $combine = $lidoMapping->getExistingRecordValue($sourceFilePath,$data[1]).
                                ' '.$lidoMapping->getExistingRecordValue($sourceFilePath,$data[2]);
                            $lidoMapping->addXMLElement($newXMLFile, $data[3],null,$combine );

                            break;

                        case 'LIMIT':
                            // for marc
//                            $existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);

                            $toLimit = $lidoMapping->getExistingRecordValue($sourceFilePath,$data[1]);
                            $limited = substr($toLimit, 0,$data[2]);
                            $lidoMapping->addXMLElement($newXMLFile, $data[3],null,$limited );
//                            file_put_contents('C:/xampp/htdocs/euInside/files/dmtlog.txt','Limit: '.$limited."\n",FILE_APPEND);
                            break;

                        case 'PUT':
                            //for marc
//                            $existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);

                            //for lido
                            $lidoMapping->putMapping($sourceFilePath, $data[2], $newXMLFile, $edmRecordIds, $data[1]);
                            $lidoMapping->addXMLElement($newXMLFile, $data[2],null,$data[1]);
                            break;

                        case 'REPLACE':             //replace value of an existing record and assign to a new record
                            $toReplace = $lidoMapping->getExistingRecordValue($sourceFilePath,$data[1]);
                            $replacedValue = str_replace($data[2], $data[3], $toReplace);
                            $lidoMapping->addXMLElement($newXMLFile, $data[4],null,$replacedValue );
                            break;

                        case 'SKIP':        //do not add against skip
//                            $existingValues = $this->getExistingRecordValue($sourceFilePath,$data[1] , 1);
                            break;

                        case 'CONDITION':
                            break;

                        default:
                            break;
                    }
                    $row++;
                }
                fclose($handle);

            }

        //Tempoary Default transformation file
            $mappingFile->filePath = dirname(__FILE__)."/transformationrules";
            $mappingFile->fileType = 'xslt';
            $mappingFile->fileName = "collectionrules.xsl";

            return $mappingFile;
        }

        //Working for Marc
        function getExistingRecordValue($existingXML, $attribute){
            $returnValue = array();
            $xml=simplexml_load_file($existingXML);

            $tag = substr($attribute,4,3);
            $ident1 = substr($attribute,7,1);
            if($ident1 == "#") $ident1 =" ";
            $ident2 = substr($attribute,8,1);
            if($ident2 == "#") $ident2 =" ";
            $code = substr($attribute,10,1);

            $xPathQuery = ' /collection/record/datafield[@tag="%s" and @ind1="%s" and @ind2="%s"] ';
            $xPathQuery = sprintf($xPathQuery, $tag, $ident1, $ident2);

            $query = $xml->xpath($xPathQuery);
            if(isset($query[0]))
            {
                $children =$query[0]->children();

                if(isset($code)){
                    foreach($children as $child){
                        if($code == $child['code']){
                            $returnValue = $child;
                            break;
                        }
                    }
                }
                else
                    $returnValue = $children[0];
            }
            else

            return $returnValue;
        }

        function writeNewRecordValue($newXML, $field){

        }

        function mappingCopy(){
            //$lidoMapping->addXMLElement($newXMLFile, $data[2],null,$toCopy );
        }

        function initEDMXML($xmlFile){
            $domDoc = new DOMDocument('1.0', 'UTF-8');
            $rootElt = $domDoc->createElementNS(' ','rdf:RDF');
//            $att = $domDoc->createAttribute('xsi:schemaLocation');
//            $attTex = $domDoc->createTextNode('"http://www.w3.org/1999/02/22-rdf-syntax-ns# EDM.xsd"');
//            $att->appendChild($attTex);
//            $rootElt->appendChild($att);
            $domDoc->appendChild($rootElt);
            $domDoc->save($xmlFile);

            return $xmlFile;
        }
        function addXMLElement($xmlFile, $appendElement, $appendToElement, $value){

            $addToElement = "";
            $domDoc = new DOMDocument();
            $domDoc->load($xmlFile);
            $root=$domDoc->documentElement; // Root node
            if(!isset($appendToElement))
                $addToElement = $domDoc->documentElement; // Root node
            else
                $addToElement = $appendToElement;


            //$childNode = $domDoc->appendChild($addToElement);

            $childNode = $domDoc->createElementNS('nm', $appendElement);
            $nodeValue = $domDoc->createTextNode($value);
//            $test = $childNode->appendChild($nodeValue);
            $addToElement->appendChild($childNode);

            $domDoc->save($xmlFile);
        }

		function removeDirectory($dir) {

            $dir_content = scandir($dir);
            if($dir_content !== FALSE){
                foreach ($dir_content as $entry)
                {
                    if(!in_array($entry, array('.','..'))){
                        $entry = $dir . '/' . $entry;
                        if(!is_dir($entry)){
                            unlink($entry);
                        }
                        else{
                            rmdir_recursive($entry);
                        }
                    }
                }
            }
            rmdir($dir);

		}
	}
?>