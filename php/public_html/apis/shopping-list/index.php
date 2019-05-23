<?php

require_once(dirname(__DIR__, 3) . "/vendor/autoload.php");
require_once(dirname(__DIR__, 3) . "/Classes/autoload.php");
require_once(dirname(__DIR__, 3) . "/lib/uuid.php");

use Deepdivedylan\DockerComposeLesson\ShoppingList;

//verify the session, start if not active
if(session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

//prepare an empty reply
$reply = new stdClass();
$reply->status = 200;
$reply->data = null;

try {
//grab the environment variables from the host.
	$env = getenv();
	// grab the encrypted mySQL properties file and crete the DSN
	$dsn = "mysql:host=" . $env["MYSQL_HOST"] . ";dbname=" . $env["MYSQL_DATABASE"];
	$options = [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"];
	$pdo = new PDO($dsn, $env["MYSQL_USER"], $env["MYSQL_PASSWORD"], $options);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	//determine which HTTP method was used
	$method = $_SERVER["HTTP_X_HTTP_METHOD"] ?? $_SERVER["REQUEST_METHOD"];

	// handle a GET request
	if($method === "GET") {
		$reply->data = ShoppingList::getAllShoppingLists($pdo)->toArray();
	} else if($method === "POST") {
		// unpack the JSON data
		$requestContent = file_get_contents("php://input");
		$requestObject = json_decode($requestContent);

		// make sure shopping list item is available (required field)
		if(empty($requestObject->shoppingListItem) === true) {
			throw(new InvalidArgumentException("no content for shopping list item", 405));
		}

		// make sure shopping list quantity is available (required field)
		if(empty($requestObject->shoppingListQuantity) === true) {
			throw(new InvalidArgumentException("no content for shopping list quantity", 405));
		}

		// post new shopping list to mySQL
		$shoppingList = new ShoppingList(generateUuidV4(), $requestObject->shoppingListItem, $requestObject->shoppingListQuantity);
		$shoppingList->insert($pdo);
		$reply->message = "Shopping List created OK";
	} else {
		throw (new InvalidArgumentException("Invalid HTTP method request"));
	}
} catch(\Exception | \TypeError $exception) {
	$reply->status = $exception->getCode();
	$reply->message = $exception->getMessage();
}

// encode and return reply to front end caller
header("Content-type: application/json");
echo json_encode($reply);
