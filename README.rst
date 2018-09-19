Debug utilities
###############

Collection of useful debugging utilities.

.. image:: https://travis-ci.com/kuria/debug.svg?branch=master
   :target: https://travis-ci.com/kuria/debug

.. contents::
   :depth: 2


Requirements
************

PHP 7.1+


Dumper
******

Utilities for inspecting arbitrary values.


Dumping any value
=================

Dumping arbitrary PHP values with nesting and string length limits.

.. code:: php

   <?php

   use Kuria\Debug\Dumper;

   $values = [
       'foo bar',
       123,
       -123,
       1.53,
       -1.53,
       true,
       false,
       fopen('php://stdin', 'r'),
       null,
       array(1, 2, 3),
       new \stdClass(),
   ];

   echo Dumper::dump($values);

Output:

::

  array[11] {
      [0] => "foo bar"
      [1] => 123
      [2] => -123
      [3] => 1.530000
      [4] => -1.530000
      [5] => true
      [6] => false
      [7] => resource(stream#10)
      [8] => NULL
      [9] => array[3]
      [10] => object(stdClass)
  }

- see other arguments of ``dump()`` for nesting and string limits
- if an object implements the ``__debugInfo()`` method, its output
  will be used instead of the properties
- if an object implements the ``__toString()`` method, its output
  will be used instead of its properties if:

  A. it has no properties
  B. the properties cannot be displayed due to the nesting limit

- if an object implements the ``\DateTimeInterface``, its value
  will be formatted as a string


Dumping strings
===============

Safely dumping arbitrary strings. All ASCII < 32 will be escaped in C style.

.. code:: php

   <?php

   use Kuria\Debug\Dumper;

   echo Dumper::dumpString("Foo\nBar");

Output:

::

  Foo\nBar


Dumping string as HEX
=====================

Useful for dumping binary data or examining actual bytes of a text.

.. code:: php

   <?php

   use Kuria\Debug\Dumper;

   echo Dumper::dumpStringAsHex("Lorem\nIpsum\nDolor\nSit\nAmet\n");

Output:

::

       0 : 4c 6f 72 65 6d 0a 49 70 73 75 6d 0a 44 6f 6c 6f [Lorem.Ipsum.Dolo]
      10 : 72 0a 53 69 74 0a 41 6d 65 74 0a                [r.Sit.Amet.]


Getting object properties
=========================

.. code:: php

   <?php

   use Kuria\Debug\Dumper;

   class Foo
   {
       public static $staticProperty = 'lorem';
       public $publicProperty = 'ipsum';
       private $privateProperty = 'dolor';
   }

   print_r(Dumper::getObjectProperties(new Foo()));

Output:

::

  Array
  (
      [staticProperty] => ReflectionProperty Object
          (
              [name] => staticProperty
              [class] => Foo\Foo
          )

      [publicProperty] => ReflectionProperty Object
          (
              [name] => publicProperty
              [class] => Foo\Foo
          )

      [privateProperty] => ReflectionProperty Object
          (
              [name] => privateProperty
              [class] => Foo\Foo
          )

  )


Output
******

Utilities related to PHP's output system.


Cleaning output buffers
=======================

.. code:: php

   <?php

   use Kuria\Debug\Output;

   // clean all buffers
   Output::cleanBuffers();

   // clean buffers up to a certain level
   Output::cleanBuffers(2);

   // clean all buffers and catch exceptions
   $bufferedOutput = Output::cleanBuffers(null, true);


Capturing output buffers
========================

.. code:: php

   <?php

   use Kuria\Debug\Output;

   // capture all buffers
   Output::captureBuffers();

   // capture buffers up to a certain level
   Output::captureBuffers(2);

   // capture all buffers and catch exceptions
   $bufferedOutput = Output::captureBuffers(null, true);


Replacing all headers
=====================

Replace all headers (unless they've been sent already):

.. code:: php

   <?php

   use Kuria\Debug\Output;

   Output::replaceHeaders(['Content-Type: text/plain; charset=UTF-8']);


Error
*****

PHP error utilities.


Getting name of a PHP error code
================================

.. code:: php

   <?php

   use Kuria\Debug\Error;

   var_dump(Error::getName(E_USER_ERROR));

Output:

::

  string(10) "E_USER_ERROR"


Exception
*********

Exception utilities.

Rendering an exception
======================

.. code:: php

   <?php

   use Kuria\Debug\Exception;

   $invalidArgumentException = new \InvalidArgumentException('Bad argument', 123);
   $runtimeException = new \RuntimeException('Something went wrong', 0, $invalidArgumentException);

   echo Exception::render($runtimeException);

Output:

::

  RuntimeException: Something went wrong in example.php on line 6
  #0 {main}


Including all previous exceptions and excluding the traces
----------------------------------------------------------

.. code:: php

   <?php

   echo Exception::render($runtimeException, false, true);

Output:

::

  [1/2] RuntimeException: Something went wrong in example.php on line 6
  [2/2] InvalidArgumentException (123): Bad argument in example.php on line 5


Getting a list of all previous exceptions
=========================================

.. code:: php

   <?php

   use Kuria\Debug\Exception;

   try {
       try {
           throw new \InvalidArgumentException('Invalid parameter');
       } catch (\InvalidArgumentException $e) {
           throw new \RuntimeException('Something went wrong', 0, $e);
       }
   } catch (\RuntimeException $e) {
       $exceptions = Exception::getChain($e);

       foreach ($exceptions as $exception) {
           echo $exception->getMessage(), "\n";
       }
   }

Output:

::

  Something went wrong
  Invalid parameter


Joining exception chains together
=================================

Joining exception chains has some uses in exception-handling code where
additional exception may be thrown.

.. code:: php

   <?php

   use Kuria\Debug\Exception;

   $c = new \Exception('C');
   $b = new \Exception('B', 0, $c);
   $a = new \Exception('A', 0, $b);

   $z = new \Exception('Z');
   $y = new \Exception('Y', 0, $z);
   $x = new \Exception('X', 0, $y);

   // print current chains
   echo "A's chain:\n", Exception::render($a, false, true), "\n\n";
   echo "X's chain:\n", Exception::render($x, false, true), "\n\n";

   // join chains (any number of exceptions can be passed)
   // from right to left: the last previous exception is joined to the exception on the left
   Exception::joinChains($a, $x);

   // print the modified X chain
   echo "X's modified chain:\n", Exception::render($x, false, true), "\n";

Output:

::

  A's chain:
  [1/3] Exception: A in example.com on line 7
  [2/3] Exception: B in example.com on line 6
  [3/3] Exception: C in example.com on line 5

  X's chain:
  [1/3] Exception: X in example.com on line 11
  [2/3] Exception: Y in example.com on line 10
  [3/3] Exception: Z in example.com on line 9

  X's modified chain:
  [1/6] Exception: X in example.com on line 11
  [2/6] Exception: Y in example.com on line 10
  [3/6] Exception: Z in example.com on line 9
  [4/6] Exception: A in example.com on line 7
  [5/6] Exception: B in example.com on line 6
  [6/6] Exception: C in example.com on line 5


Simplified real-world example
-----------------------------

Without joining exception chains
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code:: php

   <?php

   use Kuria\Debug\Exception;

   // print uncaught exceptions
   set_exception_handler(function ($uncaughtException) {
       echo Exception::render($uncaughtException, false, true);
   });

   try {
       // some code which may throw an exception
       throw new \Exception('Initial exception');
   } catch (\Exception $exception) {
       // handle the exception
       try {
           // some elaborate exception-handling code which may also throw an exception
           throw new \Exception('Exception-handler exception');
       } catch (\Exception $additionalException) {
           // the exception-handling code has crashed
           throw new \Exception('Final exception', 0, $additionalException);
       }
   }

Output:

::

  [1/2] Exception: Final exception in example.php on line 20
  [2/2] Exception: Exception-handler exception in example.php on line 17

Notice that the information about *Initial exception* is lost completely.

We could glue the *Initial exception*'s info to the *Final exception*'s message,
but that would be rather ugly and hard to read.


With joining exception chains
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

.. code:: php

   <?php

   try {
       // some code which may throw an exception
       throw new \Exception('Initial exception');
   } catch (\Exception $exception) {
       // handle the exception
       try {
           // some elaborate exception-handling code which may also throw an exception
           throw new \Exception('Exception-handler exception');
       } catch (\Exception $additionalException) {
           // the exception-handling code has crashed

           // join exception chains
           Exception::joinChains($exception, $additionalException);

           throw new \Exception('Something went wrong while handling an exception', 0, $additionalException);
       }
   }

Output:

::

    [1/3] Exception: Something went wrong while handling an exception in example.php on line 24
    [2/3] Exception: Exception-handler exception in /example.php on line 17
    [3/3] Exception: Initial exception in example.php on line 12

Now the *Initial exception* is accessible as one of the previous exceptions.
