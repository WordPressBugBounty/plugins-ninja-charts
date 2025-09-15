<?php

namespace NinjaCharts\Framework\Database\Orm;

use BadMethodCallException;
use InvalidArgumentException;
use NinjaCharts\Framework\Http\Request\WPUserProxy;

trait UserProxyTrait
{
	/**
	 * The WPUserProxy instance.
	 * 
	 * @var WPUserProxy
	 */
	protected $__wp_user_proxy_obj;

	/**
	 * Resolve the WPUserProxy instance.
	 * 
	 * @return WPUserProxy
	 */
	protected function resolveWPUser()
    {
        return $this->__wp_user_proxy_obj ??= new WPUserProxy($this->getKey());
    }

	/**
	 * Dual-purpose is() method:
	 *  - is(string $role) → checks user role
	 *  - is(User $model) → checks model identity
	 *
	 * @param string|self $value
	 * @return bool
	 */
	public function is($value)
	{
	    if (is_string($value)) {
	        // Only call resolveWPUser() if a WP_User exists
	        if ($this->getKey() && $this->resolveWPUser() instanceof WPUserProxy) {
	            return $this->resolveWPUser()->is($value);
	        }

	        return false;
	    }

	    if ($value instanceof static) {
	        return parent::is($value);
	    }

	    return false;
	}


    /**
     * Call a method on the WPUserProxy instance dynamically.
     * 
     * @param  string $method
     * @param  array $args
     * @return mixed
     * @throws BadMethodCallException|InvalidArgumentException
     */
    public function __call($method, $args)
	{
	    try {
	        return parent::__call($method, $args);
	    } catch (BadMethodCallException $e) {
	        try {
	            return $this->resolveWPUser()->$method(...$args);
	        } catch (BadMethodCallException $proxyError) {
	            // Proxy didn’t have the method - let it bubble up
	            throw $proxyError;
	        } catch (InvalidArgumentException $invalidUser) {
	            // Wrongly constructed proxy - surface as undefined method
	            throw new BadMethodCallException(
	                "Call to undefined method " . static::class . "::{$method}()",
	                0,
	                $invalidUser
	            );
	        }
	    }
	}
}
