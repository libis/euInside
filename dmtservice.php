<?php
	require_once("util/dmtdatafiles.php");
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

		function validRequest($path){
			//There should be minimum 4 items in the GET request.
			if (sizeof($path) == $this -> MINIMUM_PATH_ITEMS) 
				return true;
			else
				return false;			
		}

        /*
         * GET REQUESTS
         */

        function getStatus($requestId){
            $id = explode(':', $requestId);
            $requestStatusFile = dirname(__FILE__)."/files/".$id[0].'/status.txt';
            if(file_exists($requestStatusFile)){
                if (($handle = fopen($requestStatusFile, "r")) !== FALSE){
                    $line = fgets($handle);
                    fclose($handle);
                    return $line;
                }
            }
            else
                return 0;
        }

        function getSupportedFormatList(){
            return array('1' => 'EDM', '2' => 'LIDO');
        }

        function getResult($requestId){
            $resultFile = new DataFile();

            $id = explode(':', $requestId);
            if(sizeof($id) == 2){
                $requestFile = dirname(__FILE__)."/files/".$id[0].'/'.$id[1];
                if(file_exists($requestFile)){
                    $resultFile->fileName = $id[1];
                    $resultFile->filePath = dirname(__FILE__)."/files/".$id[0];
                    $ext = pathinfo($requestFile, PATHINFO_EXTENSION);
                    switch($ext){
                        case 'xml':
                            $resultFile -> fileType = 'application/xml';
                            break;

                        case 'zip':
                            $resultFile -> fileType = 'application/zip';
                            break;

                        case 'html': //only for test purposes
                            $resultFile -> fileType = 'text/html';
                            break;

                        default:
                            break;
                    }
                }
                else
                    return null;
            }
            return $resultFile;

        }

        /*
         * PUT REQUESTS
         */

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

            $this->changeRequestStatus($iFile->filePath.'/status',1);

            return $iFile;
			
		}
		
		function dmtTransformer($dataFile, $rulesFile){

			$oFile = new DataFile();
            //temporary, output format will be based on the xslt used
			$oFile->fileName = "Transformed_".substr($dataFile -> fileName, 0,strrpos($dataFile -> fileName,'.')).'.html';
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

        function recordTransformation($recordFile, $mappingFile){

            $this->inputDataFile =$recordFile;
            $success = false;

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

                $resultFile = $zipFile->fileName;

                $zip = new ZipArchive;
                $zip->open($zipFile->filePath."/".$zipFile->fileName, ZipArchive::CREATE);
                foreach (glob($zipFile->filePath."/transformed/*") as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                $success = true; //at this moment results are ready immedietly , later this flag will be set based on the status

            }else
            {
                $this -> outputDataFile = $this -> dmtTransformer($this->inputDataFile, $mappingFile);
                $resultFile = $this -> outputDataFile->fileName;
                $success = true; //at this moment results are ready immedietly , later this flag will be set based on the status
            }

            $statusFile = $recordFile->filePath.'/status.txt';
            if($success === true) //change status to 2(ready to send)
            $this->changeRequestStatus($statusFile, 2);
            else //change status to 1 (not yet ready, in process)
            $this->changeRequestStatus($statusFile, 1);

            $requestPath = explode('/',$recordFile->filePath);
            $receipt = $requestPath[sizeof($requestPath)-1].':'.$resultFile;

            return $receipt;
        }

        function recordMapping($rulesFileContent, $rulesFileName, $recordFile, $sourceFormat, $targetFormat){

            $targetFile = new DataFile();
            $success = false;
            $resultFile = "";

            $rulesFile = $recordFile->filePath."/".$rulesFileName;
            file_put_contents($rulesFile, $rulesFileContent);

            $sourceFilePath = $recordFile->filePath."/".$recordFile->fileName;

            if($recordFile -> fileType == "application/zip"){

                $zip = new ZipArchive;
                if ($zip->open($sourceFilePath) === true) {
                    $transformationPath = $recordFile -> filePath."/transformed";
                    if (!file_exists($transformationPath)) {
                        mkdir($transformationPath);
                    }
                    for($i = 0; $i < $zip->numFiles; $i++) {
                        $zip->extractTo($transformationPath, array($zip->getNameIndex($i)));
                        $extractedFile = $transformationPath."/". $zip->getNameIndex($i);

                        $filePath = explode(".",  $zip->getNameIndex($i));
                        if(isset($filePath[0])){
                            $fileName = explode(".",  $filePath[0]);
                            $edmXMLFileName = "Transformed_".$fileName[0].".xml";
                        }
                        else
                            $edmXMLFileName = "Transformed_".$i.".xml";

                        $edmXMLFile = $transformationPath."/".$edmXMLFileName;
                        $success = $this->generateLIDOEDMFile($edmXMLFile, $extractedFile, $rulesFile);
                        unlink($extractedFile);
                    }
                    $zip->close();
                }
                //zip all files
                $zipFile = new DataFile();
                $zipFile->fileName = "Transformed_" . $recordFile -> fileName;
                $zipFile->filePath = $recordFile -> filePath;
                $zipFile->fileType = "application/zip";

                $zip = new ZipArchive;
                $result = $zip->open($zipFile->filePath."/".$zipFile->fileName, ZipArchive::CREATE);

                $counter=0;
                foreach (glob($zipFile->filePath."/transformed/*") as $file) {
                        $zip->addFile($file, basename($file));
                }
                $zip->close();

                $resultFile = $zipFile->fileName;

            }
            else{
                $sourceFilePath = $recordFile->filePath."/".$recordFile->fileName;
                $fileName = explode(".", $recordFile->fileName);
                $edmXMLFileName = "Transformed_" .$fileName[0].".xml";
                $edmXMLFile =  $recordFile->filePath."/".$edmXMLFileName;

                $resultFile = $edmXMLFileName;
                if($sourceFormat == 'LIDO' && $targetFormat == 'EDM')
                    $success = $this->generateLIDOEDMFile($edmXMLFile, $sourceFilePath, $rulesFile);

            }

            $statusFile = $recordFile->filePath.'/status.txt';
            if($success === true) //change status to 2(ready to send)
                $this->changeRequestStatus($statusFile, 2);
            else //change status to 1(not yet ready, in process)
            $this->changeRequestStatus($statusFile, 1);


            $requestPath = explode('/',$recordFile->filePath);
            $receipt = $requestPath[sizeof($requestPath)-1].':'.$resultFile;
            return $receipt;
        }

        function generateLIDOEDMFile($newXMLFile, $sourceFilePath, $rulesFile){

            $lidoMapping =  new lidoMapping();

            $lidoMapping->initEDMXML($newXMLFile);
            $edmRecordIds = $lidoMapping->initEDMRecord($sourceFilePath, $newXMLFile);

            $row = 1;
            if (($handle = fopen($rulesFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $this->mappingCommandParser($data, $sourceFilePath, $newXMLFile, $edmRecordIds, $row, $handle);
                    $row++;
                }
                fclose($handle);
            }
            return true;
        }

        function mappingCommandParser($data, $sourceFilePath, $newXMLFile, $edmRecordIds, $row, $handle){

            $lidoMapping =  new lidoMapping();
            switch($data[0]){
                case 'COPY':
                    $lidoMapping->copyMapping($sourceFilePath, $data[1], $data[2], $newXMLFile, $edmRecordIds);
                    break;

                case 'APPEND':
                    $lidoMapping->appendMapping($sourceFilePath, $data[1], $data[3], $newXMLFile, $edmRecordIds, $data[2]);
                    break;

                case 'SPLIT':
                    $lidoMapping->splitMapping($sourceFilePath, $data[1], $data[3], $newXMLFile, $edmRecordIds, $data[2]);
                    break;

                case 'COMBINE':
                    $lidoMapping->combineMapping($sourceFilePath, $data[1], $data[2], $newXMLFile, $edmRecordIds);
                    break;

                case 'LIMIT':
                    $lidoMapping->limitMapping($sourceFilePath, $data[1], $data[3], $newXMLFile, $edmRecordIds, $data[2]);
                    break;

                case 'PUT':
                    $lidoMapping->putMapping($sourceFilePath, $data[2], $newXMLFile, $edmRecordIds, $data[1]);
                    break;

                case 'REPLACE':
                    $lidoMapping->replaceMapping($sourceFilePath, $data[1], $data[4], $newXMLFile, $edmRecordIds, $data[2], $data[3]);
                    break;

                case 'SKIP':        //do not add against skip
                    break;

                case 'CONDITION':

                    $conditionData = array();
                    for($i=$row; $i<1000; $i++){
                        $lineData = fgets($handle);
                        if (strpos($lineData,"}")!== false)
                            break;
                        $conditionData[] = $lineData;
                    }
                    $commandData = $this->conditionParser($sourceFilePath, $conditionData);
                    $this->mappingCommandParser($commandData, $sourceFilePath, $newXMLFile, $edmRecordIds, $row, $handle);

                    break;

                default:
                    break;
            }
        }

        function conditionParser($sourceFilePath, $conditionData){

            $ifCondition = $conditionData[0];

            $ifPosition = strpos($ifCondition,'IF');

            if($ifPosition === false) return; //IF condition not found

            $ifData = explode('DO', str_replace(array( '[', ']' ), '', substr($ifCondition, $ifPosition+2)));
            $ifConditionPart = explode(',', $ifData[0]);
            $ifDoPart = explode(',', str_replace(array( '(', ')' ), '', $ifData[1]));

            $result = $this->conditionIF($sourceFilePath, $ifConditionPart);
            if($result == 1){ //condition positive
                return $ifDoPart;
            }
            elseif($result == 2 && isset($conditionData[1])){ //condition negative
                $elseCondition = $conditionData[1];

                $elsePosition = strpos($elseCondition,'ELSE');
                $elseData = explode('DO', str_replace(array( '[', ']' ), '', substr($elseCondition, $elsePosition+4)));
                //here support for nested IF ELSE can be extended
               // $elseConditionPart = explode(',', $elseData[0]);//is not neeed for single ELSE condition (non nested)
                $elseDoPart = explode(',', str_replace(array( '(', ')' ), '', $elseData[1]));

                foreach($elseData as $dd)

                return $elseDoPart;
            }
            else{
            }
        }

        function conditionIF($sourceFilePath, $ifData){
            $lidoMapping =  new lidoMapping();

            $conditionType = strtoupper($ifData[1]); //e.g EQUAL
            $conditionValue =  $ifData[2];

            $foundNodeValues = $lidoMapping->nodeValue($sourceFilePath, $ifData[0]);

            switch($conditionType){
                case 'EQUALS':
                    foreach ($foundNodeValues as $foundValues) {
                        foreach ($foundValues as $value) {
                            if($value == $conditionValue)
                            {
                                return 1;  //values are equal
                            }
                        }
                    }
                    return 2;//values are not equal
                    break;
            }
            return 0; //invalid condition type
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

        function changeRequestStatus($filePaht, $status){
            file_put_contents($filePaht, $status);

        }
	}
?>