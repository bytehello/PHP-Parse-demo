# PHP-Parse-demo

博文地址：https://www.cnblogs.com/abyte/p/13984983.html
## 介绍

PHP-Parse 是分析 PHP 代码生成 AST 的库，分析出可读性很高的对象数据结构，方便后续的更新和遍历。
PHP-Parse 的主要作用是修改原有代码（比如插入自定义的代码片段），生成新的代理类 。框架内生成代理类，处理注入有用到，比如 Hyperf 的 DI 组件利用反射和 PHP-Parse 实现了注入。

AST 的简单介绍 具体搜索，资料很多，讲得很好
比如下面 PHP 代码会被解析成

```
<?php
namespace NameSpace;
class SomeClass
{
    protected function echo()
    {
        echo 'Hello';
    }
}
```

```
array(
    0: Stmt_Namespace(
        name: Name(
            parts: array(
                0: NameSpace
            )
        )
        stmts: array(
            0: Stmt_Class(
                attrGroups: array(
                )
                flags: 0
                name: Identifier(
                    name: SomeClass
                )
                extends: null
                implements: array(
                )
                stmts: array(
                    0: Stmt_ClassMethod(
                        attrGroups: array(
                        )
                        flags: MODIFIER_PROTECTED (2)
                        byRef: false
                        name: Identifier(
                            name: echo
                        )
                        params: array(
                        )
                        returnType: null
                        stmts: array(
                            0: Stmt_Echo(
                                exprs: array(
                                    0: Scalar_String(
                                        value: World
                                    )
                                )
                            )
                        )
                    )
                )
            )
        )
    )
)
```

大致对照看一下，解析后的对象对照源代码，很清晰。

其中 stmts 表示节点中包含的 PHP 语句。比如 Stmt_Namespace 对象（NameSpace 命名空间） 的 stmts 数组含有一个 Stmt_Class 对象（SomeClass 类名），Stmt_Class 对象的 stmts 数组含有一个 Stmt_ClassMethod（echo 方法）

以上执行代码在 https://github.com/bytehello/PHP-Parse-demo/blob/main/example1.php

## 节点类型

1. statement node 没有返回值，不会出现在别的语句当中，比如说类定义，不会出现func(class A {});
2. expr node 有返回值，会出现在别的语句当中。比如func()、$foo
3. scalar values 标量值，比如 'string' (PhpParser\Node\Scalar\String_)
4. 还有一些其他分类：名字（ PhpParser\Node\Name），调用参数（ PhpParser\Node\Arg）

其中
Node\Stmt\Expression 表示 ```expr;```，Node\Expr 表示```expr```。
区别是一个带分号，一个不带分号

## 操作节点

### 节点修改

节点的遍历和修改修改是通过添加 visitor，原理就是在遍历 AST 的时候，会调用到 visitor 中的方法，我们想要修改节点只要实现 visitor 中的方法即可。具体的修改操作是在 visitor 这个对象内完成

打个实际的例子 别墅(AST)需要装修,管家(NodeTraverser)带着装修队(visitor)去到别墅的房间一间一间浏览，在进入房间后(调用visitor的enterNode方法)，装修队会记录房间的内容(enterNode方法你自己的实现，当然也可以什么都不做)，在离开房间后(调用visitor的leaveNode方法)，装修队开始施工(修改节点)

```
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

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
        $prettyPrinter = new PrettyPrinter\Standard();
        echo $prettyPrinter->prettyPrintFile($ast);
```

```
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

class MyNodeVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node) {
        if ($node instanceof Node\Scalar\String_) {
            $node->value = 'World';
        }
    }
}
```

执行以上后会输出

```
<?php
echo 'World';
```

在遍历 AST 时，visitor 会调用多个方法：enterNode、leaveNode 等，修改节点的操作通常都是在 leaveNode 中完成（这句话是重点，下面会用到）。

以上执行代码在 https://github.com/bytehello/PHP-Parse-demo/blob/main/example2.php

参考 https://github.com/nikic/PHP-Parser/blob/master/doc/component/Walking_the_AST.markdown

### 代码构造

除了遍历，还可以直接构造 PHP 代码，比如

