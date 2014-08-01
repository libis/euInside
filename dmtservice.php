<?php
	require_once("util/dmtdatafiles.php");
    require_once("lidoMapping.php");
    require_once("marcMapping.php");
    require_once("mappingRules.php");

	class service{
		public $MINIMUM_PATH_ITEMS = 4;
		
		private	$inputDataFile;
		private $outputDataFile;

        private $marcRecords;
        private $edmRecords;
        private $mappingCommands = array();

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
            $supportedFormats = array('Supported Formats'=>
                        array(
                            array('Source' => 'LIDO',
                                  'target' => 'EDM'),
                            array('Source' => 'MARC',
                                  'target' => 'EDM')
                        ));
            return $supportedFormats;
        }

        function getStatistics(){
            $statistics = array('Statistics'=>
            array(
                array('Item Processed'      => 10,
                      'Duration (sec)'      => 1,
                      'Successful Items'    => 8,
                      'Unsuccessful Items'  => 2,
                )
            ));
            return $statistics;
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


        ////Record Transformation: content processing
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

        ////Record Transformation: applying XSLT
        function dmtTransformer($dataFile, $rulesFile){
            $transformedFile = new DataFile();

            $transformedFile->fileName = "Transformed_".substr($dataFile -> fileName, 0,strrpos($dataFile -> fileName,'.')).'.xml';
            $transformedFile->filePath = $dataFile -> filePath;
            $transformedFile->fileType = $dataFile -> fileType;

            if(false !== ($f = @fopen($transformedFile->filePath."/".$transformedFile->fileName, 'w')))
            {
                $cSourceXML = $dataFile->filePath."/".$dataFile->fileName;
                $cSourceXSLT = $rulesFile->filePath."/".$rulesFile->fileName;
                $cOutputXML = $transformedFile->filePath."/".$transformedFile->fileName;
                $saxonJar = dirname(__FILE__).'/util/saxon9he.jar';

                $command = 'java -jar '.$saxonJar.' -s:'.$cSourceXML.' -xsl:'.$cSourceXSLT.' -o:'.$cOutputXML;

                $javaDirectory ='C:\\PrOgRaM fIlEs\\Java\\jdk1.7.0_17\\bin';
                chdir($javaDirectory);

                exec($command);
            }
            return $transformedFile;
        }

        function normalizeRules($rulesFile){

            $rulesFileContent = file_get_contents($rulesFile);
            preg_match_all('/"[^"]+"/', $rulesFileContent, $matches, PREG_SET_ORDER);
            $changed = "";
            foreach ($matches as $val) {
                foreach($val as $v)
                {
                    $replaced = str_replace(',', '||', $v);
                    $rulesFileContent = file_get_contents($rulesFile);
                    $changed = str_replace($v, $replaced, $rulesFileContent);
                    file_put_contents($rulesFile,$changed);
                }
            }
        }

        ////Record Mapping: content processing
        function recordMapping($rulesFileContent, $rulesFileName, $recordFile, $sourceFormat, $targetFormat){

            $success = false;
            $resultFile = "";

            $rulesFile = $recordFile->filePath."/".$rulesFileName;
            file_put_contents($rulesFile, $rulesFileContent);

            $this->normalizeRules($rulesFile);

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
//                        $success = $this->generateLIDOEDMFile($edmXMLFile, $extractedFile, $rulesFile);

                        //support for records in zip for both lido and marc to edm
                        if($sourceFormat == 'LIDO' && $targetFormat == 'EDM')
                            $success = $this->generateLIDOEDMFile($edmXMLFile, $extractedFile, $rulesFile);

                        if($sourceFormat == 'MARC' && $targetFormat == 'EDM'){
                            $success = $this->generateMARCEDMFile($edmXMLFile, $extractedFile, $rulesFile);
                        }

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

//                $edmXMLFile2 =  $recordFile->filePath."/nm".$edmXMLFileName;

                $resultFile = $edmXMLFileName;
                if($sourceFormat == 'LIDO' && $targetFormat == 'EDM')
                    $success = $this->generateLIDOEDMFile($edmXMLFile, $sourceFilePath, $rulesFile);

                if($sourceFormat == 'MARC' && $targetFormat == 'EDM'){
                   $success = $this->generateMARCEDMFile($edmXMLFile, $sourceFilePath, $rulesFile);
                }

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

        ////Lido Mapping: lido to edm
        function generateLIDOEDMFile($newXMLFile, $sourceFilePath, $rulesFile){

            $lidoMapping =  new lidoMapping();

            $lidoMapping->initEDMXML($newXMLFile);
            $edmRecordIds = $lidoMapping->initEDMRecord($sourceFilePath, $newXMLFile);

            $this->normalizeRules($rulesFile);

            $row = 1;
            if (($handle = fopen($rulesFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

                    $this->lidoMappingCommandParser($data, $sourceFilePath, $newXMLFile, $edmRecordIds, $row, $handle);
                    $row++;
                }
                fclose($handle);
            }
            return true;
        }

        ////Lido Mapping: parsing commands for lido mapping
        function lidoMappingCommandParser($data, $sourceFilePath, $newXMLFile, $edmRecordIds, $row, $handle){
            $lidoMapping =  new lidoMapping();
            switch(strtoupper($data[0])){
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
                    ///****
                   // $commandData = $this->newConditionParser($sourceFilePath, $conditionData);
                    ///***
                    $commandData = $this->conditionParser($sourceFilePath, $conditionData, 'LIDO');
                    $this->lidoMappingCommandParser($commandData, $sourceFilePath, $newXMLFile, $edmRecordIds, $row, $handle);

                    break;

                default:
                    break;
            }
        }

        ////Mapping: parsing if condition
        function conditionParser($sourceFilePath, $conditionData, $format){     //for all formats
            $ifCondition = $conditionData[0];
            $ifPosition = strpos($ifCondition,'IF');

            if($ifPosition === false) return; //IF condition not found

            $ifData = explode('DO', str_replace(array( '[', ']' ), '', substr($ifCondition, $ifPosition+2)));
            $ifConditionPart = explode(',', $ifData[0]);
            $ifDoPart = explode(',', str_replace(array( '(', ')' ), '', $ifData[1]));

            switch($format){
                case 'LIDO':
                    $result = $this->conditionIFLIDO($sourceFilePath, $ifConditionPart);
                    break;

                case 'MARC':
                    $result = $this->conditionIFMARC($sourceFilePath, $ifConditionPart);
                    break;

                default:
                    break;
            }

            if(isset($result)){
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

        }

        ////Mapping: lido if conditions
        function conditionIFLIDO($sourceFilePath, $ifData){
            $lidoMapping =  new lidoMapping();

            $conditionType = strtoupper($ifData[1]); //e.g EQUAL
            $conditionValue =  $ifData[2];
            $conditionValue =  trim(str_replace('||', ',', $conditionValue),'"');

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

        function changeRequestStatus($filePaht, $status){
            file_put_contents($filePaht, $status);

        }

        ////MARC Mapping: marc to edm
        function generateMARCEDMFile($edmXMLFile, $sourceFilePath, $rulesFile){
            $marcMapping =  new marcMapping();
            $marcMapping->initEDMXML($edmXMLFile);

            $this->normalizeRules($rulesFile);
            $this->marcRecords = $marcMapping->getMarcRecords($sourceFilePath); //marc records

            $row = 1;
            if (($handle = fopen($rulesFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $this->marcMappingCommandParser($data, $sourceFilePath, $row, $handle);    //mapping rules
                    $row++;
                }
                fclose($handle);
            }
            $domDoc = new DOMDocument();
            $domDoc->load($edmXMLFile);

			$edmRecords = array();
			$t1 = round(microtime(true) * 1000);

            foreach($this->marcRecords as $marcRecord){
                $edmRecords [] = $marcMapping->edmRecord($marcRecord, $this->mappingCommands);
            }

			$t2 = round(microtime(true) * 1000);

			foreach($edmRecords as $item)
			{
                    $marcMapping->writeEDMRecord($domDoc, $edmXMLFile, $item);
			}
			$t3 = round(microtime(true) * 1000);			
            $domDoc->save($edmXMLFile);

            $t4 = round(microtime(true) * 1000);
            file_put_contents(realpath(dirname(__FILE__)).'/test/timelog3.txt', '1: '.(($t2-$t1)/1000)."s\n", FILE_APPEND);
            file_put_contents(realpath(dirname(__FILE__)).'/test/timelog3.txt', '2: '.(($t3-$t2)/1000)."s\n", FILE_APPEND);
            file_put_contents(realpath(dirname(__FILE__)).'/test/timelog3.txt', '3: '.(($t4-$t3)/1000)."s\n", FILE_APPEND);

            return true;
        }

        ////Marc Mapping: parsing commands for marc mapping
        function marcMappingCommandParser($data, $sourceFilePath, $row, $handle){
            $mappingRule = new mappingRules();
            switch(strtoupper($data[0])){
                case 'COPY':
                    $mappingRule->command = 'COPY';
                    $mappingRule->marcElement = $data[1];
                    $mappingRule->edmElement = $data[2];
                    $mappingRule->fields = null;
                    break;

                case 'APPEND':
                    $mappingRule->command = 'APPEND';
                    $mappingRule->marcElement = $data[1];
                    $mappingRule->edmElement = $data[3];
                    $mappingRule->fields['appendtext'] = $data[2];
                    break;

                case 'SPLIT':
                    $mappingRule->command = 'SPLIT';
                    $mappingRule->marcElement = $data[1];
                    $mappingRule->edmElement = $data[3];
                    $mappingRule->fields['splitby'] = $data[2];
                    break;

                case 'COMBINE':
                    $mappingRule->command = 'COMBINE';
                    $mappingRule->marcElement = $data[1];
                    $mappingRule->edmElement = $data[2];
                    $mappingRule->fields = null;
                    break;

                case 'LIMIT':
                    $mappingRule->command = 'LIMIT';
                    $mappingRule->marcElement = $data[1];
                    $mappingRule->edmElement = $data[3];
                    $mappingRule->fields['limitto'] = $data[2];
                    break;

                case 'PUT':
                    $mappingRule->command = 'PUT';
                    $mappingRule->marcElement = null;
                    $mappingRule->edmElement = $data[2];
                    $mappingRule->fields['puttext'] = $data[1];
                    break;

                case 'REPLACE':
                    $mappingRule->command = 'REPLACE';
                    $mappingRule->marcElement = $data[1];
                    $mappingRule->edmElement = $data[4];
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
                    $commandData = $this->conditionParser($sourceFilePath, $conditionData, 'MARC');
                    $this->marcMappingCommandParser($commandData, $sourceFilePath, $row, $handle);
                    break;

                default:
                    break;
            }

            if(isset($mappingRule->command))
                $this->mappingCommands[] = $mappingRule;
            unset($mappingRule);

        }

        ////Mapping: lido if conditions
        function conditionIFMARC($sourceFilePath, $ifData){
            $marcMapping =  new marcMapping();
            $conditionType = strtoupper($ifData[1]); //e.g EQUAL
            $conditionValue =  $ifData[2];
            $conditionValue =  trim(str_replace('||', ',', $conditionValue),'"');
            $foundNodeValues = $marcMapping->nodeValue($sourceFilePath, $ifData[0]);
            switch($conditionType){
                case 'EQUALS':
                    foreach ($foundNodeValues as $foundValue) {
                        if($foundValue == $conditionValue)
                            return 1;  //values are equal
                    }
                    return 2;//values are not equal
                    break;
            }
            return 0; //invalid condition type
        }

        // Check request validity
        function validRequest($path){
            //There should be minimum 4 items in the GET request.
            if (sizeof($path) == $this -> MINIMUM_PATH_ITEMS)
                return true;
            else
                return false;
        }

        // Request Directory: creation
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

        // Request Directory: deletion
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