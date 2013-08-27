<?php
    require_once('util/KLogger.php');
	require_once("dmtservice.php");
	require_once("util/dmtdatafiles.php");

    $log = KLogger::instance('logging/', KLogger::DEBUG);
	$dmtservice = new service;

    ob_start();
    header('Content-Type: text/plain');
	
	$requestmethod = $_SERVER['REQUEST_METHOD'];
	$requestPath = explode("/", substr(@$_SERVER['PATH_INFO'], 1));
	$requestParameters = $_SERVER['QUERY_STRING'];

    $requestLogId = round(microtime(true) * 1000).'-'.$_SERVER['REMOTE_ADDR'];

    $log->logInfo($requestLogId.'->Request Type:'. $_SERVER['REQUEST_METHOD']);

	$arrResponse = "";
	switch ($requestmethod) {
	
	  case 'PUT':
		$arrResponse = $_PUT;  
		break;
		
	  case 'POST':
		if($dmtservice -> validRequest($requestPath)){

            $log->logInfo($requestLogId.'->Is a valid Request.');

			if (isset($_POST['sourceFormat']) && isset($_POST['targetFormat'])){
				$responseFile = new DataFile();
                $mappingFile = new DataFile();
                $sourceFormat = strtoupper($_POST['sourceFormat']);
                $targetFormat = strtoupper($_POST['targetFormat']);
                //Currently transformation only to EDM is possible, for othr transformations code needs to be modified
                $targetFormat = 'EDM';

                $contentRecord = "";
                $nameRecord = "";
                $typeRecord = "";

                if (isset($_FILES['records'])){
                    $contentRecord = file_get_contents($_FILES['records']['tmp_name']);
                    $nameRecord = $_FILES['records']['name'];
                    $typeRecord = $_FILES['records']['type'];
                }elseif
                (isset($_FILES['record'])){
                    $contentRecord = file_get_contents($_FILES['record']['tmp_name']);
                    $nameRecord = $_FILES['record']['name'];
                    $typeRecord = $_FILES['record']['type'];
                }else{
                    //http_response_code(422);
                    header("Status: 422 Unprocessable Entity");
                    $arrResponse = "Record(s) is not provided";

                    $log->logError($requestLogId.'->'.$arrResponse);

                    exit(json_encode ($arrResponse));
                }
                if (isset($_FILES['record']) && isset($_FILES['records']))
                {
                    //http_response_code(404);
                    header("Status: 404 Not Found");
                    $arrResponse = "record and records parameters cannot be handled together in one request.";
                    $log->logError($requestLogId.'->'.$arrResponse);
                    exit(json_encode ($arrResponse));
                }

                $recordFile = $dmtservice -> dmtDirectory($contentRecord, $nameRecord, $typeRecord, $requestPath[2]);

                $log->logInfo($requestLogId.'->Request Directory on Server:'.$recordFile->filePath);

                if (isset($recordFile))
                {

                    if (isset($_FILES['mappingRulesFile'])){
                        $rulesContent = file_get_contents($_FILES['mappingRulesFile']['tmp_name']);
                        if($_FILES['mappingRulesFile']['type'] == 'text/csv'){
                            $arrResponse = $dmtservice -> recordMapping($rulesContent,$_FILES['mappingRulesFile']['name'],
                                $recordFile, $sourceFormat, $targetFormat);

                            $log->logInfo($requestLogId.'->Request ID:'.$arrResponse);

                            header('Content-Type: application/json');
                            exit(json_encode (array('request_id' => $arrResponse)));
                        }
                        else{
                            //http_response_code(422);
                            header("Status: 422 Unprocessable Entity");
                            $arrResponse = "Input mapping rules file is not a CSV file";

                            $log->logError($requestLogId.'->'.$arrResponse);

                            $dmtservice -> removeDirectory($recordFile->filePath);
                            exit(json_encode ($arrResponse));
                        }

                    }
                    else
                    {
                        $mappingFile->filePath = dirname(__FILE__)."/transformationrules";
                        $mappingFile->fileType = 'xslt';

                        switch ($sourceFormat) {
                            case 'LIDO':
                                $mappingFile->fileName = "lidotoedm.xsl";
                                break;
                            case 'MARC':
                                $mappingFile->fileName = "marctoedm.xsl";
                                break;
                            case 'EAD':
                                $mappingFile->fileName = "eadtoedm.xsl";
                                break;
                            default:
                                //http_response_code(422);
                                header("Status: 422 Unprocessable Entity");
                                $arrResponse = 'Unsupported source format.';

                                $log->logError($requestLogId.'->'.$arrResponse);

                                $dmtservice -> removeDirectory($recordFile->filePath);
                                exit(json_encode ($arrResponse));
                        }

                        $log->logInfo($requestLogId.'->Mapping file (XLT) Used:'.$mappingFile->fileName);

                        $arrResponse = $dmtservice -> recordTransformation($recordFile, $mappingFile);
                        header('Content-Type: application/json');
                        exit(json_encode (array('request_id' => $arrResponse)));
                    }

                }else{
                    //http_response_code(404);
                    header("Status: 404 Not Found");
                    $arrResponse = "Error in storing record file(s).";

                    $log->logError($requestLogId.'->'.$arrResponse);

                    exit(json_encode ($arrResponse));
                }


			}
			else{
                //http_response_code(404);
                header("Status: 404 Not Found");
				$arrResponse = "Please provide both source and target formats";

                $log->logError($requestLogId.'->'.$arrResponse);
			}

		}else
        {
            //http_response_code(422);
            header("Status: 422 Unprocessable Entity");
			$arrResponse = "Invalid URL. <br> Please provide url in '/DataMapping/provider/batch/action?parameters' format.";
            $log->logError($requestLogId.'->'.$arrResponse);
        }

		break;
		
	  case 'GET':
          if($dmtservice -> validRequest($requestPath)){
              $log->logInfo($requestLogId.'->Is a valid Request.');

              switch(strtoupper($requestPath[3])){
                  case 'STATUS':
                      if(isset($_GET['request_id'])){
                          header('Content-Type: application/json');
                          $arrResponse = array('status_code' => $dmtservice->getStatus($_GET['request_id']));

                          $log->logInfo($requestLogId.'->Resuest Status:', $arrResponse);

                      }
                      else{
                          $arrResponse = 'request_id is needed for '.strtoupper($requestPath[3]).' actions.';
                          //http_response_code(422);

                          $log->logError($requestLogId.'->'.$arrResponse);

                          header("Status: 422 Unprocessable Entity");
                      }
                      break;

                  case 'FETCH':
                      if(isset($_GET['request_id']))
                          $resultFile = $dmtservice->getResult($_GET['request_id']);
                          if(isset($resultFile)){
                              $file = $resultFile -> filePath."/".$resultFile -> fileName;
                              if (file_exists($file)) {
                                  $log->logInfo($requestLogId.'->Result file sent:'.$file);

                                  header('Content-Description: File Transfer');
                                  header('Content-Type: '. $resultFile -> fileType);
                                  header('Content-Disposition: attachment; filename='.basename($file));
                                  header('Content-Transfer-Encoding: binary');
                                  header('Expires: 0');
                                  header('Cache-Control: must-revalidate');
                                  header('Pragma: public');
                                  header('Content-Length: ' . filesize($file));
                                  ob_clean();
                                  flush();
                                  readfile($file);
                                  exit;
                              }
                          }

                      else{
                          $arrResponse = 'a valid request_id is needed for '.strtoupper($requestPath[3]).' actions.';
                          //http_response_code(422);
                          $log->logError($requestLogId.'->'.$arrResponse);

                          header("Status: 422 Unprocessable Entity");
                      }
                      break;

                  case 'LIST':
                      header('Content-Type: application/json');
                      $arrResponse =  $dmtservice->getSupportedFormatList();

                      $log->logInfo($requestLogId.'->List of supported formats',$arrResponse);

                      exit(json_encode ($arrResponse));
                      break;

                  default:
                      $arrResponse = 'Request '.strtoupper($requestPath[3]).' is not supported.';
                      //http_response_code(404);
                      $log->logError($requestLogId.'->'.$arrResponse);

                      header("Status: 404 Not Found");
                      //exit();
              }

          }else
          {
              //http_response_code(422);
              header("Status: 422 Unprocessable Entity");
              $arrResponse = "Invalid URL. <br> Please provide url in '/DataMapping/provider/batch/action?parameters' format.";
              $log->logError($requestLogId.'->'.$arrResponse);
              //exit();
          }
		break;
		
	  case 'DELETE':
		$arrResponse = "Delete Request";
        $log->logInfo($requestLogId.'->'.$arrResponse);
		break;

	}

	echo json_encode ($arrResponse);
    ob_flush();
?>