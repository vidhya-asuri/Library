<?php

require './../vendor/autoload.php';

/*  	$dbhost="10.10.102.41";
	$dbuser="intranetrw";
	$dbpass="aSw2017";
	$dbname="PO";
	$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); 
*/
/* 	$sql = "SELECT * FROM po ";
	$dbh = getConnection();
	var_dump($dbh);
	$stmt = $dbh->query($sql);
	$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
	$db = null;
	echo '{"purchase orders": ' . json_encode($wines) . '}';
 */	

	// Uses Slim framework
	// http://coenraets.org/blog/2011/12/restful-services-with-jquery-php-and-the-slim-framework/
	$app = new \Slim\App;
	
	$app->get('/pos', 'getAllPOs');
	$app->get('/pos/:id',  'getPO');
	// $app->get('/wines/search/:query', 'findByName');
	// $app->post('/wines', 'addWine');
	// $app->put('/wines/:id', 'updateWine');
	// $app->delete('/wines/:id',   'deleteWine');
	
	$app->run();
	
	getAllPOs();
	
	// Get all purchase orders
	function getAllPOs() {
		$sql = "select * FROM po ";
		try {
			$db = getConnection();
			echo "connection successful";
			$stmt = $db->query($sql);
			$pos = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;
			echo '{"purchase orders": ' . json_encode($pos) . '}';
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	}
	
	/*	
	$sql = "select * FROM po";
	try {
		$db = getConnection();
		echo "connection successful";
		$stmt = $db->query($sql);
		$wines = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		echo '{"purchase orders": ' . json_encode($wines) . '}';
	} catch(PDOException $e) {
		echo '{"error":{"text":'. $e->getMessage() .'}}';
	}
	*/
	
	
	function getConnection() {
		$dbhost="10.10.102.41";
		$dbuser="intranetrw";
		$dbpass="aSw2017";
		$dbname="PO";
		$dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
		$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		return $dbh;
		
	}
	
	
			
?>