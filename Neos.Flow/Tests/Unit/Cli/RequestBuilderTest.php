<?php
namespace Neos\Flow\Tests\Unit\Cli;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Command\HelpCommandController;
use Neos\Flow\Mvc\Exception\InvalidArgumentMixingException;
use Neos\Flow\Mvc\Exception\NoSuchCommandException;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use Neos\Flow\Tests\UnitTestCase;
use Neos\Flow\Cli;

/**
 * Testcase for the MVC CLI Request Builder
 */
class RequestBuilderTest extends UnitTestCase
{
    /**
     * @var Cli\RequestBuilder
     */
    protected $requestBuilder;

    /**
     * @var ObjectManagerInterface
     */
    protected $mockObjectManager;

    /**
     * @var Cli\Command
     */
    protected $mockCommand;

    /**
     * @var Cli\CommandManager
     */
    protected $mockCommandManager;

    /**
     * @var ReflectionService
     */
    protected $mockReflectionService;

    /**
     * Sets up this test case
     *
     */
    protected function setUp(): void
    {
        $this->mockObjectManager = $this->createMock(ObjectManagerInterface::class);
        $this->mockObjectManager->expects(self::any())->method('getObjectNameByClassName')->with('Acme\Test\Command\DefaultCommandController')->will(self::returnValue('Acme\Test\Command\DefaultCommandController'));

        $this->mockCommand = $this->getMockBuilder(Cli\Command::class)->disableOriginalConstructor()->getMock();
        $this->mockCommand->expects(self::any())->method('getControllerClassName')->will(self::returnValue('Acme\Test\Command\DefaultCommandController'));
        $this->mockCommand->expects(self::any())->method('getControllerCommandName')->will(self::returnValue('list'));

        $this->mockCommandManager = $this->createMock(Cli\CommandManager::class);
        $this->mockCommandManager->expects(self::any())->method('getCommandByIdentifier')->with('acme.test:default:list')->will(self::returnValue($this->mockCommand));

        $this->mockReflectionService = $this->createMock(ReflectionService::class);

        $this->requestBuilder = new Cli\RequestBuilder();
        $this->requestBuilder->injectObjectManager($this->mockObjectManager);
        $this->requestBuilder->injectCommandManager($this->mockCommandManager);
    }

    /**
     * Checks if a CLI request specifying a package, controller and action name results in the expected request object
     *
     * @test
     */
    public function cliAccessWithPackageControllerAndActionNameBuildsCorrectRequest()
    {
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->will(self::returnValue([]));

        $request = $this->requestBuilder->build('acme.test:default:list');
        self::assertSame('Acme\Test\Command\DefaultCommandController', $request->getControllerObjectName());
        self::assertSame('list', $request->getControllerCommandName(), 'The CLI request specifying a package, controller and action name did not return a request object pointing to the expected action.');
    }

    /**
     * @test
     */
    public function ifCommandCantBeResolvedTheHelpScreenIsShown()
    {
        // The following call is only made to satisfy PHPUnit. For some weird reason PHPUnit complains that the
        // mocked method ("getObjectNameByClassName") does not exist _if the mock object is not used_.
        $this->mockObjectManager->getObjectNameByClassName('Acme\Test\Command\DefaultCommandController');
        $this->mockCommandManager->getCommandByIdentifier('acme.test:default:list');

        $mockCommandManager = $this->createMock(Cli\CommandManager::class);
        $mockCommandManager->expects(self::any())->method('getCommandByIdentifier')->with('test:default:list')->will(self::throwException(new NoSuchCommandException()));
        $this->requestBuilder->injectCommandManager($mockCommandManager);

        $request = $this->requestBuilder->build('test:default:list');
        self::assertSame(HelpCommandController::class, $request->getControllerObjectName());
    }

