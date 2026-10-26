<?php

declare(strict_types=1);

namespace Evidence\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Parser\Parser;

/** Recognize the reviewed procedural DB imports, never arbitrary undefined PDO variables. */
final class BootstrapPdoExtension implements IgnoreErrorExtension
{
    private const BOOTSTRAPS = [
        'booking/eshop.php' => 'db.php',
        'booking/moje_objednavky.php' => 'db.php',
        'booking/objednavka.php' => 'db.php',
        'individualni_lekce_sprava.php' => 'db.php',
        'kis_review_admin.php' => 'includes/init.php',
        'kis_sync_center.php' => 'includes/init.php',
        'member_charges_admin.php' => 'includes/init.php',
        'zavod_detail.php' => 'db.php',
        'zavod_vysledky.php' => 'includes/init.php',
    ];

    /** @var array<string, int|null> */
    private array $imports = [];

    public function __construct(private Parser $parser, private string $projectRoot)
    {
        $this->projectRoot = str_replace('\\', '/', $projectRoot);
    }

    public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
    {
        if (
            $error->getIdentifier() !== 'variable.undefined'
            || $error->getMessage() !== 'Variable $pdo might not be defined.'
            || !$node instanceof Expr\Variable || $node->name !== 'pdo'
            || $scope->getFunction() !== null || $scope->isInAnonymousFunction() || $scope->isInClass()
        ) {
            return false;
        }
        $file = str_replace('\\', '/', $scope->getFile());
        $relative = substr($file, strlen($this->projectRoot) + 1);
        if (!str_starts_with($file, $this->projectRoot . '/') || !isset(self::BOOTSTRAPS[$relative])) {
            return false;
        }
        if (!array_key_exists($file, $this->imports)) {
            $this->imports[$file] = null;
            $statements = $this->parser->parseFile($file);
            $finder = new \PhpParser\NodeFinder();
            foreach ($finder->findInstanceOf($statements, Node\Stmt\Unset_::class) as $unset) {
                foreach ($unset->vars as $variable) {
                    if ($variable instanceof Expr\Variable && $variable->name === 'pdo') {
                        return false;
                    }
                }
            }
            foreach ($statements as $statement) {
                // A conditional/try/function import does not guarantee the variable exists.
                if (!$statement instanceof Node\Stmt\Expression || !$statement->expr instanceof Expr\Include_) {
                    continue;
                }
                $include = $statement->expr;
                if (!in_array($include->type, [Expr\Include_::TYPE_REQUIRE, Expr\Include_::TYPE_REQUIRE_ONCE], true)) {
                    continue;
                }
                $path = $this->literalPath($include->expr, dirname($file));
                if ($path === self::BOOTSTRAPS[$relative]) {
                    $path = $this->projectRoot . '/' . $path;
                }
                if ($path === $this->projectRoot . '/' . self::BOOTSTRAPS[$relative]) {
                    $this->imports[$file] = $statement->getEndLine();
                    break;
                }
            }
        }
        $line = $this->imports[$file];
        return $line !== null && $node->getStartLine() > $line;
    }

    private function literalPath(Expr $expression, string $directory): ?string
    {
        if ($expression instanceof Node\Scalar\String_) {
            return $expression->value;
        }
        if ($expression instanceof Node\Scalar\MagicConst\Dir) {
            return $directory;
        }
        if ($expression instanceof Expr\BinaryOp\Concat) {
            $left = $this->literalPath($expression->left, $directory);
            $right = $this->literalPath($expression->right, $directory);
            return $left !== null && $right !== null ? $left . $right : null;
        }
        if (
            $expression instanceof Expr\FuncCall && $expression->name instanceof Node\Name
            && $expression->name->toLowerString() === 'dirname' && count($expression->getArgs()) === 1
        ) {
            $path = $this->literalPath($expression->getArgs()[0]->value, $directory);
            return $path !== null ? dirname($path) : null;
        }
        return null;
    }
}
