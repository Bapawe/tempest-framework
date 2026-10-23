<?php

namespace Tempest\Upgrade\Tempest321;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\NodeFinder;
use Rector\PhpParser\Node\FileNode;
use Rector\Rector\AbstractRector;
use Tempest\Router\HttpApplication;

/**
 * Adds the early maintenance check, which answers requests before the autoloader is loaded, to `public/index.php`.
 */
final class AddEarlyMaintenanceCheckRector extends AbstractRector
{
    private const string CHECK_PATH = '/../.tempest/maintenance/check.php';

    public function getNodeTypes(): array
    {
        return [
            FileNode::class,
        ];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof FileNode) {
            return null;
        }

        if (! $this->bootsHttpApplication($node) || $this->hasMaintenanceCheck($node)) {
            return null;
        }

        $autoloadIndex = $this->findAutoloadRequire($node->stmts);

        if ($autoloadIndex === null) {
            return null;
        }

        array_splice($node->stmts, $autoloadIndex, 0, [new Nop(), $this->createMaintenanceCheck()]);

        return $node;
    }

    private function bootsHttpApplication(FileNode $file): bool
    {
        return (
            new NodeFinder()->findFirst(
                $file->stmts,
                fn (Node $node) => $node instanceof StaticCall && $this->isName($node->class, HttpApplication::class) && $this->isName($node->name, 'boot'),
            ) instanceof StaticCall
        );
    }

    private function hasMaintenanceCheck(FileNode $file): bool
    {
        return (
            new NodeFinder()->findFirst(
                $file->stmts,
                static fn (Node $node) => $node instanceof String_ && str_ends_with($node->value, '/maintenance/check.php'),
            ) instanceof String_
        );
    }

    /**
     * @param Stmt[] $stmts
     */
    private function findAutoloadRequire(array $stmts): ?int
    {
        foreach ($stmts as $index => $stmt) {
            if (! $stmt instanceof Expression) {
                continue;
            }
            if (! $stmt->expr instanceof Include_) {
                continue;
            }
            $requiresAutoload = new NodeFinder()->findFirst(
                $stmt->expr,
                static fn (Node $node) => $node instanceof String_ && str_ends_with($node->value, 'vendor/autoload.php'),
            );

            if ($requiresAutoload instanceof String_) {
                return $index;
            }
        }

        return null;
    }

    private function createMaintenanceCheck(): If_
    {
        $maintenance = new Variable('maintenance');

        return new If_(
            new FuncCall(new Name('is_file'), [
                new Arg(new Assign($maintenance, new Concat(new Dir(), new String_(self::CHECK_PATH)))),
            ]),
            [
                'stmts' => [new Expression(new Include_($maintenance, Include_::TYPE_REQUIRE))],
            ],
        );
    }
}
