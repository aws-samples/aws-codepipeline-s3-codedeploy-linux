<?
header('Content-Type: application/json');
if (false) {
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}


/*
transactions, rollback automatically in complete trans
$conn->StartTrans();
$conn->Execute($sql);
$conn->Execute($Sql2);
$conn->HasFailedTrans();
$this->db->Affected_Rows(),Insert_ID()
*/
	
require_once(dirname(__FILE__)."/../../dev/api/adodb/adodb.inc.php");
require_once(dirname(__FILE__)."/../../../TriathlonClub/phpmailer/class.phpmailer.php");

class MyMailery extends PHPMailer {
    // Set default variables for all new objects
    var $Host     = "smtp.sendgrid.net";
    var $Port     = 25; // 25,465
    //var $SMTPSecure = 'tls';
    var $Mailer   = "smtp";
    var $WordWrap = 150;
	var $SMTPAuth = true;
	var $Username = "apikey";
	var $Password = "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";
	//var $SMTPDebug = true;
    // Replace the default error_handler
    function error_handler($msg) {
        print $msg;
    }
}

class PsaUsa {
	public $db;
	public $token, $userid;
	private $salt = '!~#3';
	private $upsShipperNumber = "812290";


	public function __construct($token=null) {
		$this->db = NewADOConnection('mysqli');
		//$this->db->Connect('localhost', 'fax', 'projectx', 'liondesk');
		$this->db->Connect('psausa.ckabb9eqgcnu.us-east-1.rds.amazonaws.com', 'apiuser', '12psausa**', 'psausa');
		$this->db->SetFetchMode(ADODB_FETCH_ASSOC);
		
		if ( !$token ) $this->token = $this->generateUuid();
		else $this->token = $token;

		$this->userid = 0;
		$sql = "SELECT ID FROM psausa.users WHERE Token = ? AND Status > 0";
		$bindvars = [$this->token];
		$result = $this->db->Execute($sql, $bindvars);
		if ( $result->RecordCount() > 0 ) $this->userid = $result->Fields("ID");
	}

	public static function generateUuid() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
			mt_rand( 0, 0xffff ),
			mt_rand( 0, 0x0fff ) | 0x4000,
			mt_rand( 0, 0x3fff ) | 0x8000,
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

	public static function sqlEsc($s) {
		return mysql_escape_string($s);
	}
	
	public function sendMail($criteria) {
		//$to,$subject,$message,$altMessage="", $attachment = ""
		$mail = new MyMailery;
		$mail->From     = "sales@psausa.com";
		$mail->FromName = "Sales";
		$mail->AddAddress($criteria->to, "");
		//$mail->AddAddress("johnh@oakhillsoftware.com", "");
		$mail->Subject = $criteria->subject;
		$mail->Body    = $criteria->message;
		if ( $criteria->altMessage == "" ) $criteria->altMessage = $criteria->message;
		$mail->AltBody = $criteria->altMessage;
		if ( $criteria->attachment != "" ) {
			//$mail->AddAttachment($attachment, "score_report.pdf");
		}
		$mail->IsHTML(true);
		if(!$mail->Send()) {
			return 0;
		}
		else return 1;
	}

	public function session($criteria) {
		//
		$sql = "SELECT ID FROM psausa.session WHERE Token = ?";
		$bindvars = [$this->token];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
		if ( $result && $result->RecordCount() == 0 ) {
			$sql = "INSERT INTO psausa.session (Token) VALUES (?)";
			$bindvars = [$this->token];
			$result = $this->db->Execute($sql,$bindvars);
			if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
		}
		return $this->token;
	}

	public function login($criteria) {
		//token or user, pass
		$token = '';
		if ( isset($criteria->user) && isset($criteria->pass) ) {
			$sql = "SELECT ID, Token FROM psausa.users WHERE User = ? AND Pass = MD5(CONCAT(?,?)) AND Status > 0";
			$bindvars = [$criteria->user, $this->salt, $criteria->pass];
			$result = $this->db->Execute($sql, $bindvars);
			if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
			if ( $result && $result->RecordCount() > 0 ) {
				if ( $result->Fields("Token") == "" ) {
					$token = $this->generateUuid();
					$sql = "UPDATE psausa.users SET Token = ? WHERE ID = ?";
					$bindvars = [$token,$result->Fields("ID")];
					$result = $this->db->Execute($sql,$bindvars);
					if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
				}
				else $token = $result->Fields("Token");
			}
			else throw new Exception("No login found.");
		}
		else {
			$sql = "SELECT Token FROM psausa.users WHERE Token = ? AND Status > 0";
			$bindvars = [$this->token];
			$result = $this->db->Execute($sql,$bindvars);
			if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
			if ( $result && $result->RecordCount() > 0 ) {
				$token = $result->Fields("Token");
			}
			else throw new Exception('Token not valid');
		}
		return $token;
	}
	
