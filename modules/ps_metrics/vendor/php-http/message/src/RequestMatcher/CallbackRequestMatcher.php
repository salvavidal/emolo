<?php

namespace ps_metrics_module_v4_0_6\Http\Message\RequestMatcher;

use ps_metrics_module_v4_0_6\Http\Message\RequestMatcher;
use Psr\Http\Message\RequestInterface;
/**
 * Match a request with a callback.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class CallbackRequestMatcher implements RequestMatcher
{
    /**
     * @var callable
     */
    private $callback;
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }
    public function matches(RequestInterface $request)
    {
        return (bool) \call_user_func($this->callback, $request);
    }
}
