<?php

namespace Transunit;

use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Transunit
{
    public static function run(string $path): void
    {
        $f = new Filesystem();
        $specs = (new Finder())->files()->in($path)->name('*Spec.php');

        $root = dirname(__DIR__, 2);
        $exportDir = "{$root}/var";
        $f->remove($exportDir);
        $f->mkdir($exportDir);

        foreach ($specs as $file) {
            $newFilename = substr($file->getBasename(), 0, -8) . 'Test.php';
            $modifiedCode = self::processFile($file->getRealPath());
            $fullPathToNewTestFile = "{$exportDir}/{$newFilename}";
            $f->touch($fullPathToNewTestFile);
            file_put_contents($fullPathToNewTestFile, $modifiedCode);
        }
    }

    private static function processFile(string $path): string
    {
        $phpCode = @file_get_contents($path);

        if (null === $phpCode) {
            throw new \RuntimeException('Failed to read file: ' . $path);
        }

        $parser = (new ParserFactory)->createForHostVersion();
        $sourceAst = $parser->parse($phpCode);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());

        $newStmts = $traverser->traverse($sourceAst);

        $nodeFinder = new NodeFinder();
        $passes = [
            new Pass\NamespacePass(),
            new Pass\ClassnamePass(),
            new Pass\GlobalCollaboratorPass(),
            new Pass\GlobalTestSubjectInstancePass(),
            new Pass\CallTestSubjectPass(),
            new Pass\AssertionPass(),
            new Pass\TestMethodPass(),
        ];

        /** @var Pass $pass */
        foreach ($passes as $pass) {
            $nodes = $pass->find($nodeFinder, $newStmts);
            foreach ($nodes as $node) {
                $pass->rewrite($node);
            }
        }

        $prettyPrinter = new PrettyPrinter\Standard();
        $modifiedCode = $prettyPrinter->printFormatPreserving($newStmts, $sourceAst, $parser->getTokens());

        return $modifiedCode;
    }
}