	public function signup($criteria) {
		if (!filter_var($criteria->email, FILTER_VALIDATE_EMAIL)) {
			throw new Exception('Not a valid email address.');
		}
		if ( $criteria->user == '' || $criteria->pass == '' ) {
			throw new Exception('Not a valid username or password.');
		}
		$token = '';
		$sql = "SELECT ID FROM psausa.users WHERE User = ?";
		$bindvars = [$criteria->user];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
		if ( $result && $result->RecordCount() == 0 ) {
			$token = $this->generateUuid();
			$validate = $this->generateUuid();
			$sql = "INSERT INTO psausa.users (FirstName,LastName,Email,User,Pass,Validate,Token,Status) VALUES ('','',?,?,MD5(CONCAT(?,?)),?,?,-1)";
			$bindvars = [$criteria->email, $criteria->user, $this->salt, $criteria->pass,$validate,$token];
			$result = $this->db->Execute($sql, $bindvars);
			if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
			else {
				$link = 'http://www.psausa.com/demo/#/validate/'.$validate;
				$mailsent = $this->sendMail((object)array('to'=>$criteria->email,'subject'=>'Welcome to PsaUsa - Validate Your Email','message'=>'<html><body><div>Welcome to www.psausa.com</div><div>Validate your signup by clicking this link (or copying the URL into the address field of you browser)</div><div>'.$link.'</div></body></html>'));
			}
		}
		else throw new Exception('User already exists.');
		return $token;
	}
	
	public function validate($criteria) {
		$updated = 0;
		$sql = "UPDATE psausa.users SET Status = 1, Validate = '' WHERE Validate <> '' AND Validate = ?";
		$bindvars = [$criteria->validate];
		$result = $this->db->Execute($sql,$bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().": ".$this->db->ErrorMsg());
		else {
			$updated = $this->db->Affected_Rows();
		}
		return $updated;
	}
	
	public function addCart($criteria) {
		//sku,quantity
		$sql = "SELECT Cart FROM psausa.cart WHERE Status = 1 AND Token = ? ORDER BY Updated DESC LIMIT 1";
		$bindvars = [$this->token];
		$result = $this->db->Execute($sql,$bindvars);
		if ( $result && $result->RecordCount() == 1 ) $cart = $result->Fields("Cart");
		else {
			$cart = $this->generateUuid();
			$sql = "INSERT INTO psausa.cart (Token, Cart) VALUES (?, ?)";
			$bindvars = [$this->token, $cart];
			$result = $this->db->Execute($sql,$bindvars);
			if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		}
		// check to see if this sku is already in the cart
		$sql = "SELECT Quantity FROM psausa.cart_items WHERE Cart = ? AND SKU = ?";
		$bindvars = [$cart,$criteria->sku];
		$result = $this->db->Execute($sql, $bindvars);
		if ( $result && $result->RecordCount() > 0 ) {
			$criteria->quantity = $criteria->quantity + $result->Fields('Quantity');
			$price = $this->getPrice($criteria);
			$sql = "UPDATE psausa.cart_items SET Quantity = ?, Price = ? WHERE Cart = ? AND SKU = ?";
			$bindvars = [$criteria->quantity, $price, $cart, $criteria->sku];
		}
		else {
			$price = $this->getPrice($criteria);
			$sql = "INSERT INTO psausa.cart_items (Cart, SKU, Quantity, Price) VALUES (?, ?, ?, ?)";
			$bindvars = [$cart,$criteria->sku,$criteria->quantity, $price];
		}
		$result = $this->db->Execute($sql,$bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg().":".$sql);
		
		$this->specialPricing();
		return $this->getCart($criteria);
	}
	
