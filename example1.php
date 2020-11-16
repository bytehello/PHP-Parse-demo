<?php

use PhpParser\NodeDumper;
use PhpParser\ParserFactory;

require 'vendor/autoload.php';

$code = <<<'CODE'
<?php
namespace NameSpace;
class SomeClass
{
    protected function echo()
    {
        echo 'Hello';
    }
}
CODE;

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$ast = $parser->parse($code);
$nodeDumper = new NodeDumper();
echo $nodeDumper->dump($ast), "\n";
