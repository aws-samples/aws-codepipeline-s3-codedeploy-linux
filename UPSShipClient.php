<html>
<form method="GET">
Service: <select name="service"><option value="01">Next Day</option><option value="02">2nd Day</option><option value="03">Ground</option></select>
Dest Zip: <input name="zip" value="">
Weight: <input name="weight" value="">
<input type="submit" value="Price">
</form>
<?php

if (true) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

function getRate($params) {
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
					"Name" => "",
					"Address" => [
						"PostalCode" => $params['DestZip'],
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
					"Code" => $params['ShipCode']
				],
				"Package" => [
					"PackagingType" => [ 
						"Code" => "02", 
						"Description" => "Rate" 
					], 
					"PackageWeight" => [
						"UnitOfMeasurement" => [
							"Code" => "LBS", 
							"Description" => "pounds" 
						], 
						"Weight" => $params['Weight']
					],
					"Dimensions" => [ 
						"UnitOfMeasurement" => [
							 "Code" => "IN", 
							 "Description" => "inches" 
						], 
						"Length" => $params['Length'], 
						"Width" => $params['Width'],
						"Height" => $params['Height']
					], 
					"DimWeight" => [
						"UnitOfMeasurement" => [ "Code" => "LBS" ],
						"Weight" => $params['DimWeight']
					]
				],
				"ShipmentRatingOptions" => [
					"NegotiatedRatesIndicator" => "-"
				]
			)
		)
	);

	//print json_encode($json);
	$server_error = "";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_URL, "http://psausabeta.us-east-1.elasticbeanstalk.com/ups.php");
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
	//print $server_error;
	$vals = json_decode($server_output);

	return $vals;
}

$params = [
	"DestZip" => "92130",
	"Weight" => "2",
	"Length" => "10",
	"Width" => "8",
	"Height" => "4",
	"ShipCode" => "03",
	"DimWeight" => "1"
];
if ( isset($_GET['zip']) ) $params['DestZip'] = $_GET['zip'];
if ( isset($_GET['weight']) ) $params['Weight'] = $_GET['weight'];
if ( isset($_GET['service']) ) $params['ShipCode'] = $_GET['service'];
$vals = getRate($params);
print 'ground<br/>';
print 'package: '.$params['Length'].'x'.$params['Width'].'x'.$params['Height'].' '.$params['Weight'].' lbs. ('.$params['DimWeight'].') to zip '.$params['DestZip'].'<br/>';
print 'standard: $'.$vals->RateResponse->RatedShipment->TotalCharges->MonetaryValue.'<br/>';
print 'negotiated: $'.$vals->RateResponse->RatedShipment->NegotiatedRateCharges->TotalCharge->MonetaryValue.'<br/>';

?>
