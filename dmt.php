<?php
	require_once("dmtservice.php");
	require_once("/util/dmtdatafiles.php");	
?>

<?php
	$dmtservice = new service;
	
	$requestmethod = $_SERVER['REQUEST_METHOD'];
	$requestPath = explode("/", substr(@$_SERVER['PATH_INFO'], 1));
	$requestParameters = $_SERVER['QUERY_STRING'];
	
	//echo $requestmethod;
	//echo "<br>";
	$arrResponse = "";
	switch ($requestmethod) {
	
	  case 'PUT':
		$arrResponse = $_PUT;  
		break;
		
	  case 'POST':
		if($dmtservice -> validRequest($requestPath)){
			
			if (isset($_POST['sourceFormat']) && isset($_POST['targetFormat'])){
				$responseFile = new DataFile();
				
				if(isset($_FILES['records'])){	
					$recordContent = file_get_contents($_FILES['records']['tmp_name']);  								
					$responseFile = $dmtservice -> dmtCore($recordContent, $_FILES['records']['name'], $_FILES['records']['type'] , $_POST['sourceFormat'], $_POST['targetFormat']);
				}
								
				if(isset($_FILES['record'])){
					$recordContent = file_get_contents($_FILES['record']['tmp_name']);  								
					$responseFile = $dmtservice -> dmtCore($recordContent, $_FILES['record']['name'], $_FILES['record']['type'] , $_POST['sourceFormat'], $_POST['targetFormat']);
				}				
												
				$file = $responseFile -> filePath."/".$responseFile -> fileName;
				if (file_exists($file)) {
					header('Content-Description: File Transfer');
					header('Content-Type: '. $responseFile -> fileType);
					header('Content-Disposition: attachment; filename='.basename($file));
					header('Content-Transfer-Encoding: binary');
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . filesize($file));
					ob_clean();
					flush();
					readfile($file);
					//$dmtservice -> removeDirectory($responseFile -> filePath);
					exit;
				}
				
			}
			else{
				$arrResponse = "Please provide both source and target formats";
			}
			
		}else
			$arrResponse = "Invalid URL. <br> Please provide url in '/DataMapping/provider/batch/action?parameters' format.";

		break;
		
	  case 'GET':
		$arrResponse = $dmtservice -> getRequest($requestPath , $requestParameters);  
		break;
		
	  case 'DELETE':
		$arrResponse = "Delete Request"; 
		break;

	}

	echo json_encode ($arrResponse);
	
?>