<?php

/**
 * User: NaeemM
 * Date: 30/03/2016
 */

require_once("../dmtservice.php");

class DmtController
{

    private $dmtService;
    /**
     * DmtController constructor.
     */
    public function __construct()
    {
        $this->dmtService =  new service();
    }

    public function getStatus($request, $response, $args){

        echo "this is status";
    }

    public function fetchMappingResult($request, $response, $args){
        $response = $response->withHeader('Content-type', 'application/json');
        $responseBody = array();
        $queryParameters = $request->getQueryParams();
        $responseBody['requestparams'] = $queryParameters;

        $validityCheck = $this->validateFetchRequest($queryParameters);
        if(!$validityCheck['valid']){           // Not a valid request
            return $response->withStatus(404)
                ->write(json_encode(array('error_message' => $validityCheck['error_message'])));
        }

        $response->getBody()->write(json_encode($responseBody));
        return $response;
    }
    public function validateFetchRequest($queryParameters){
        /* Check if fetch has request_id. */
        if(empty($queryParameters['request_id']))
            return array('valid' => false, 'error_message' => ' request_id parameter is missing.');

        return array('valid' => true);
    }
    public function getList($request, $response, $args){

        echo "this is list";
    }

    public function getStatistics($request, $response, $args){

        echo "this is stats";
    }

    public function dataMapping($request, $response, $args){
        $response = $response->withHeader('Content-type', 'application/json');
        $responseBody = array();

        $requestParameters = $request->getParams();
        $pathParameters = $request->getAttribute('route')->getArguments();
        $responseBody['reqParama'] = $requestParameters; //TBR
        $responseBody['requestattributesprovider'] =  $pathParameters; //TBR

        $validityCheck = $this->validateMappingRequest($pathParameters, $requestParameters, $_FILES);
        if(!$validityCheck['valid']){           // Not a valid request
            return $response->withStatus(404)
                ->write(json_encode(array('error_message' => $validityCheck['error_message'])));
        }

        $mappingRules = file_get_contents($_FILES['mappingRulesFile']['tmp_name']);
        $mappingRulesFileName = $_FILES['mappingRulesFile']['name'];

        if(!empty($_FILES['record']))
            $dataParameter = 'record';
        elseif(!empty($_FILES['records']))
            $dataParameter = 'records';

        $data = file_get_contents($_FILES[$dataParameter]['tmp_name']);
        $dataFileName = $_FILES[$dataParameter]['name'];
        $dataType = $_FILES[$dataParameter]['type'];

        $dataFile = $this->dmtService->dmtDirectory($data, $dataFileName, $dataType, $pathParameters['batch']);
        file_put_contents("tmp/datafile.txt", print_r($dataFile, true));
        if(!empty($dataFile)){
            $requestSubmission = $this->dmtService->recordMapping($mappingRules, $mappingRulesFileName,
                $dataFile, $requestParameters['sourceFormat'], $requestParameters['targetFormat']);

            file_put_contents("tmp/requestsubmission.txt", print_r($requestSubmission, true));
        }

        $responseBody['requestsubmission'] =  $requestSubmission; //TBR

        $response->getBody()->write(json_encode($responseBody));
        //$response = $response->withHeader('Content-type', 'application/json');
        return $response;
    }

    public function validateMappingRequest($pathParameters, $requestParameters, $files){

        /* Check if url path contains provider, batch name and action parameters. */
        if(empty($pathParameters['provider']) || empty($pathParameters['batch']) || empty($pathParameters['action']))
            return array('valid' => false, 'error_message' => 'Url should contain name of the provider, a batch name and an action /provider/batch/action .');

        /* Check if action is transform. */
        if(strtolower($pathParameters['action']) != "transform")
            return array('valid' => false, 'error_message' => $pathParameters['action']. ' is not a valid mapping action.');

        /* Check if source and target parameters are given. */
        if(empty($requestParameters['sourceFormat']) || empty($requestParameters['targetFormat']))
            return array('valid' => false, 'error_message' => 'Please provide both source and target formats.');

        /* Check if mapping file and data files are attached. */
        if(empty($files) || sizeof($files) < 2)
            return array('valid' => false, 'error_message' => 'Both a mapping file and a data file is needed.');

        if(empty($files['mappingRulesFile']))
            return array('valid' => false, 'error_message' => 'Mapping file not provided.');

        if(empty($files['record']) && empty($files['records']))
            return array('valid' => false, 'error_message' => 'Data file not provided.');

        if(!empty($files['record']) && !empty($files['records']))
            return array('valid' => false, 'error_message' => 'Record and records parameters cannot be handled together in one request.');

            return array('valid' => true);
    }


}