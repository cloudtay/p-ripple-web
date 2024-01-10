<?php

namespace Cclilshy\PRippleWeb\Middleware;

use Cclilshy\PRippleHttpService\Request;
use Cclilshy\PRippleWeb\Std\MiddlewareStd;
use Cclilshy\PRippleWeb\WebApplication;
use Generator;
use Illuminate\Support\Facades\Session;
use Throwable;

class SessionMiddleware implements MiddlewareStd
{
    /**
     * @param Request $request
     * @return Generator
     * @throws Throwable
     */
    public function handle(Request $request): Generator
    {
        /**
         * @var WebApplication $webApplication
         */
        $webApplication = $request->resolve(WebApplication::class);
        if (!$sessionID = $request->cookieArray['P_SESSION_ID'] ?? null) {
            $sessionID = md5(microtime(true) . $request->hash);
            $request->response->setCookie(
                'P_SESSION_ID',
                $sessionID,
                $webApplication->config['SESSION_EXPIRE'] ?? 7200
            );
        }
        $session = $webApplication->sessionManager->buildSession($sessionID);
        $request->inject(Session::class, $session);
        yield true;
    }
}
