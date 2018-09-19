Changelog
#########

4.0.1
*****

- ``Dumper`` now calls ``__toString()`` directly and catches any exceptions


4.0.0
*****

- split ``Error`` class into ``Error`` and ``Exception``
- ``Error::getName()`` now returns the constant name instead of an english transcription
- ``Dumper`` now displays source information for anonymous classes


3.0.0
*****

- changed class members from protected to private
- cs fixes, added codestyle checks


2.0.0
*****

- updated to PHP 7.1
- ``Dumper::getObjectProperties()`` now always returns ``ReflectionProperty[]``
- added ``Output::captureBuffers()`` (separated from ``Output::cleanBuffers()``)
- code style improvements


1.0.3
*****

- removed unused file


1.0.2
*****

- ``Dumper`` now displays the ``-INF`` float value correctly
- code style fixes


1.0.1
******

- moved default ``Dumper`` limits to class constants
- code style fixes


1.0.0
*****

Initial release

- this component has been separated from kuria/error
- refactoring, bug fixes and improvements
