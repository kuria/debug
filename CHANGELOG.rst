Changelog
#########

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
