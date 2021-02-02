<?php

/**
 * Class authMiddleware
 *
 * @author Liam McClelland
 * @property \Slim\Container $container Container provided by Slim
 * @property user $user Reference to an instance of the user class.
 */
class authMiddleware
{

    private $container;
    private $user;

    /**
     * authMiddleware constructor.
     * @param \Slim\Container $container Container provided by Slim
     */
    public function __construct($container) {

        $this->container = $container;
        $this->user = $this->container["user"];

    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        // This will redirect if user isn't logged in
        $this->user->loginRequired();

        $response = $next($request, $response);

        return $response;
    }
}