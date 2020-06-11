<?php declare(strict_types=1);
/**
 * This file is part of toolkit/sys-utils.
 *
 * @author   https://github.com/inhere
 * @link     https://github.com/php-toolkit/sys-utils
 * @license  MIT
 */

class Some
{
    public $prop0;

    private $prop1;

    protected $prop2;

    /**
     * @return mixed
     */
    public function getProp1()
    {
        return $this->prop1;
    }
}


echo "use class:\n";

echo 'public: ' . (property_exists(Some::class, 'prop0') ? 'Y' : 'N') . PHP_EOL;
echo 'private: ' . (property_exists(Some::class, 'prop1') ? 'Y' : 'N') . PHP_EOL;
echo 'protected: ' . (property_exists(Some::class, 'prop2') ? 'Y' : 'N') . PHP_EOL;
echo "use object:\n";

$object = new Some();

echo 'public: ' . (property_exists($object, 'prop0') ? 'Y' : 'N') . PHP_EOL;
echo 'private: ' . (property_exists($object, 'prop1') ? 'Y' : 'N') . PHP_EOL;
echo 'protected: ' . (property_exists($object, 'prop2') ? 'Y' : 'N') . PHP_EOL;