    /**
     * Checks if a CLI request specifying some "console style" (--my-argument=value) arguments results in the expected request object
     *
     * @test
     */
    public function cliAccessWithPackageControllerActionAndArgumentsBuildsCorrectRequest()
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string']
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list --test-argument=value --test-argument2=value2');
        self::assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument2'), 'The given "testArgument2" was not found in the built request.');
        self::assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
        self::assertSame($request->getArgument('testArgument2'), 'value2', 'The "testArgument2" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some "console style" (--my-argument =value) arguments with spaces between name and value results in the expected request object
     *
     * @test
     */
    public function checkIfCliAccesWithPackageControllerActionAndArgumentsToleratesSpaces()
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
            'testArgument3' => ['optional' => false, 'type' => 'string'],
            'testArgument4' => ['optional' => false, 'type' => 'string']
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list --test-argument= value --test-argument2 =value2 --test-argument3 = value3 --test-argument4=value4');
        self::assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument2'), 'The given "testArgument2" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument3'), 'The given "testArgument3" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument4'), 'The given "testArgument4" was not found in the built request.');
        self::assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
        self::assertSame($request->getArgument('testArgument2'), 'value2', 'The "testArgument2" had not the given value.');
        self::assertSame($request->getArgument('testArgument3'), 'value3', 'The "testArgument3" had not the given value.');
        self::assertSame($request->getArgument('testArgument4'), 'value4', 'The "testArgument4" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some short "console style" (-c value or -c=value or -c = value) arguments results in the expected request object
     *
     * @test
     */
    public function CliAccesWithShortArgumentsBuildsCorrectRequest()
    {
        $methodParameters = [
            'a' => ['optional' => false, 'type' => 'string'],
            'd' => ['optional' => false, 'type' => 'string'],
            'f' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list -d valued -f=valuef -a = valuea');
        self::assertTrue($request->hasArgument('d'), 'The given "d" was not found in the built request.');
        self::assertTrue($request->hasArgument('f'), 'The given "f" was not found in the built request.');
        self::assertTrue($request->hasArgument('a'), 'The given "a" was not found in the built request.');
        self::assertSame($request->getArgument('d'), 'valued', 'The "d" had not the given value.');
        self::assertSame($request->getArgument('f'), 'valuef', 'The "f" had not the given value.');
        self::assertSame($request->getArgument('a'), 'valuea', 'The "a" had not the given value.');
    }

    /**
     * Checks if a CLI request specifying some mixed "console style" (-c or --my-argument -f=value) arguments with and
     * without values results in the expected request object
     *
     * @test
     */
    public function CliAccesWithArgumentsWithAndWithoutValuesBuildsCorrectRequest()
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
            'testArgument3' => ['optional' => false, 'type' => 'string'],
            'testArgument4' => ['optional' => false, 'type' => 'string'],
            'testArgument5' => ['optional' => false, 'type' => 'string'],
            'testArgument6' => ['optional' => false, 'type' => 'string'],
            'testArgument7' => ['optional' => false, 'type' => 'string'],
            'f' => ['optional' => false, 'type' => 'string'],
            'd' => ['optional' => false, 'type' => 'string'],
            'a' => ['optional' => false, 'type' => 'string'],
            'c' => ['optional' => false, 'type' => 'string'],
            'j' => ['optional' => false, 'type' => 'string'],
            'k' => ['optional' => false, 'type' => 'string'],
            'm' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list --test-argument=value --test-argument2= value2 -k --test-argument-3 = value3 --test-argument4=value4 -f valuef -d=valued -a = valuea -c --testArgument7 --test-argument5 = 5 --test-argument6 -j kjk -m');
        self::assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument2'), 'The given "testArgument2" was not found in the built request.');
        self::assertTrue($request->hasArgument('k'), 'The given "k" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument3'), 'The given "testArgument3" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument4'), 'The given "testArgument4" was not found in the built request.');
        self::assertTrue($request->hasArgument('f'), 'The given "f" was not found in the built request.');
        self::assertTrue($request->hasArgument('d'), 'The given "d" was not found in the built request.');
        self::assertTrue($request->hasArgument('a'), 'The given "a" was not found in the built request.');
        self::assertTrue($request->hasArgument('c'), 'The given "d" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument7'), 'The given "testArgument7" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument5'), 'The given "testArgument5" was not found in the built request.');
        self::assertTrue($request->hasArgument('testArgument6'), 'The given "testArgument6" was not found in the built request.');
        self::assertTrue($request->hasArgument('j'), 'The given "j" was not found in the built request.');
        self::assertTrue($request->hasArgument('m'), 'The given "m" was not found in the built request.');
        self::assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
        self::assertSame($request->getArgument('testArgument2'), 'value2', 'The "testArgument2" had not the given value.');
        self::assertSame($request->getArgument('testArgument3'), 'value3', 'The "testArgument3" had not the given value.');
        self::assertSame($request->getArgument('testArgument4'), 'value4', 'The "testArgument4" had not the given value.');
        self::assertSame($request->getArgument('f'), 'valuef', 'The "f" had not the given value.');
        self::assertSame($request->getArgument('d'), 'valued', 'The "d" had not the given value.');
        self::assertSame($request->getArgument('a'), 'valuea', 'The "a" had not the given value.');
        self::assertSame($request->getArgument('testArgument5'), '5', 'The "testArgument4" had not the given value.');
        self::assertSame($request->getArgument('j'), 'kjk', 'The "j" had not the given value.');
    }

    /**
     * @test
     */
    public function argumentWithValueSeparatedByEqualSignBuildsCorrectRequest()
    {
        $methodParameters = [
            'testArgument' => ['optional' => false, 'type' => 'string']
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list --test-argument=value');
        self::assertTrue($request->hasArgument('testArgument'), 'The given "testArgument" was not found in the built request.');
        self::assertSame($request->getArgument('testArgument'), 'value', 'The "testArgument" had not the given value.');
    }

    /**
     * @test
     */
    public function insteadOfNamedArgumentsTheArgumentsCanBePassedUnnamedInTheCorrectOrder()
    {
        $methodParameters = [
            'testArgument1' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::any())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list --test-argument1 firstArgumentValue --test-argument2 secondArgumentValue');
        self::assertSame('firstArgumentValue', $request->getArgument('testArgument1'));
        self::assertSame('secondArgumentValue', $request->getArgument('testArgument2'));

        $request = $this->requestBuilder->build('acme.test:default:list firstArgumentValue secondArgumentValue');
        self::assertSame('firstArgumentValue', $request->getArgument('testArgument1'));
        self::assertSame('secondArgumentValue', $request->getArgument('testArgument2'));
    }

    /**
     * @test
     */
    public function argumentsAreDetectedAfterOptions()
    {
        $methodParameters = [
            'some' => ['optional' => true, 'type' => 'boolean'],
            'option' => ['optional' => true, 'type' => 'string'],
            'argument1' => ['optional' => false, 'type' => 'string'],
            'argument2' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $request = $this->requestBuilder->build('acme.test:default:list --some -option=value file1 file2');
        self::assertSame('list', $request->getControllerCommandName());
        self::assertTrue($request->getArgument('some'));
        self::assertSame('file1', $request->getArgument('argument1'));
        self::assertSame('file2', $request->getArgument('argument2'));
    }

    /**
     * @test
     */
    public function exceedingArgumentsMayBeSpecified()
    {
        $methodParameters = [
            'testArgument1' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedArguments = ['testArgument1' => 'firstArgumentValue', 'testArgument2' => 'secondArgumentValue'];

        $request = $this->requestBuilder->build('acme.test:default:list --test-argument1=firstArgumentValue --test-argument2 secondArgumentValue exceedingArgument1');
        self::assertSame($expectedArguments, $request->getArguments());
        self::assertSame(['exceedingArgument1'], $request->getExceedingArguments());
    }

    /**
     * @test
     */
    public function ifNamedArgumentsAreUsedAllRequiredArgumentsMustBeNamed()
    {
        $this->expectException(InvalidArgumentMixingException::class);
        $methodParameters = [
            'testArgument1' => ['optional' => false, 'type' => 'string'],
            'testArgument2' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $this->requestBuilder->build('acme.test:default:list --test-argument1 firstArgumentValue secondArgumentValue');
    }

    /**
     * @test
     */
    public function ifUnnamedArgumentsAreUsedAllRequiredArgumentsMustBeUnnamed()
    {
        $this->expectException(InvalidArgumentMixingException::class);
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $this->requestBuilder->build('acme.test:default:list firstArgumentValue --required-argument2 secondArgumentValue');
    }

    /**
     * @test
     */
    public function booleanOptionsAreConsideredEvenIfAnUnnamedArgumentFollows()
    {
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string'],
            'booleanOption' => ['optional' => true, 'type' => 'boolean'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedArguments = ['requiredArgument1' => 'firstArgumentValue', 'requiredArgument2' => 'secondArgumentValue', 'booleanOption' => true];

        $request = $this->requestBuilder->build('acme.test:default:list --booleanOption firstArgumentValue secondArgumentValue');
        self::assertEquals($expectedArguments, $request->getArguments());
    }

    /**
     * @test
     */
    public function optionsAreNotMappedToCommandArgumentsIfTheyAreUnnamed()
    {
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string'],
            'booleanOption' => ['optional' => true, 'type' => 'boolean'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedArguments = ['requiredArgument1' => 'firstArgumentValue', 'requiredArgument2' => 'secondArgumentValue'];

        $request = $this->requestBuilder->build('acme.test:default:list firstArgumentValue secondArgumentValue true');
        self::assertSame($expectedArguments, $request->getArguments());
    }

    /**
     * @test
     */
    public function afterAllRequiredArgumentsUnnamedParametersAreStoredAsExceedingArguments()
    {
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string'],
            'booleanOption' => ['optional' => true, 'type' => 'boolean'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedExceedingArguments = ['true'];

        $request = $this->requestBuilder->build('acme.test:default:list firstArgumentValue secondArgumentValue true');
        self::assertSame($expectedExceedingArguments, $request->getExceedingArguments());
    }

    /**
     * @test
     */
    public function booleanOptionsCanHaveOnlyCertainValuesIfTheValueIsAssignedWithoutEqualSign()
    {
        $methodParameters = [
            'b1' => ['optional' => true, 'type' => 'boolean'],
            'b2' => ['optional' => true, 'type' => 'boolean'],
            'b3' => ['optional' => true, 'type' => 'boolean'],
            'b4' => ['optional' => true, 'type' => 'boolean'],
            'b5' => ['optional' => true, 'type' => 'boolean'],
            'b6' => ['optional' => true, 'type' => 'boolean'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedArguments = ['b1' => true, 'b2' => true, 'b3' => true, 'b4' => false, 'b5' => false, 'b6' => false];

        $request = $this->requestBuilder->build('acme.test:default:list --b2 y --b1 1 --b3 true --b4 false --b5 n --b6 0');
        self::assertEquals($expectedArguments, $request->getArguments());
    }

    /**
     * Data provider
     *
     * @return array
     */
    public function quotedValues()
    {
        return [
            ["'value with spaces'", 'value with spaces'],
            ["'value with spaces and \\' escaped'", 'value with spaces and \' escaped'],
            ['"value with spaces"', 'value with spaces'],
            ['"value with spaces and \\" escaped"', 'value with spaces and " escaped'],
            ['value\\ with\\ spaces', 'value with spaces'],
            ['no\\"spaces\\\'here', 'no"spaces\'here'],
            ["nospaces\\'here", "nospaces'here"],
            ['no\\"spaceshere', 'no"spaceshere'],
            ['no\\\\spaceshere', 'no\\spaceshere'],
            ["''", '']
        ];
    }

    /**
     * @test
     * @dataProvider quotedValues
     */
    public function quotedArgumentValuesAreCorrectlyParsedWhenPassingTheCommandAsString($quotedArgument, $expectedResult)
    {
        $methodParameters = [
            'requiredArgument1' => ['optional' => false, 'type' => 'string'],
            'requiredArgument2' => ['optional' => false, 'type' => 'string'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedArguments = ['requiredArgument1' => 'firstArgumentValue', 'requiredArgument2' => $expectedResult];

        $request = $this->requestBuilder->build('acme.test:default:list firstArgumentValue ' . $quotedArgument);
        self::assertEquals($expectedArguments, $request->getArguments());
    }

    /**
     * @test
     */
    public function arrayArgumentIsParsedCorrectly()
    {
        $methodParameters = [
            'a1' => ['optional' => false, 'type' => 'array'],
            'a2' => ['optional' => true, 'type' => 'array'],
        ];
        $this->mockCommandManager->expects(self::once())->method('getCommandMethodParameters')->with('Acme\Test\Command\DefaultCommandController', 'listCommand')->will(self::returnValue($methodParameters));

        $expectedArguments = [
            'a1' => ['1', 'x'], 'a2' => ['y', 'z']
        ];

        $request = $this->requestBuilder->build('acme.test:default:list --a1 1 --a2 y --a1 x --a2 z');
        self::assertEquals($expectedArguments, $request->getArguments());
    }
}
