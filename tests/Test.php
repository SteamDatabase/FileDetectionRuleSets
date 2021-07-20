<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

$Detector = new FileDetector( __DIR__ . '/../rules.ini' );

$TestsIterator = new DirectoryIterator( __DIR__ . '/types' );

$FailingTests = [];
$PassedTests = 0;
$TotalTestsRun = 0;

foreach( $TestsIterator as $File )
{
	if( $File->getExtension() !== 'txt' )
	{
		continue;
	}

	$TestFilePaths = file( $File->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$ExpectedType = $File->getBasename( '.txt' );

	if( $ExpectedType === '_NonMatchingTests' )
	{
		foreach( $TestFilePaths as $Path )
		{
			$TotalTestsRun++;
			$Actual = $Detector->GetMatchingRuleForFilePath( $Path );

			if( $Actual !== null )
			{
				$FailingTests[] = "Path \"$Path\" returned \"$Actual\" but it should not have matched anything";
			}
			else
			{
				$PassedTests++;
			}
		}

		continue;
	}

	foreach( $TestFilePaths as $Path )
	{
		$TotalTestsRun++;
		$Actual = $Detector->GetMatchingRuleForFilePath( $Path );

		if( $Actual !== $ExpectedType )
		{
			$FailingTests[] = "Path \"$Path\" does not match for \"$ExpectedType\"";
		}
		else
		{
			$PassedTests++;
		}
	}
}

echo "{$PassedTests} tests out of {$TotalTestsRun} tests passed.\n";

if( !empty( $FailingTests ) )
{
	echo "\n" . count( $FailingTests ) . " tests failed:\n";

	foreach( $FailingTests as $Test )
	{
		echo $Test . "\n";
	}

	exit( 1 );
}
