<?php
if (true) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}
//Configuration
$access = "CD57A60DEED4CC2C";
$userid = "johnh@oakhillsoftware.com";
$passwd = "sugcij-Kakboz-9momru";
$wsdl = "http://psausabeta.us-east-1.elasticbeanstalk.com/schema/RateWS.wsdl";
$operation = "ProcessRate";
$endpointurl = 'https://onlinetools.ups.com/webservices/Rate';
$outputFileName = "XOLTResult.xml";

$shipperNumber = "812290";
$json =
array(
	"RateRequest" => array( 
		"Request" => array( "RequestOption" => "Rate", "TransactionReference" => array( "CustomerContext" => "123abc" ) ),
		"Shipment" => array(
			"Shipper" => [
				"Name" => "PsaUsa",
				"ShipperNumber" => $shipperNumber,
				"Address" => [
					"AddressLine" => "10522 Corte Jardin Del Mar",
					"City" => "San Diego",
					"StateProvinceCode" => "CA",
					"PostalCode" => "92130",
					"CountryCode" => "US"
				]
			],
			"ShipTo" => [
				"Name" => "PsaUsa",
				"Address" => [
					"AddressLine" => "10526 Corte Jardin Del Mar",
					"City" => "San Diego",
					"StateProvinceCode" => "CA",
					"PostalCode" => "92130",
					"CountryCode" => "US",
					"ResidentialAddressIndicator" => ""
				]
			],
			"ShipFrom" => [
				"Name" => "PsaUsa",
				"Address" => [
					"AddressLine" => "10526 Corte Jardin Del Mar",
					"City" => "San Diego",
					"StateProvinceCode" => "CA",
					"PostalCode" => "92130",
					"CountryCode" => "US"
				]
			],
			"Service" => [
				"Code" => "03",
				"Description" => "Ground"
			],
			"Package" => [
				"PackagingType" => [ 
					"Code" => "02", 
					"Description" => "Rate" 
				], 
				"Dimensions" => [ 
					"UnitOfMeasurement" => [
						 "Code" => "IN", 
						 "Description" => "inches" 
					], 
					"Length" => "5", 
					"Width" => "4",
					"Height" => "3" 
				], 
				"PackageWeight" => [
					"UnitOfMeasurement" => [
						"Code" => "Lbs", 
						"Description" => "pounds" 
					], 
					"Weight" => "1" 
				]
			],
			"ShipmentRatingOptions" => [
				"NegotiatedRatesIndicator" => "-"
			]
		)
	)
);
$json = json_decode(file_get_contents("php://input"));

$jsonend = "https://wwwcie.ups.com/rest/Rate";
//$jsonend = 'https://onlinetools.ups.com/rest/Rate';
$json->UPSSecurity = (object)array( "UsernameToken" => array( "Username" => $userid, "Password" => $passwd ), "ServiceAccessToken" => array( "AccessLicenseNumber" => $access ) );

$server_error = "";
$ch = curl_init();
curl_setopt($ch, CURLOPT_VERBOSE, 1);
curl_setopt($ch, CURLOPT_URL, $jsonend);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($json));
curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type" => "application/json; charset=utf-8"));
//curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1_2'); //SSLVERSION 6 forces tls1.2
//CURL_SSLVERSION_TLSv1_2
curl_setopt($ch, CURLOPT_SSLVERSION, 6);//CURL_SSLVERSION_TLSv1_2
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'SSLv3');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$server_output = curl_exec($ch);
if ($server_output === false) $server_error .= "error:".curl_error ($ch );
print $server_error;
curl_close ($ch);
$vals = json_decode($server_output);
print 'standard: $'.$vals->RateResponse->RatedShipment->TotalCharges->MonetaryValue;
print 'negotiated: $'.$vals->RateResponse->RatedShipment->NegotiatedRateCharges->TotalCharge->MonetaryValue;

// if used as a relay
header('Content-Type: application/json');
ob_clean();
print $server_output;
?>
