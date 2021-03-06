<?php

use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application;

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider, [
	'twig.path' => __DIR__.'/../views'
]);

$app->register(new Silex\Provider\DoctrineServiceProvider, [
	'db.options' => [
		'driver' => 'pdo_mysql',
		'host' => '127.0.0.1',
		'dbname' => 'awesomeimage',
		'user' => 'root',
		'password' => '',
		'charset' => 'utf8',
	]
]);

$app->register(new AI\Providers\UploadcareProvider);

$app->register(new Silex\Provider\UrlGeneratorServiceProvider);

$app->get('/', function() use($app){
	$images = $app['db']->prepare("SELECT * FROM images");
	$images->execute();

	$images = $images->fetchAll(\PDO::FETCH_CLASS, \AI\Models\Image::class);
	$app['images'] = $images;

	return $app['twig']->render('home.twig');
})->bind('home');

$app->post('/upload', function(Request $request) use($app){
	if ($request->get('file_id') === '') {
		return $app->redirect($app['url_generator']->generate('home'));
	}

	$file = $app['uploadcare']->getfile($request->get('file_id'));

	$store = $app['db']->prepare("
		INSERT INTO images(hash, url, created_at)
		VALUES(:hash, :url, NOW())
	");

	$store->execute([
		'hash' => bin2hex(random_bytes(20)),
		'url' => $file->getUrl(),
	]);

	return $app->redirect($app['url_generator']->generate('home'));
})->bind('image.upload');

$app->get('/image/{hash}', function(Request $request) use($app){
	$image = $app['db']->prepare("SELECT url FROM images where hash = :hash");
	
	$image->execute([
		'hash' => $request->get('hash'),
	]);

	$image->setFetchMode(\PDO::FETCH_CLASS, \AI\Models\Image::class);

	$image = $image->fetch();

	return $app['twig']->render('show.twig', [
		'image' => $image,
	]);
})->bind('image.show');

$app->run();

?>