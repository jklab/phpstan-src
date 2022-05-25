<?php declare(strict_types = 1);

namespace PHPStan\Command\ErrorFormatter;

use PHPStan\Analyser\Error;
use PHPStan\Command\AnalysisResult;
use PHPStan\File\FuzzyRelativePathHelper;
use PHPStan\File\NullRelativePathHelper;
use PHPStan\Testing\ErrorFormatterTestCase;
use function putenv;
use function sprintf;
use const PHP_VERSION_ID;

class TableErrorFormatterTest extends ErrorFormatterTestCase
{

	protected function tearDown(): void
	{
		putenv('COLUMNS');
	}

	public function dataFormatterOutputProvider(): iterable
	{
		yield [
			'No errors',
			0,
			0,
			0,
			'
 [OK] No errors

',
		];

		yield [
			'One file error',
			1,
			1,
			0,
			' ------ -------------------------------------------------------------------
  Line   folder with unicode 😃/file name with "spaces" and unicode 😃.php
 ------ -------------------------------------------------------------------
  4      Foo
 ------ -------------------------------------------------------------------


 [ERROR] Found 1 error

',
		];

		yield [
			'One generic error',
			1,
			0,
			1,
			' -- ---------------------
     Error
 -- ---------------------
     first generic error
 -- ---------------------


 [ERROR] Found 1 error

',
		];

		yield [
			'Multiple file errors',
			1,
			4,
			0,
			' ------ -------------------------------------------------------------------
  Line   folder with unicode 😃/file name with "spaces" and unicode 😃.php
 ------ -------------------------------------------------------------------
  2      Bar
         Bar2
  4      Foo
 ------ -------------------------------------------------------------------

 ------ ---------
  Line   foo.php
 ------ ---------
  1      Foo
  5      Bar
         Bar2
 ------ ---------

 [ERROR] Found 4 errors

',
		];

		yield [
			'Multiple generic errors',
			1,
			0,
			2,
			' -- ----------------------
     Error
 -- ----------------------
     first generic error
     second generic error
 -- ----------------------


 [ERROR] Found 2 errors

',
		];

		yield [
			'Multiple file, multiple generic errors',
			1,
			4,
			2,
			' ------ -------------------------------------------------------------------
  Line   folder with unicode 😃/file name with "spaces" and unicode 😃.php
 ------ -------------------------------------------------------------------
  2      Bar
         Bar2
  4      Foo
 ------ -------------------------------------------------------------------

 ------ ---------
  Line   foo.php
 ------ ---------
  1      Foo
  5      Bar
         Bar2
 ------ ---------

 -- ----------------------
     Error
 -- ----------------------
     first generic error
     second generic error
 -- ----------------------

 [ERROR] Found 6 errors

',
		];
	}

	/**
	 * @dataProvider dataFormatterOutputProvider
	 *
	 */
	public function testFormatErrors(
		string $message,
		int $exitCode,
		int $numFileErrors,
		int $numGenericErrors,
		string $expected,
	): void
	{
		if (PHP_VERSION_ID >= 80100) {
			self::markTestSkipped('Skipped on PHP 8.1 because of different result');
		}
		$relativePathHelper = new FuzzyRelativePathHelper(new NullRelativePathHelper(), self::DIRECTORY_PATH, [], '/');
		$formatter = new TableErrorFormatter($relativePathHelper, new CiDetectedErrorFormatter(
			new GithubErrorFormatter($relativePathHelper),
			new TeamcityErrorFormatter($relativePathHelper),
		), false, null);

		$this->assertSame($exitCode, $formatter->formatErrors(
			$this->getAnalysisResult($numFileErrors, $numGenericErrors),
			$this->getOutput(),
		), sprintf('%s: response code do not match', $message));

		$this->assertEquals($expected, $this->getOutputContent(), sprintf('%s: output do not match', $message));
	}

	public function testEditorUrlWithTrait(): void
	{
		$relativePathHelper = new FuzzyRelativePathHelper(new NullRelativePathHelper(), self::DIRECTORY_PATH, [], '/');
		$formatter = new TableErrorFormatter($relativePathHelper, new CiDetectedErrorFormatter(
			new GithubErrorFormatter($relativePathHelper),
			new TeamcityErrorFormatter($relativePathHelper),
		), false, 'editor://%file%/%line%');
		$error = new Error('Test', 'Foo.php (in context of trait)', 12, true, 'Foo.php', 'Bar.php');
		$formatter->formatErrors(new AnalysisResult([$error], [], [], [], false, null, true), $this->getOutput());

		$this->assertStringContainsString('Bar.php', $this->getOutputContent());
	}

	public function testBug6727(): void
	{
		putenv('COLUMNS=30');
		$relativePathHelper = new FuzzyRelativePathHelper(new NullRelativePathHelper(), self::DIRECTORY_PATH, [], '/');
		$formatter = new TableErrorFormatter($relativePathHelper, new CiDetectedErrorFormatter(
			new GithubErrorFormatter($relativePathHelper),
			new TeamcityErrorFormatter($relativePathHelper),
		), false, null);
		$formatter->formatErrors(
			new AnalysisResult(
				[
					new Error(
						'Method MissingTypehintPromotedProperties\Foo::__construct() has parameter $foo with no value type specified in iterable type array.',
						'/var/www/html/app/src/Foo.php (in context of class App\Foo\Bar)',
						5,
					),
				],
				[],
				[],
				[],
				false,
				null,
				true,
			),
			$this->getOutput(),
		);
		self::expectNotToPerformAssertions();
	}

}
