<?php
	require_once("/util/dmtdatafiles.php");
?>

<?php
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
		function dmtDirectory($fileContent, $fileName, $fileType){		
			$filePath = dirname(__FILE__)."/files/".round(microtime(true) * 1000);
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
			
			$this->inputDataFile = $iFile; 
			
		}
		
		function dmtTransformer($dataFile, $rulesFile){

			$oFile = new DataFile();
			$oFile->fileName = "Transformed_".$dataFile -> fileName; //"Transformed.html"; 
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
		
		function dmtCore($fileContent, $fileName, $fileType, $sourceFormat, $targetFormat){
			$this -> dmtDirectory($fileContent, $fileName, $fileType);
			
			//currently only one xslt, an appropriate xslt mapping rule file should be selected
			//based on source and target format choice
			$rulesFile = new DataFile();
			#$rulesFile->fileName = "marc21ToOaiDc.xsl"; 
			$rulesFile->fileName = "collectionrules.xsl"; 
			$rulesFile->filePath = dirname(__FILE__)."/transformationrules";  
			$rulesFile->fileType = 'xslt'; 
			
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
														   
						$this -> dmtTransformer($tempFile, $rulesFile);
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
				$this -> outputDataFile = $this -> dmtTransformer($this->inputDataFile, $rulesFile);					
			}
								
			return $this -> outputDataFile;
			 
		}
		
		function removeDirectory($dirPath) { 
	
			if (is_dir($dirPath)) { 
				$objects = scandir($dirPath); 
				foreach ($objects as $object) { 
					if ($object != "." && $object != "..") { 
						if (filetype($dirPath."/".$object) == "dir"){
							 removeDirectory($dirPath."/".$object);
						}else{
							 unlink($dirPath."/".$object);
						} 
					} 
					reset($objects); 
					rmdir($dirPath); 
				} 
			}		
		}
	}
?>