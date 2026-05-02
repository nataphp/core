<?php
/**
 * NataPHP Framework
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Async;

use Fiber;
use RuntimeException;
use Throwable;

/**
 * A Promise implementation that works with PHP 8.1+ Fibers
 * for asynchronous processing in the Nata framework.
 *
 * This class is based on the Promise implementation from the Amp project.
 *
 * EXPERIMENTAL: This class is experimental and may change in future versions.
 *
 * Basic Usage:
 * ```php
 * // Create a new promise
 * $promise = new Promise(function($resolve, $reject) {
 *     // Async operation
 *     if ($success) {
 *         $resolve($result);
 *     } else {
 *         $reject(new Exception('Operation failed'));
 *     }
 * });
 *
 * // Using async/await
 * $result = Promise::async(function() {
 *     $response = Promise::await($someAsyncOperation);
 *     return $response;
 * });
 * ```
 */
class Promise {

/**
 * The current state of the promise.
 *
 * @var string
 */
    const PENDING = 'pending';
    const FULFILLED = 'fulfilled';
    const REJECTED = 'rejected';

/**
 * The current state of the promise (PENDING, FULFILLED, or REJECTED).
 *
 * @var string
 */
    protected $_state = self::PENDING;

/**
 * The fulfilled value of the promise.
 *
 * @var mixed
 */
    protected $_result = null;

/**
 * The reason for rejection if the promise is rejected.
 *
 * @var mixed
 */
    protected $_reason = null;

/**
 * Array of callbacks to be called when the promise is fulfilled.
 *
 * @var callable[]
 */
    protected $_onFulfilled = [];

/**
 * Array of callbacks to be called when the promise is rejected.
 *
 * @var callable[]
 */
    protected $_onRejected = [];

/**
 * The Fiber instance associated with this promise.
 *
 * @var ?Fiber
 */
    protected $_fiber = null;

/**
 * Create a new Promise.
 *
 * Example:
 * ```php
 * $promise = new Promise(function ($resolve, $reject) {
 *     // Simulate async operation
 *     if (rand(0, 1)) {
 *         $resolve('Success!');
 *     } else {
 *         $reject(new Exception('Failed!'));
 *     }
 * });
 * ```
 *
 * @param callable $executor Function that receives resolve and reject functions.
 */
    public function __construct(callable $executor = null) {
        if (!$executor) {
            return;
        }

        try {
            $executor(
                function ($value) { $this->resolve($value); },
                function ($reason) { $this->reject($reason); }
            );
        } catch (Throwable $e) {
            $this->reject($e);
        }
    }

/**
 * Resolve the promise with a value.
 *
 * @param mixed $value
 * @return void
 */
    public function resolve($value) {
        if ($this->_state !== self::PENDING) {
            return;
        }

        // If value is a promise, adopt its state
        if ($value instanceof self) {
            $value->then(
                function ($result) { $this->resolve($result); },
                function ($reason) { $this->reject($reason); }
            );
            return;
        }

        $this->_state = self::FULFILLED;
        $this->_result = $value;

        foreach ($this->_onFulfilled as $callback) {
            $this->_handleCallback($callback, $value);
        }

        $this->_onFulfilled = [];
        $this->_onRejected = [];
    }

/**
 * Reject the promise with a reason.
 *
 * @param mixed $reason
 * @return void
 */
    public function reject($reason) {
        if ($this->_state !== self::PENDING) {
            return;
        }

        $this->_state = self::REJECTED;
        $this->_reason = $reason;

        foreach ($this->_onRejected as $callback) {
            $this->_handleCallback($callback, $reason);
        }

        $this->_onFulfilled = [];
        $this->_onRejected = [];
    }

/**
 * Add callbacks to be called when the Promise is fulfilled or rejected.
 *
 * Example:
 * ```php
 * $promise->then(
 *     function($value) {
 *         echo "Success: $value";
 *     },
 *     function($error) {
 *         echo "Error: " . $error->getMessage();
 *     }
 * );
 * ```
 *
 * @param callable|null $onFulfilled
 * @param callable|null $onRejected
 * @return Promise
 */
    public function then(callable $onFulfilled = null, callable $onRejected = null) {
        $promise = new Promise();

        $wrappedOnFulfilled = function ($value) use ($onFulfilled, $promise) {
            if ($onFulfilled === null) {
                $promise->resolve($value);
                return;
            }

            try {
                $promise->resolve($onFulfilled($value));
            } catch (Throwable $e) {
                $promise->reject($e);
            }
        };

        $wrappedOnRejected = function ($reason) use ($onRejected, $promise) {
            if ($onRejected === null) {
                $promise->reject($reason);
                return;
            }

            try {
                $promise->resolve($onRejected($reason));
            } catch (Throwable $e) {
                $promise->reject($e);
            }
        };

        if ($this->_state === self::PENDING) {
            $this->_onFulfilled[] = $wrappedOnFulfilled;
            $this->_onRejected[] = $wrappedOnRejected;
        } elseif ($this->_state === self::FULFILLED) {
            $this->_handleCallback($wrappedOnFulfilled, $this->_result);
        } elseif ($this->_state === self::REJECTED) {
            $this->_handleCallback($wrappedOnRejected, $this->_reason);
        }

        return $promise;
    }

/**
 * Add a callback to be called when the Promise is rejected.
 *
 * @param callable $onRejected
 * @return Promise
 */
    public function catch(callable $onRejected) {
        return $this->then(null, $onRejected);
    }

/**
 * Add a callback to be called when the Promise is settled (fulfilled or rejected).
 *
 * @param callable $onFinally
 * @return Promise
 */
    public function finally(callable $onFinally) {
        return $this->then(
            function ($value) use ($onFinally) {
                $onFinally();
                return $value;
            },
            function ($reason) use ($onFinally) {
                $onFinally();
                throw $reason;
            }
        );
    }

/**
 * Run a function in a fiber, and return a Promise.
 *
 * Example:
 * ```php
 * Promise::async(function () {
 *     $result1 = Promise::await($asyncOperation1);
 *     $result2 = Promise::await($asyncOperation2);
 *     return $result1 . $result2;
 * })->then(function ($result) {
 *     echo "Combined result: $result";
 * });
 * ```
 *
 * @param callable $fn Function to run in a fiber
 * @return Promise
 */
    public static function async(callable $fn): self {
        $promise = new self();

        $fiber = new Fiber(function () use ($fn, $promise) {
            try {
                $result = $fn();
                $promise->resolve($result);
            } catch (Throwable $e) {
                $promise->reject($e);
            }
        });

        $promise->_fiber = $fiber;
        $fiber->start();

        return $promise;
    }

/**
 * Suspends the current Fiber until a value is available.
 *
 * @param Promise $promise
 * @return mixed The resolved value
 * @throws Throwable if the promise is rejected
 */
    public static function await(self $promise) {
        if (!Fiber::getCurrent()) {
            throw new RuntimeException('Cannot await outside of a fiber');
        }

        if ($promise->_state === self::FULFILLED) {
            return $promise->_result;
        }

        if ($promise->_state === self::REJECTED) {
            throw $promise->_result;
        }

        $fiber = Fiber::getCurrent();

        $promise->then(
            function ($value) use ($fiber) {
                if ($fiber->isTerminated()) {
                    return;
                }
                $fiber->resume($value);
            },
            function ($reason) use ($fiber) {
                if ($fiber->isTerminated()) {
                    return;
                }
                $fiber->throw($reason);
            }
        );

        return Fiber::suspend();
    }

/**
 * Returns a Promise that resolves when all promises have resolved.
 *
 * Example:
 * ```php
 * $promises = [
 *     $asyncOperation1,
 *     $asyncOperation2,
 *     $asyncOperation3
 * ];
 * Promise::all($promises)->then(function($results) {
 *     // $results is an array containing all resolved values
 *     foreach ($results as $result) {
 *         echo $result . "\n";
 *     }
 * });
 * ```
 *
 * @param Promise[] $promises
 * @return Promise
 */
    public static function all(array $promises): self {
        $results = [];
        $remaining = count($promises);
        $promise = new self();

        if ($remaining === 0) {
            $promise->resolve([]);
            return $promise;
        }

        foreach ($promises as $i => $p) {
            if (!($p instanceof self)) {
                $results[$i] = $p;
                $remaining--;
                continue;
            }

            $p->then(
                function ($value) use (&$results, &$remaining, $promise, $i) {
                    $results[$i] = $value;
                    $remaining--;

                    if ($remaining === 0) {
                        $promise->resolve($results);
                    }
                },
                function ($reason) use ($promise) {
                    $promise->reject($reason);
                }
            );
        }

        if ($remaining === 0) {
            $promise->resolve($results);
        }

        return $promise;
    }

/**
 * Handle a callback by scheduling it for the next tick.
 *
 * @param callable $callback
 * @param mixed $value
 * @return void
 */
    protected function _handleCallback(callable $callback, $value) {
        // Process callback asynchronously
        $this->_scheduleCallback(function() use ($callback, $value) {
            $callback($value);
        });
    }

/**
 * Schedule a callback to be executed asynchronously.
 *
 * @param callable $callback
 * @return void
 */
    protected function _scheduleCallback(callable $callback) {
        // Use defer if available (ext-ev, ext-event, etc.)
        if (function_exists('defer')) {
            defer($callback);
            return;
        }

        // Otherwise use a simple setTimeout-like approach
        call_user_func($callback);
    }
}