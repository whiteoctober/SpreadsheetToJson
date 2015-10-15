<?php

// Composer loading in our dependencies
require 'vendor/autoload.php';
use Google\Spreadsheet\DefaultServiceRequest;
use Google\Spreadsheet\ServiceRequestFactory;

class SpreadsheetToJson{
	
	private $applicationName;
	
	function __construct($applicationName = "Spreadsheet to Json") {
		$this->applicationName = $applicationName;
	}
	
	function generateSpreadsheetData($p12file, $serviceEmail, $spreadsheetUrl){
		
		
		if(!isset($p12file)){
			throw new Exception("No file set");
		}else if(!isset($serviceEmail)){
			throw new Exception("No Google service email set");
		}else if(!isset($spreadsheetUrl)){
			throw new Exception("No spreadsheet URL set");
		}
		
		$googleKey = file_get_contents($p12file);
		$scope = array('https://spreadsheets.google.com/feeds', 'https://www.googleapis.com/auth/drive');
		
		$credentials = new Google_Auth_AssertionCredentials($serviceEmail, $scope, $googleKey);
		
		$client = new Google_Client();
		$client->setApplicationName($applicationName);
		$client->setAssertionCredentials($credentials);
		
	
		if ($client->getAuth()->isAccessTokenExpired()) {
			$client->getAuth()->refreshTokenWithAssertion();
		}
		
		// Issue with setting the scope inside of Google_Auth_AssertionCredentials so we set them here
		$client->addScope('https://spreadsheets.google.com/feeds', 'https://www.googleapis.com/auth/drive');
		//$client->createAuthUrl(); <-- Possibly not needed for this application but if any issues, uncomment it.
		
		$objToken = json_decode($client->getAccessToken(), true);
		$accessToken = $objToken['access_token'];
		
		$serviceRequest = new DefaultServiceRequest($accessToken);
		ServiceRequestFactory::setInstance($serviceRequest);
		
		$spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
		$spreadsheetId = self::convertSpreadsheetUrlToId($spreadsheetUrl);
		
		if(!isset($spreadsheetId)){
			throw new Exception("SpreadsheetId is null, make sure that you have a valid Google Spreadsheet Url");
		}
		
		$spreadsheet = $spreadsheetService->getSpreadsheetById($spreadsheetId);
		
		$json = array();
		
		foreach($spreadsheet->getWorksheets() as $entry){
			$listFeed = $entry->getListFeed();
			foreach($listFeed->getEntries() as $listFeedEntry){
				$value = $listFeedEntry->getValues();
				
				$json[] = $value;
			}
		}
		return $json;
	}
	
	static function convertSpreadsheetUrlToId($url){
		
		/*
		 * This function strips a full Google Spreadsheet url of everything but the Id, which is required to access the actual spreadsheet. 
		 * An follow url: https://docs.google.com/spreadsheets/d/1dr4YQVDfWIa9zFYjPPsjVuID4ubMLilmRfxo1fE_rsc/edit#gid=0"
		 * will be converted to: 1dr4YQVDfWIa9zFYjPPsjVuID4ubMLilmRfxo1fE_rsc
		 */
		
		if(stripos($url, "/d/") !== false){
			$startIdPos = strpos($url, "/d/") + 3;
			$urlStart = substr($url, $startIdPos);
			
			$endIdPos = strpos($urlStart, "/");
			$urlEnd = substr($urlStart, 0, $endIdPos);
			
			return $urlEnd;
		}
		
		return null;
	}
}