```
use PhpParser\NodeDumper;
use PhpParser\BuilderFactory;
use PhpParser\PrettyPrinter;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\If_;
$factor = new BuilderFactory;
        $node = $factor->namespace('Name\Space')
            ->addStmt($factor->use('Tools'))
            ->addStmt($factor->class('SomeClass')
                ->addStmt($factor->method('check')
                    ->makeProtected()
                    ->addParam($factor->param('orderData')->setDefault(null))
                    ->addStmt(
                        new Node\Expr\Assign($factor->var('ttt'), new Expr\MethodCall(new Expr\Variable('this'),'check', [
                            new Node\Arg(new Node\Scalar\MagicConst\Class_())
                        ]))
                    )
                    ->addStmt(
                        new Node\Stmt\If_($factor->var('ttt'), [
                            'stmts' => array(
                                new Node\Stmt\Expression(new Node\Expr\Print_(new Node\Expr\Variable('someParam'))) // 1.
                            )
                        ])
                    )
                    )
                    )
            ->getNode();;
        $stmts = array($node);
        $prettyPrinter = new PrettyPrinter\Standard();
        echo $prettyPrinter->prettyPrintFile($stmts);
```

执行上述代码生成

```
<?php

namespace Name\Space;

use Tools;
class SomeClass
{
    protected function check($orderData = null)
    {
        $ttt = $this->check(__CLASS__);
        if ($ttt) {
            print $someParam;
        }
    }
}
```

**试一试**：大家可以试试 上述代码标记为1的地方去掉new Node\Stmt\Expression()的包裹，试试看输出，就能理解

>  Node\Stmt\Expression 表示 expr;Node\Expr 表示 expr

以上执行代码在 https://github.com/bytehello/PHP-Parse-demo/blob/main/example3.php

参考 https://github.com/nikic/PHP-Parser/blob/master/doc/component/AST_builders.markdown

## 实例讲解

以 https://github.com/hyperf/hyperf-skeleton的骨架讲解

项目启动会在runtime下利用 AST 生成代理文件
![](https://img2020.cnblogs.com/blog/789032/202011/789032-20201116144017408-1619849677.png)



代理文件内容的部分如下

```
abstract class AbstractController
{
    use \Hyperf\Di\Aop\ProxyTrait;
    use \Hyperf\Di\Aop\PropertyHandlerTrait;
    function __construct()
    {
        self::__handlePropertyHandler(__CLASS__);
    }
```



新增的方法是 **__handlePropertyHandler** 方法，同时还新增了**use \Hyperf\Di\Aop\ProxyTrait 和 use \Hyperf\Di\Aop\PropertyHandlerTrait**

下面详解是如何新增的
1.从入口文件bin/hyperf.php中Hyperf\Di\ClassLoader::init() 开始 依次调用

2.vendor\hyperf\di\src\ClassLoader.php 的 **__construct**

3.vendor\hyperf\di\src\Aop\ProxyManager.php 的**__construct**，此构造方法内有生成代理类方法 generateProxyFiles

4.generateProxyFiles 内调用 putProxyFile 生成代理文件

5.putProxyFile 方法内部 其实调用了vendor\hyperf\di\src\Aop\Ast.php 的 proxy 方法，添加了若干的visitor，比如"Hyperf\Di\Aop\PropertyHandlerVisitor"、"Hyperf\Di\Aop\ProxyCallVisitor"

具体看 PropertyHandlerVisitor

```
public function leaveNode(Node $node)
    {
         // 仅提取了关键代码
         $constructor = $this->buildConstructor(); 
         $constructor->stmts[] = $this->buildStaticCallStatement(); 
         $node->stmts = array_merge(/* 构造了 Trait */, [$constructor], $node->stmts);
    }

protected function buildStaticCallStatement(): Node\Stmt\Expression
    {
        return new Node\Stmt\Expression(new Node\Expr\StaticCall(new Name('self'), '__handlePropertyHandler', [
            new Node\Arg(new Node\Scalar\MagicConst\Class_()),
        ]));
    }
```



一目了然，就是操作 node 的 stmts 数组。vendor\hyperf\di\src\Aop\PropertyHandlerVisitor.php buildStaticCallStatement 方法就是添加 __handlePropertyHandler 的地方
注：vendor\hyperf\di\src\Aop\PropertyHandlerVisitor.php 在 Hyperf 的 v2.0.19 的代码与上述有出入，具体看本人提的PR： https://github.com/hyperf/hyperf/pull/2788

总结：

1. 了解了PHP-Parse的基本用法：解析、遍历、修改

2. 了解了PHP-Parse在Hyperf中的应用场景

作为实践，本人也写了个小工具用户生成 PHP 条件语句的代码片段，有兴趣的同学可以看看哈 https://github.com/bytehello/condition-builder