	public function specialPricing() {
		$sql = "SELECT psausa.cart_items.SKU, psausa.cart_items.Quantity, psausa.cart_items.Price, psausa.cart_items.ID
		FROM psausa.cart_items
		INNER JOIN psausa.cart ON psausa.cart.Cart = psausa.cart_items.Cart
		WHERE psausa.cart.Status = 1 AND psausa.cart.Token  = ?";
		$bindvars = [$this->token];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg().":".$sql);
		$numSKU = $result->RecordCount();
		while ( $result && !$result->EOF ) {
			$row = $result->FetchRow();
			switch ($row['SKU']) {
				case '9-001'://Special Offer: $19.50 each - when purchased with other items - 5 bag minimum purchase.
					if ( $numSKU >= 2 && $row['Quantity'] >= 5 ) {
						$price = 19.50;
					}
					else {
						$price = $this->getPrice((object)array('sku'=>$row['SKU'],'quantity'=>$row['Quantity']));
					}
					$sql = "UPDATE psausa.cart_items SET Price = ? WHERE ID = ?";
					$bindvars = [$price, $row['ID']];
					$result = $this->db->Execute($sql, $bindvars);
					if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg().":".$sql);
					break;
				case '9-001B'://$15.00 each - when purchased with other items - 5 bag minimum purchase.
					if ( $numSKU >= 2 && $row['Quantity'] >= 5 ) {
						$price = 15.00;
					}
					else {
						$price = $this->getPrice((object)array('sku'=>$row['SKU'],'quantity'=>$row['Quantity']));
					}
					$sql = "UPDATE psausa.cart_items SET Price = ? WHERE ID = ?";
					$bindvars = [$price, $row['ID']];
					$result = $this->db->Execute($sql, $bindvars);
					if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg().":".$sql);
					break;
			}
		}
	}
	
	public function getPrice($criteria) {
		//sku,quantity
		$sql = "SELECT Price FROM psausa.product_price WHERE SKU = ? AND Quantity <= ? ORDER BY Quantity DESC LIMIT 1";
		$bindvars = [$criteria->sku, $criteria->quantity];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		if ( $result->RecordCount() > 0 ) return $result->Fields("Price");
		else return 0;
	}
	
	public function deleteCart($criteria) {
		//index
		$sql = "DELETE psausa.cart_items
		FROM psausa.cart_items
		INNER JOIN psausa.cart ON psausa.cart.Cart = psausa.cart_items.Cart
		WHERE psausa.cart.Status = 1 AND psausa.cart.Token  = ? AND psausa.cart_items.ID = ?";

		$bindvars = [$this->token,$criteria->id];
		$result = $this->db->Execute($sql, $bindvars);
		
		$this->specialPricing();
		return $this->getCart($criteria);
	}
	
	public function getCart($criteria) {
		//
		$sql = "SELECT psausa.cart_items.ID AS id, psausa.cart_items.SKU AS sku, psausa.cart_items.Quantity AS quantity, psausa.cart_items.Price AS price, psausa.products.Title AS title
		FROM psausa.cart_items 
		INNER JOIN psausa.cart ON psausa.cart_items.Cart = psausa.cart.Cart 
		INNER JOIN psausa.products ON psausa.cart_items.SKU = psausa.products.SKU
		WHERE psausa.cart.Status = 1 AND psausa.cart.Token = ?";
		$bindvars = [$this->token];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$cart = Array();
		while ($result && !$result->EOF) {
			$row = $result->FetchRow();
			$cart[] = $row;
		}
		
		return $cart;
	}
	
	public function getShipping($criteria) {
		//zip, ?combined
		$combined = false;
		if ( isset($criteria->combined) ) $combined = $criteria->combined;
		$shipCost = 0;
		$allShip = 1;
		$destZip = $criteria->zip;
		$sql = "SELECT psausa.cart_items.ID AS id, psausa.cart_items.Quantity as quantity, psausa.products.MIN_QTY as min_qty, psausa.products.DIM_WT as dim_weight, psausa.products.WEIGHT as weight, psausa.products.WT_EACH as weight_each, psausa.products.SHIPS_UPS as ships_ups
		FROM psausa.cart_items 
		INNER JOIN psausa.cart ON psausa.cart_items.Cart = psausa.cart.Cart 
		INNER JOIN psausa.products ON psausa.cart_items.SKU = psausa.products.SKU
		WHERE psausa.cart.Status = 1 AND psausa.cart.Token = ?";
		$bindvars = [$this->token];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$totalWeight = 0;
		$dimWeight = 0;
		while ($result && !$result->EOF) {
			$row = $result->FetchRow();
			if ($row['ships_ups'] == 'Y') {
				$totalWeight += ($row['quantity']/$row['min_qty'])*$row['weight'];
				$dimWeight += ceil((($row['quantity']/$row['min_qty'])*$row['dim_weight'])/139);
				if ( !$combined ) {
					$rateParams = array(
					"destZip" => (string)$destZip,
					"weight" => (string)$totalWeight,
					"shipCode" => "03",
					"dimWeight" => (int)$dimWeight
					);
					$lineCost = $this->upsRate((object)$rateParams);
					$totalWeight = 0;
					$dimWeight = 0;
					$shipCost += $lineCost;
				}
			}
			else{
				$allShip = 0;
			}
		}
		
		if ( $combined ) {
				$rateParams = array(
				"destZip" => (string)$destZip,
				"weight" => (string)$totalWeight,
				"shipCode" => "03",
				"dimWeight" => (int)$dimWeight
				);
				$lineCost = $this->upsRate((object)$rateParams);
				$shipCost += $lineCost;
		}
		
		return array('shipCost' => $shipCost, 'allShip' => $allShip, 'destZip' => $destZip, 'combined' => $combined);
	}

	public function getProduct($criteria) {
		$sql = "SELECT p.title, p.sku, p.picture, p.min_qty, p.description, p.main, p.sub_1, p.sub_2, p.sub_3,
		pp.quantity, pp.price 
		FROM psausa.products p 
		LEFT JOIN psausa.product_price pp ON p.SKU = pp.SKU 
		WHERE p.SKU = ?
		ORDER BY pp.Quantity ASC";
		$bindvars = [$criteria->sku];
		$result = $this->db->Execute($sql,$bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$product = new StdClass;
		while ($result && !$result->EOF) {
			$row = $result->FetchRow();
			if (!isset($product->sku)) {
				$product->sku = trim($row['sku']);
				$product->title = trim($row['title']);
				$product->description = trim($row['description']);
				$product->picture = trim($row['picture']);
				$product->img = "//www.psausa.com/images/products/".trim($row['picture']);
				$product->path = $row['main'];
				if ($row['sub_1'] != '') $product->path .= '/'.trim($row['sub_1']);
				if ($row['sub_2'] != '') $product->path .= '/'.trim($row['sub_2']);
				if ($row['sub_3'] != '') $product->path .= '/'.trim($row['sub_3']);
				$product->main = trim($row['main']);
				$product->sub1 = trim($row['sub_1']);
				$product->sub2 = trim($row['sub_2']);
				$product->sub3 = trim($row['sub_3']);
				$product->minQty = trim($row['min_qty']);
				$product->prices = Array();
			}
			if ( $row['quantity'] > 0 ) {
				$product->prices[] = array(intval($row['quantity']), floatval($row['price']));
			}
		}
		return $product;
	}
	
	public function findProducts($criteria) {
		//keywords, start, number
		$results = Array();
		
		$start=0;
		if (isset($criteria->start)) $start = $criteria->start;
		$number=5;
		if (isset($criteria->number)) $number = $criteria->number;
		
		$sql = "SELECT Count(psausa.products.SKU) AS total
		FROM psausa.products
		WHERE psausa.products.MAIN <> '' AND (1=0
		";
		$sql .= " OR psausa.products.SKU LIKE '".$this->sqlEsc($criteria->keywords)."%'";
		$sql .= " OR psausa.products.TITLE LIKE '%".$this->sqlEsc($criteria->keywords)."%'";
		$sql .= " ) ";
		$total = $this->db->Execute($sql);
		if ( $total && $total->RecordCount() > 0 ) $results['total'] = $total->Fields('total');
		else $results['total'] = -1;

		$sql = "SELECT p.TITLE, p.SKU, p.PICTURE, p.MIN_QTY, 
		pp.Quantity, pp.Price 
		FROM psausa.products p 
		INNER JOIN 
		(
		SELECT psausa.products.SKU
		FROM psausa.products
		WHERE psausa.products.MAIN <> '' AND ( 1=0
		";
		$sql .= " OR psausa.products.SKU LIKE '".$this->sqlEsc($criteria->keywords)."%'";
		$sql .= " OR psausa.products.TITLE LIKE '%".$this->sqlEsc($criteria->keywords)."%'";
		$sql .= " ) ";
		$sql .= " ORDER BY psausa.products.SKU ASC LIMIT ".$start.",".$number;
		$sql .= ") pl ON p.SKU = pl.SKU
		LEFT JOIN psausa.product_price pp ON p.SKU = pp.SKU 
		WHERE 1=1
		ORDER BY p.SKU ASC, pp.Quantity ASC";
		$results['debug'] = $sql;
		$result = $this->db->Execute($sql);
		$products = Array();
		$sku = "";
		$idx = -1;
		$price_qty = array();
		while ($result && !$result->EOF) {
			$row = $result->FetchRow();
			if ( $row['SKU'] != $sku ) {
				$idx++;
				$product = new StdClass;
				$product->sku = trim($row['SKU']);
				$product->title = trim($row['TITLE']);
				$product->picture = trim($row['PICTURE']);
				$product->img = "//www.psausa.com/images/products/".trim($row['PICTURE']);
				$product->minQty = trim($row['MIN_QTY']);

				//$product->altprices = Array();
				$product->prices = Array();
				$products[$idx] = $product;
			}
			if ( $row['Quantity'] > 0 ) {
				//$products[$idx]->altprices[$row['Quantity']] = floatval($row['Price']);
				//if ( !in_array(intval($row['Quantity']), $price_qty) ) $price_qty[] = intval($row['Quantity']);
				$products[$idx]->prices[] = [intval($row['Quantity']),floatval($row['Price'])];
			}
			$sku = $row['SKU'];
		}
		/*
		sort($price_qty);
			
		// go back and fill in empty prices just for fun
		foreach ($products as $product) {
			foreach ($price_qty as $qty) {
				if ( !isset($product->altprices[$qty]) ) $product->altprices[$qty] = 0;
			}
			ksort($product->altprices);
			$product->prices = Array();
			foreach($product->altprices as $qty=>$price) $product->prices[] = [intval($qty),$price];
			unset($product->altprices);
		}
		$results['price_qty'] = $price_qty;
		*/
		$results['products'] = $products;
		
		return $results;
	}
	
	public function getCategories($criteria) {
		//path,start,number
		$results = Array();
		$path = "/";
		if (isset($criteria->path)) $path = $criteria->path;
		$c = explode('/', $path); // "/Adhesives/Glue" is and array ["","Adhesives","Glue"]
		if ( $c[count($c)-1] == "" ) array_splice($c, count($c)-1, 1);
		$name = $c[count($c)-1];
		
		$sql = "SELECT MAIN AS name, (SELECT PICTURE FROM psausa.products AS p WHERE p.MAIN = psausa.products.MAIN AND p.PICTURE <> '' LIMIT 1) AS picture FROM psausa.products WHERE MAIN <> '' GROUP BY MAIN ORDER BY Main ASC";
		if ( count($c) > 1 ) $sql = "SELECT SUB_1 AS name, (SELECT PICTURE FROM psausa.products AS p WHERE p.SUB_1 = psausa.products.SUB_1 AND MAIN = '".$this->sqlEsc(urldecode($c[1]))."' AND p.PICTURE <> '' LIMIT 1) AS picture FROM psausa.products WHERE SUB_1 <> '' AND MAIN = '".$this->sqlEsc(urldecode($c[1]))."' GROUP BY SUB_1 ORDER BY SUB_1 ASC";
		if ( count($c) > 2 ) $sql = "SELECT SUB_2 AS name, (SELECT PICTURE FROM psausa.products AS p WHERE p.SUB_2 = psausa.products.SUB_2 AND SUB_1 = '".$this->sqlEsc(urldecode($c[2]))."' AND MAIN = '".$this->sqlEsc(urldecode($c[1]))."' AND p.PICTURE <> '' LIMIT 1) AS picture FROM psausa.products WHERE SUB_2 <> '' AND MAIN = '".$this->sqlEsc(urldecode($c[1]))."' AND SUB_1 = '".$this->sqlEsc(urldecode($c[2]))."' GROUP BY SUB_2 ORDER BY SUB_2 ASC";
		if ( count($c) > 3 ) $sql = "SELECT SUB_3 AS name, (SELECT PICTURE FROM psausa.products AS p WHERE p.SUB_3 = psausa.products.SUB_3 AND SUB_2 = '".$this->sqlEsc(urldecode($c[3]))."' AND SUB_1 = '".$this->sqlEsc(urldecode($c[2]))."' AND MAIN = '".$this->sqlEsc(urldecode($c[1]))."' AND p.PICTURE <> '' LIMIT 1) AS picture FROM psausa.products WHERE SUB_3 <> '' AND MAIN = '".$this->sqlEsc(urldecode($c[1]))."' AND SUB_1 = '".$this->sqlEsc(urldecode($c[2]))." AND SUB_2 = '".$this->sqlEsc(urldecode($c[3]))."' GROUP BY SUB_3 ORDER BY SUB_3 ASC";

		$categories = Array();
		$result = $this->db->Execute($sql);
		while($result && !$result->EOF) {
			$row = $result->FetchRow();
			$cat = new StdClass;
			$cat->name = $row['name'];
			$cat->picture = $row['picture'];
			$cat->img = "//www.psausa.com/images/products/".$row['picture'];
			$categories[] = $cat;
		}
		$results['path'] = $path;
		$results['debug'] = $sql;
		$results['name'] = $name;
		$results['categories'] = $categories;
		
		$products = Array();
		if ( count($categories) == 0 ) {
			$start=0;
			if (isset($criteria->start)) $start = $criteria->start;
			$number=5;
			if (isset($criteria->number)) $number = $criteria->number;
			
			$sql = "SELECT Count(psausa.products.SKU) AS total
			FROM psausa.products
			WHERE 1=1
			";
			if ( count($c) > 1 ) $sql .= " AND Main = '".$this->sqlEsc(urldecode($c[1]))."' ";
			if ( count($c) > 2 ) $sql .= " AND SUB_1 = '".$this->sqlEsc(urldecode($c[2]))."' ";
			if ( count($c) > 3 ) $sql .= " AND SUB_2 = '".$this->sqlEsc(urldecode($c[3]))."' ";
			if ( count($c) > 4 ) $sql .= " AND SUB_3 = '".$this->sqlEsc(urldecode($c[4]))."' ";
			$total = $this->db->Execute($sql);
			if ( $total && $total->RecordCount() > 0 ) $results['total'] = $total->Fields('total');
			else $results['total'] = -1;

			
			$sql = "SELECT p.TITLE, p.SKU, p.PICTURE, p.MIN_QTY, 
			pp.Quantity, pp.Price 
			FROM psausa.products p 
			INNER JOIN 
			(
			SELECT psausa.products.SKU
			FROM psausa.products
			WHERE 1=1
			";
			if ( count($c) > 1 ) $sql .= " AND Main = '".$this->sqlEsc(urldecode($c[1]))."' ";
			if ( count($c) > 2 ) $sql .= " AND SUB_1 = '".$this->sqlEsc(urldecode($c[2]))."' ";
			if ( count($c) > 3 ) $sql .= " AND SUB_2 = '".$this->sqlEsc(urldecode($c[3]))."' ";
			if ( count($c) > 4 ) $sql .= " AND SUB_3 = '".$this->sqlEsc(urldecode($c[4]))."' ";
			$sql .= "ORDER BY psausa.products.SKU ASC LIMIT ".$start.",".$number;
			$sql .= ") pl ON p.SKU = pl.SKU
			LEFT JOIN psausa.product_price pp ON p.SKU = pp.SKU AND p.MIN_QTY = pp.Quantity
			WHERE 1=1
			ORDER BY p.SKU ASC, pp.Quantity ASC";
			//AND p.MIN_QTY = pp.Quantity
			
			$result = $this->db->Execute($sql);
			$sku = "";
			$idx = -1;
			$price_qty = array();
			while($result && !$result->EOF) {
				$row = $result->FetchRow();
				if ( $row['SKU'] != $sku ) {
					$idx++;
					$product = new StdClass;
					$product->sku = trim($row['SKU']);
					$product->title = trim($row['TITLE']);
					$product->picture = trim($row['PICTURE']);
					$product->img = "//www.psausa.com/images/products/".trim($row['PICTURE']);
					$product->minQty = trim($row['MIN_QTY']);
	
					//$product->altprices = Array();
					$product->prices = Array();
					$products[$idx] = $product;
				}
				if ( $row['Quantity'] > 0 && count($products[$idx]->prices) == 0 ) {
					//$products[$idx]->altprices[$row['Quantity']] = floatval($row['Price']);
					//if ( !in_array(intval($row['Quantity']), $price_qty) ) $price_qty[] = intval($row['Quantity']);
					$products[$idx]->prices[] = [intval($row['Quantity']),floatval($row['Price'])];
				}
				$sku = $row['SKU'];
			}
			/*we don't use all the price info in the results queries
			sort($price_qty);
			
			// go back and fill in empty prices just for fun
			foreach ($products as $product) {
				foreach ($price_qty as $qty) {
					if ( !isset($product->altprices[$qty]) ) $product->altprices[$qty] = 0;
				}
				ksort($product->altprices);
				$product->prices = Array();
				foreach($product->altprices as $qty=>$price) $product->prices[] = [intval($qty),$price];
				unset($product->altprices);
			}
			$results['price_qty'] = $price_qty;
			*/
		}
		$results['products'] = $products;
		
		return $results;
	}
	
	public function placeOrder($criteria) {
		//ship/bill values
		$cart = $this->getCart($criteria);

		$orderNumber = 0;
		$sql = "SELECT Max(OrderNum) AS MaxNum FROM psausa.cart WHERE Status = 2";
		$result = $this->db->Execute($sql);
		if ( !$result )  throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$orderNumber = $result->Fields("MaxNum") + 1;
		
		$params = array(
			"destZip" => $criteria->ship_zip,
			"weight" => "2",
			"length" => "10",
			"width" => "8",
			"height" => "4",
			"shipCode" => "03",
			"dimWeight" => "1"
		);
		$criteria->ship_cost = $this->upsRate((object)$params);
		$criteria->ship_service = $params['shipCode'];
		$sql = "UPDATE psausa.cart 
		SET Ship_Name = ?, Ship_Company = ?, Ship_Address = ?, Ship_Address2 = ?,
		Ship_City = ?, Ship_State = ?, Ship_Zip = ?, Ship_Email = ?, Ship_Phone = ?,
		Bill_Name = ?, Bill_Company = ?, Bill_Address = ?, Bill_Address2 = ?,
		Bill_City = ?, Bill_State = ?, Bill_Zip = ?, Bill_Phone = ?,
		Bill_Account = ?, Bill_Expiration = ?, Bill_CVV = ?,
		Ship_Service = ?, Ship_Cost = ?,
		Status = 2, OrderNum = ?, UserID = ?
		WHERE Status = 1 AND Token  = ?";
		$bindvars = [$criteria->ship_name,$criteria->ship_company,$criteria->ship_address,$criteria->ship_address2,$criteria->ship_city, $criteria->ship_state,$criteria->ship_zip,$criteria->ship_email,$criteria->ship_phone,$criteria->bill_name,$criteria->bill_company,$criteria->bill_address,$criteria->bill_address2,$criteria->bill_city,$criteria->bill_state,$criteria->bill_zip,$criteria->bill_phone,$criteria->bill_account,$criteria->bill_expiration,$criteria->bill_cvv, $criteria->ship_service, $criteria->ship_cost, $orderNumber, $this->userid, $this->token];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result )  throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		
		$msg = '<html><body><div>Order Number '.$orderNumber.'</div><table><tr><td>SKU</td><td>Title</td><td>Quantity</td><td>Price</td><td>Total</td></tr>';
		$total = 0;
		foreach ($cart as $item) {
			$extended = number_format($item['quantity']*$item['price'], 2);
			$msg .= '<tr><td>'.$item['sku'].'</td><td>'.$item['title'].'</td><td>'.$item['quantity'].'</td><td>$'.$item['price'].'</td><td>$'.$extended.'</td></tr>';
			$total += $extended;
		}
		$msg .= '<tr><td colspan="4"></td><td>$'.number_format($total,2).'</td></tr>';
		$msg .= '</body></html>';
		$mailsent = $this->sendMail((object)array('to'=>$criteria->ship_email,'subject'=>'PsaUsa Order Received','message'=>$msg));
		
		return $orderNumber;
	}
	
	public function accountGetOrder($criteria) {
		//orderNum
		$order = array();
		$sql = "SELECT 
		psausa.cart.Ship_Name as ship_name, psausa.cart.Ship_Company as ship_company, psausa.cart.Ship_Address as ship_address, psausa.cart.Ship_Address2 as ship_address2, 
		psausa.cart.Ship_State as ship_state, psausa.cart.Ship_Zip as ship_zip, psausa.cart.Ship_Phone as ship_phone, psausa.cart.Ship_Email as ship_email,
		psausa.cart.Bill_Name as bill_name, psausa.cart.Bill_Company as bill_company, psausa.cart.Bill_Address as bill_address, psausa.cart.Bill_Address2 as bill_address2, 
		psausa.cart.Bill_State as bill_state, psausa.cart.Bill_Zip as bill_zip,
		psausa.cart.Ship_Service as ship_service, psausa.cart.Ship_Cost as ship_cost, psausa.cart.OrderNum as order_num, psausa.cart.Updated as updated
		FROM psausa.cart 
		WHERE psausa.cart.Status > 1 AND psausa.cart.OrderNum = ? AND psausa.cart.UserID = ?";
		$bindvars = [$criteria->orderNum, $this->userid];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$cart = array();
		if ( $result->RecordCount() > 0 ) {
			$cart = $result->FetchRow();
			switch ($cart['ship_service']) {
				case '03':
					$cart['ship_service'] = 'UPS Ground';
					break;
			}
		}
		$order['cart'] = $cart;

		$sql = "SELECT psausa.cart_items.ID AS id, psausa.cart_items.SKU AS sku, psausa.cart_items.Quantity AS quantity, psausa.cart_items.Price AS price, psausa.products.Title AS title 
		FROM psausa.cart_items 
		INNER JOIN psausa.cart ON psausa.cart_items.Cart = psausa.cart.Cart 
		INNER JOIN psausa.products ON psausa.cart_items.SKU = psausa.products.SKU
		WHERE psausa.cart.Status > 1 AND psausa.cart.OrderNum = ? AND psausa.cart.UserID = ?";
		$bindvars = [$criteria->orderNum, $this->userid];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$items = Array();
		while ($result && !$result->EOF) {
			$row = $result->FetchRow();
			$items[] = $row;
		}
		$order['items'] = $items;
		
		return $order;
	}
	
	public function upsRate($criteria) {
		//destZip,shipCode,weight, and length,width,height or dimWeight
		$json = array(
			"RateRequest" => array( 
				"Request" => array( "RequestOption" => "Rate", "TransactionReference" => array( "CustomerContext" => "123abc" ) ),
				"Shipment" => array(
					"Shipper" => [
						"Name" => "PsaUsa",
						"ShipperNumber" => $this->upsShipperNumber,
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
							"PostalCode" => $criteria->destZip,
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
						"Code" => $criteria->shipCode
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
							"Weight" => $criteria->weight
						]
					],
					"ShipmentRatingOptions" => [
						"NegotiatedRatesIndicator" => "-"
					]
				)
			)
		);
		
		if ( isset($criteria->length) ) {
			$json['RateRequest']['Shipment']['Package']['Dimensions'] =
				[ 
					"UnitOfMeasurement" => [
						 "Code" => "IN" 
					], 
					"Length" => $criteria->length, 
					"Width" => $criteria->width,
					"Height" => $criteria->height
				];
		}
		if ( isset($criteria->dimWeight) ) {
			$json['RateRequest']['Shipment']['Package']['DimWeight'] =
				[
					"UnitOfMeasurement" => [ 
						"Code" => "LBS" 
					],
					"Weight" => $criteria->dimWeight
				];
		}

		if ( false ) {
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
		}
		else {
			//Configuration
			$access = "CD57A60DEED4CC2C";
			$userid = "johnh@oakhillsoftware.com";
			$passwd = "sugcij-Kakboz-9momru";
			$shipperNumber = "812290";

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
		}

		$rate = 0;
		$rate = (float)$vals->RateResponse->RatedShipment->TotalCharges->MonetaryValue;
		return $rate;
	}
	
	public function accountAddress($criteria) {
		//name,company,address,address2,city,state,zip,phone,email
		if ( isset($criteria->name) ) {
			$sql = "SELECT ID FROM psausa.user_address WHERE UserID = ?";
			$bindvars = [$this->userid];
			$result = $this->db->Execute($sql,$bindvars);
			if ( $result->RecordCount() == 0 ) {
				$this->db->Execute("INSERT INTO psausa.user_address (UserID) VALUES (?)", [$this->userid]);
				$rowid = $this->db->Insert_ID();
			}
			else $rowid = $result->Fields("ID");
			$sql = "UPDATE psausa.user_address SET
			Name = ?, Company = ?, Address = ?, Address2 = ?, City = ?, State = ?, Zip = ?, Phone = ?, Email = ?
			WHERE UserID = ?";
			$bindvars = [$criteria->name, $criteria->company, $criteria->address, $criteria->address2, $criteria->city, $criteria->state, $criteria->zip, $criteria->phone, $criteria->email, $rowid];
			$result = $this->db->Execute($sql, $bindvars);
			if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		}
		$sql = "SELECT ID as id, Name as name, Company as company, Address as address, Address2 as address2, City as city, State as state, Zip as zip, Phone as phone, Email as email FROM psausa.user_address WHERE UserID = ?";
		$bindvars = [$this->userid];
		$result = $this->db->Execute($sql,$bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		$address = array();
		if ( $result->RecordCount() > 0 ) $address = $result->FetchRow();
		return $address;
	}
	
	public function accountOrderHistory($criteria) {
		//
		$orders = array();
		$sql = "SELECT ID, OrderNum, Updated FROM psausa.cart WHERE Status > 1 AND UserID = ? ORDER BY Updated DESC";
		$bindvars = [$this->userid];
		$result = $this->db->Execute($sql, $bindvars);
		if ( !$result ) throw new Exception($this->db->ErrorNo().":".$this->db->ErrorMsg());
		while ( !$result->EOF ) {
			$row = $result->FetchRow();
			$orders[] = $row;
		}
		return $orders;
	}
}
	$debug = true;
	
	function cleanVars($criteria) {
		$return = array();
		$scrub = Array("model","platform","version","action","token","callback");
		foreach ($criteria as $name => $val) {
			if ( !in_array($name,$scrub) ) {
				if ($name == 'json') $return = json_decode($val);
				else $return[$name] = $val;
			}
		}
		return (object)$return;
	}
	
	$model = "";
	if ( isset($_REQUEST['model']) ) $model = $_REQUEST['model'];
	$action = 'action';
	if ( isset($_REQUEST['action']) ) $action = $_REQUEST['action'];
	//require_once(dirname(__FILE__)."/".strtolower($model)."/PsaUsa".ucfirst($model).".class.php");
	
	$token = 0;
	if ( isset($_REQUEST['token']) ) $token = $_REQUEST['token'];
	$className = "PsaUsa".ucfirst($model);
	$gObj = new $className($token);
	
	$criteria = cleanVars($_REQUEST);
		
	$result = Array();
	$result['error'] = 0;
	if ( $debug ) $result['debug'] = $criteria;
	try {
		$result['data'] = $gObj->$action($criteria);
	}
	catch(Exception $e){
		$result['error'] = 1;
		$result['data'] = $e->getMessage();
	}

	if ( isset($_REQUEST['callback']) ) {
		echo $_REQUEST['callback'].'('.json_encode($result).')';
	}
	else {
		echo json_encode($result);
	}
?>

<?
/* load products
SELECT SKU, Count(SKU) as total FROM psausa.products GROUP BY SKU HAVING Count(SKU) > 1

if ( false ) {
$gDB->debug = true;
set_time_limit(600);
$result = $gDB->Execute("SELECT * FROM psausa.products LIMIT 5000");
print "start";
while ( $result && !$result->EOF ) {
	$row = $result->FetchRow();
	
	$parent_id = 0;
	if ( $row['MAIN'] != "" ) {
		$sql = "SELECT ID FROM psausa.categories WHERE Name = ? AND Parent_ID = ?";
		$bindvars = [$row['MAIN'],0];
		$ok = $gDB->Execute($sql, $bindvars);
		if ( $ok->RecordCount() == 0 ) {
			$sql = "INSERT INTO psausa.categories (Name, Parent_ID) VALUES (?,?)";
			$gDB->Execute($sql, $bindvars);
			$sql = "SELECT ID FROM psausa.categories WHERE Name = ? AND Parent_ID = ?";
			$ok = $gDB->Execute($sql, $bindvars);
		}
		
		$parent_id = $ok->Fields("ID");
		if ( $row['SUB_1'] == "" ) {
			$sql = "INSERT INTO psausa.product_category (Category_ID, SKU) VALUES (?, ?) ";
			$bindvars = [$ok->Fields("ID"),$row['SKU']];
			$ok = $gDB->Execute($sql,$bindvars);
		}
		
		if ( $row['SUB_1'] != "" ) {
			$sql = "SELECT ID FROM psausa.categories WHERE Name = ? AND Parent_ID = ?";
			$bindvars = [$row['SUB_1'],$parent_id];
			$ok = $gDB->Execute($sql, $bindvars);
			if ( $ok->RecordCount() == 0 ) {
				$sql = "INSERT INTO psausa.categories (Name, Parent_ID) VALUES (?,?)";
				$gDB->Execute($sql, $bindvars);
				$sql = "SELECT ID FROM psausa.categories WHERE Name = ? AND Parent_ID = ?";
				$ok = $gDB->Execute($sql, $bindvars);
			}
		
			$parent_id = $ok->Fields("ID");
			if ( $row['SUB_2'] == "" ) {
				$sql = "INSERT INTO psausa.product_category (Category_ID, SKU) VALUES (?, ?) ";
				$bindvars = [$ok->Fields("ID"),$row['SKU']];
				$ok = $gDB->Execute($sql,$bindvars);
			}
			if ( $row['SUB_2'] != "" ) {
				$sql = "SELECT ID FROM psausa.categories WHERE Name = ? AND Parent_ID = ?";
				$bindvars = [$row['SUB_2'],$parent_id];
				$ok = $gDB->Execute($sql, $bindvars);
				if ( $ok->RecordCount() == 0 ) {
					$sql = "INSERT INTO psausa.categories (Name, Parent_ID) VALUES (?,?)";
					$gDB->Execute($sql, $bindvars);
					$sql = "SELECT ID FROM psausa.categories WHERE Name = ? AND Parent_ID = ?";
					$ok = $gDB->Execute($sql, $bindvars);
				}
		
				$parent_id = $ok->Fields("ID");
				$sql = "INSERT INTO psausa.product_category (Category_ID, SKU) VALUES (?, ?) ";
				$bindvars = [$ok->Fields("ID"),$row['SKU']];
				$ok = $gDB->Execute($sql,$bindvars);
			}
		}
	}
	
	$sql = "DELETE FROM psausa.product_price WHERE SKU = ?";
	$bindvars = [$row['SKU']];
	$ok = $gDB->Execute($sql,$bindvars);
	
	$qtys = [1,25,100,250,500,1000,300,2,3,4,5,6,8,9,10,12,15,18,20,24,36,40,48,50,72,120,144,180];
	foreach ($qtys as $qty) {
		print $qty.":".$row[$qty]."<br/>";
		if ( is_numeric($row[$qty]) ) {
			$sql = "INSERT INTO psausa.product_price (SKU, Quantity, Price) VALUES (?,?,?);";
			$bindvars = [$row['SKU'],$qty,$row[$qty]];
			$ok = $gDB->Execute($sql,$bindvars);
			if ( !$ok ) print "Error adding ".$row['SKU']." ".$qty." $".$row[$qty]."<br/>";
			else print "Added ".$row['SKU']." ".$qty." $".$row[$qty]."<br/>";
		}
	}
	
}
print "done";
exit();
}
*/
?>