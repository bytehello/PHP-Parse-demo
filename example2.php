<?php
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require 'vendor/autoload.php';

class MyNodeVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node) {
        if ($node instanceof Node\Scalar\String_) {
            $node->value = 'World';
        }
    }
}


$code = <<<'CODE'
<?php
echo 'Hello';
CODE;
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
$traverser = new NodeTraverser;
// add your visitor
$traverser->addVisitor(new MyNodeVisitor);
$ast = $parser->parse($code);
$traverser->traverse($ast);
$prettyPrinter = new Standard();
echo $prettyPrinter->prettyPrintFile($ast);
