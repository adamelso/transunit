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
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public static function create(): self
    {
        return new self(new Filesystem());
    }

    public function run(string $path, string $destination = 'var'): void
    {
        $specs = (new Finder())->files()->in($path)->name('*Spec.php');

        $root = dirname(__DIR__, 2);
        $exportDir = "{$root}/{$destination}";

        // @todo Confirm filesystem changes with user.
        // $this->filesystem->remove($exportDir);
        $this->filesystem->mkdir($exportDir);

        foreach ($specs as $file) {
            $relative = trim($this->filesystem->makePathRelative($file->getPath(), $path), DIRECTORY_SEPARATOR);
            $newFilename = substr($file->getBasename(), 0, -8) . 'Test.php';
            $fullPathToNewTestFile = "{$exportDir}/{$relative}/{$newFilename}";

            $modifiedCode = self::processFile($file->getRealPath());

            $this->filesystem->mkdir("{$exportDir}/{$relative}");
            $this->filesystem->touch($fullPathToNewTestFile);
            $this->filesystem->dumpFile($fullPathToNewTestFile, $modifiedCode);
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

        $rewritePasses = [
            new Pass\MoveNamespacePass(),
            new Pass\ImportSubjectClassPass(),
            new Pass\ImportMockingLibraryPass(),
            new Pass\RenameClassPass(),
            new Pass\ChangeExtendedClassPass(),
            new Pass\TestSubjectPropertyDeclarationPass(),
            new Pass\CreateSetupIfNoneExistsPass(), // run after DeclareTestSubjectPropertyPass
            new Pass\UseProphecyTraitPass(), // run after CreateSetupIfNoneExistsPass
            new Pass\RenameSetupPass(),
            new Pass\AddTestMethodPrefixPass(),
            new Pass\TestSubjectInstantiationPass(),
            new Pass\GlobalRevealPass(),
            new Pass\TestSubjectCallPass(),
            new Pass\AssertionPass(), // run after CallTestSubjectPass.
            new Pass\ExceptionAssertionPass(),
            new Pass\DeclareGlobalCollaboratorPass(),
            new Pass\ProphesizeGlobalCollaboratorsPass(),
            new Pass\CallGlobalCollaboratorPass(), // run after ProphesizeGlobalCollaboratorsPass
            new Pass\LocalRevealPass(), // run after ProphesizeGlobalCollaboratorsPass
            new Pass\ProphesizeLocalCollaboratorsPass(), // run after LocalRevealPass
            new Pass\TestSubjectAsArgumentPass(),
            new Pass\ReplaceCallsToGetWrappedObjectPass(),
        ];

        /** @var Pass $pass */
        foreach ($rewritePasses as $pass) {
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
