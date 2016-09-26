<?php

use Dflydev\FigCookies\SetCookie;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use PSR7Session\Http\SessionMiddleware;
use PSR7Session\Time\SystemCurrentTime;
use Slim\Container;
use SlimSkeleton\Auth\Auth;
use SlimSkeleton\Controller\AuthController;
use SlimSkeleton\Controller\HomeController;
use SlimSkeleton\Controller\UserController;
use SlimSkeleton\Auth\AuthMiddleware;
use SlimSkeleton\Provider\ConsoleProvider;
use SlimSkeleton\Provider\DoctrineServiceProvider;
use SlimSkeleton\Provider\TranslationProvider;
use SlimSkeleton\Provider\TwigProvider;
use SlimSkeleton\Repository\UserRepository;
use SlimSkeleton\Session\Session;
use SlimSkeleton\Translation\LocaleTranslationProvider;
use SlimSkeleton\Translation\TranslatorTwigExtension;
use SlimSkeleton\Validation\Validator;

/* @var Container $container */

$container->register(new ConsoleProvider());
$container->register(new DoctrineServiceProvider());
$container->register(new TranslationProvider());
$container->register(new TwigProvider());

// extend providers
$container->extend('translator.providers', function (array $providers) use ($container) {
    $translationDir = $container['appDir'].'/Resources/translations';
    $providers[] = new LocaleTranslationProvider('de', require $translationDir.'/de.php');
    $providers[] = new LocaleTranslationProvider('en', require $translationDir.'/en.php');

    return $providers;
});

$container->extend('twig.namespaces', function (array $namespaces) use ($container) {
    $namespaces['SlimSkeleton'] = $container['appDir'].'/Resources/views';

    return $namespaces;
});

$container->extend('twig.extensions', function (array $extensions) use ($container) {
    $extensions[] = new TranslatorTwigExtension($container['translator']);
    if ($container['debug']) {
        $extensions[] = new \Twig_Extension_Debug();
    }

    return $extensions;
});

// controllers
$container[HomeController::class] = function () use ($container) {
    return new HomeController($container[Auth::class], $container[Session::class], $container['twig']);
};

$container[AuthController::class] = function () use ($container) {
    return new AuthController($container[Auth::class], $container['router'], $container[Session::class]);
};

$container[UserController::class] = function () use ($container) {
    return new UserController(
        $container[Auth::class],
        $container['router'],
        $container[Session::class],
        $container['twig'],
        $container[UserRepository::class],
        $container[Validator::class]
    );
};

// middlewares
$container[AuthMiddleware::class] = function () use ($container) {
    return new AuthMiddleware($container[Auth::class], $container[Session::class], $container['twig']);
};

$container[SessionMiddleware::class] = function () use ($container) {
    return new SessionMiddleware(
        new Sha256(),
        $container['session.symmetricKey'],
        $container['session.symmetricKey'],
        SetCookie::create(SessionMiddleware::DEFAULT_COOKIE)
            ->withHttpOnly(true)
            ->withPath('/'),
        new Parser(),
        $container['session.expirationTime'],
        new SystemCurrentTime()
    );
};

// repositories
$container[UserRepository::class] = function () use ($container) {
    return new UserRepository($container['db']);
};

// services
$container[Auth::class] = function () use ($container) {
    return new Auth($container[Session::class], $container[UserRepository::class]);
};

$container[Session::class] = function () use ($container) {
    return new Session();
};

$container[Validator::class] = function () use ($container) {
    return new Validator();
};
