<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

$Detector = new FileDetector( __DIR__ . '/../rules.ini' );

$TestsIterator = new DirectoryIterator( __DIR__ . '/twopass' );

$SeenTestTypes = [];
$FailingTests = [];
$PassedTests = 0;
$TotalTestsRun = 0;

foreach( $TestsIterator as $File )
{
	if( $File->getExtension() !== 'txt' )
	{
		continue;
	}

	echo($File."\n");
	
	$TestFilePaths = file( $File->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$BaseName = $File->getBasename( '.txt' );
	$Bits = explode(".",$BaseName);
	$Title = $Bits[2];
	$ExpectedType = $Bits[0].".".$Bits[1];

	$AlreadySeenStrings = [];

	$Evidence = [];
	$NumFiles = 0;
	$Passed = false;
	
	foreach( $TestFilePaths as $Path )
	{
		if( isset( $AlreadySeenStrings[ $Path ] ) )
		{
			$FailingTests[] = "Path \"$Path\" in \"$File\" is defined more than once";
		}
		
		$NumFiles += 1;

		$AlreadySeenStrings[ $Path ] = true;

		$Actual = $Detector->GetMatchingRuleForFilePath( $Path );

		if( preg_last_error() !== PREG_NO_ERROR )
		{
			throw new RuntimeException( 'PCRE returned an error: ' . preg_last_error() . ' - ' . preg_last_error_msg() );
		}

		if( $Actual !== null )
		{
			if( strstr($Actual, "Evidence.") !== false)
			{
				//Log this evidence
				if(!isset($Evidence[$Actual])){
					$Evidence[$Actual] = 0;
				}
				$Evidence[$Actual] += 1;
			}
			else
			{
				//We got a direct match to a one-shot test, stop now
				if( $Actual === $ExpectedType )
				{
					$Passed = true;
					break;
				}
			}
		}
	}
	$TotalTestsRun++;
	
	//No match yet, but we have some evidence
	if(!$Passed && !empty($Evidence))
	{
		$Actual = $Detector->DetermineMatchFromEvidence($NumFiles, $Evidence);
		if( $Actual === $ExpectedType )
		{
			$Passed = true;
		}
	}
	
	if($Passed)
	{
		$PassedTests++;
	}
	else
	{
		$FailingTests[] = "File \"$File\" returned \"$Actual\" but it should have matched $ExpectedType";
	}

	if( !empty( $TestFilePaths ) )
	{
		$SeenTestTypes[ $ExpectedType ] = true;
	}
}

echo "{$PassedTests} tests out of {$TotalTestsRun} tests passed.\n";

if( !empty( $FailingTests ) )
{
	err( count( $FailingTests ) . " tests failed:" );

	foreach( $FailingTests as $Test )
	{
		err( $Test );
	}

	exit( 1 );
}

function err( string $Message ) : void
{
	if( getenv( 'CI' ) !== false )
	{
		echo "::error::" . $Message . PHP_EOL;
	}
	else
	{
		fwrite( STDERR, $Message . PHP_EOL );
	}
}
