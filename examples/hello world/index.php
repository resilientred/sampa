<?php

$loader = require_once(__DIR__ . '/vendor/autoload.php');
$loader->add('App', __DIR__);

use sampa\Core\Kernel;
use sampa\Exception;

try {
	$kernel = new Kernel(php_uname('n'));
	$kernel->boot(__DIR__ . '/config', __DIR__ . '/log');
	$kernel->dispatch();
} catch (Exception\Boot $e) {
	//Framework boot error
	printf("BOOT Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\Application $e) {
	//custom application error
	printf("Application Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\Config $e) {
	//application error (problems with config files)
	printf("Config Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\DatabaseCache $e) {
	//application error (problems on query execution)
	printf("DatabaseCache Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\DatabaseConnection $e) {
	printf("DatabaseConnection Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\DatabaseQuery $e) {
	//application error (problems on query execution)
	printf("DatabaseQuery Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\Log $e) {
	//application error (problems on folder permission)
	printf("Log Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\Cache $e) {
	//application error (problems on cache driver selection)
	printf("Cache Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception\Filter $e) {
	//application error (problems on filter selection)
	printf("Filter Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
} catch (Exception $e) {
	printf("General Exception: %s in %s:%d\n", $e->getMessage(), $e->getFile(), $e->getLine());
}
