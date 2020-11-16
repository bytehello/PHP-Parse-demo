<?php

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;

require 'vendor/autoload.php';

$factor = new BuilderFactory;
$node = $factor->namespace('Name\Space')
    ->addStmt($factor->use('Tools'))
    ->addStmt($factor->class('SomeClass')
        ->addStmt($factor->method('check')
            ->makeProtected()
            ->addParam($factor->param('orderData')->setDefault(null))
            ->addStmt(
                new Node\Expr\Assign($factor->var('ttt'), new Expr\MethodCall(new Expr\Variable('this'), 'check', [
                    new Node\Arg(new Node\Scalar\MagicConst\Class_())
                ]))
            )
            ->addStmt(
                new Node\Stmt\If_($factor->var('ttt'), [
                    'stmts' => array(
                        new Node\Stmt\Expression(new Node\Expr\Print_(new Node\Expr\Variable('someParam')))
                    )
                ])
            )))
    ->getNode();
$stmts = array($node);
$prettyPrinter = new Standard();
echo $prettyPrinter->prettyPrintFile($stmts);
