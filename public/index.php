<?php

use Slim\Factory\AppFactory;
use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Doctrine\DBAL\DriverManager;

require __DIR__ . '/../vendor/autoload.php';

$phinxConfig = require __DIR__ . '/../phinx.php';
$env = $phinxConfig['environments']['development'];

$connectionParams = [
	'dbname' => $env['name'],
	'user' => $env['user'],
	'password' => $env['pass'],
	'host' => $env['host'],
	'driver' => 'pdo_pgsql',
	'port' => $env['port'],
	'charset' => $env['charset'],
];

try {
	$conn = DriverManager::getConnection($connectionParams);
} catch (\Doctrine\DBAL\Exception $e) {
	die("Database connection failed: " . $e->getMessage());
}

$container = new Container();
$container->set('db', $conn);

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->get('/', function ($request, $response, $args) {
	$response->getBody()->write("Server is running!");
	return $response;
});

$app->post('/login', function ($request, $response, $args)  use ($container) {
	$body = $request->getBody()->getContents();
	$data = json_decode($body, true);

	if (json_last_error() === JSON_ERROR_NONE) {
		$username = $data['username'] ?? 'not provided';
		$password = $data['password'] ?? 'not provided';
		$response->getBody()->write("Username: $username, Password: $password");
	} else {
		$response->getBody()->write("Invalid JSON");
	}

	$conn = $container->get('db');
	$user = $conn->fetchAssociative('SELECT * FROM users WHERE username = ?', [$username]);

	if (!$user || !password_verify($password, $user['password'])) {
		$response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
		return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
	}

	$payload = [
		'id' => $user['id'],
		'username' => $user['username'],
	];
	$token = JWT::encode($payload, 'your_secret_key', 'HS256');

	$response->getBody()->write(json_encode(['token' => $token]));
	return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/users', function (Request $request, Response $response) use ($container) {
	$authHeader = $request->getHeaderLine('Authorization');
	$token = str_replace('Bearer ', '', $authHeader);

	if (!$token) {
		$response->getBody()->write(json_encode(['error' => 'Missing token']));
		return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
	}

	try {
		$decoded = JWT::decode($token, new Key('your_secret_key', 'HS256'));
	} catch (Exception $e) {
		$response->getBody()->write(json_encode(['error' => 'Invalid token: ' . $e->getMessage()]));
		return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
	}


	$params = $request->getQueryParams();

	$connection = $container->get('db');

	$queryBuilder = $connection->createQueryBuilder();
	$queryBuilder->select('*')->from('users');

	if (isset($params['firstName'])) {
		$queryBuilder->andWhere('first_name = :firstName')->setParameter('firstName', $params['firstName']);
	}
	if (isset($params['lastName'])) {
		$queryBuilder->andWhere('last_name = :lastName')->setParameter('lastName', $params['lastName']);
	}
	if (isset($params['birthday'])) {
		list($startDate, $endDate) = explode('|', $params['birthday']);
		$queryBuilder->andWhere('birthday BETWEEN :startDate AND :endDate')
			->setParameter('startDate', $startDate)
			->setParameter('endDate', $endDate);
	}

	$users = $queryBuilder->executeQuery()->fetchAllAssociative();

	if (empty($users)) {
		$response->getBody()->write(json_encode(['message' => 'No users found']));
		return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
	}

	$response->getBody()->write(json_encode($users));
	return $response->withHeader('Content-Type', 'application/json');
});

$app->run();