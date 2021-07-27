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

	$TestFilePaths = file( $File->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	$BaseName = $File->getBasename( '.txt' );
	$Bits = explode(".",$BaseName);
	$Title = $Bits[2];
	$ExpectedType = $Bits[0].".".$Bits[1];

	$FileMatch = "";

	$Matches = $Detector->GetMatchesForFileList( $TestFilePaths );

	if(count($Matches) > 0){
		$bits = "";
		foreach($Matches as $key=>$count){
			if($bits != ""){
				$bits .= ", ";
			}
			$bits .= $key.":".$count;
		}
		$FileMatch .= " --> " . $bits;
	}

	$TotalTestsRun++;

	if( isset( $Matches[ $ExpectedType ] ) )
	{
		$PassedTests++;
	}
	else
	{
		$MatchStr = "";
		foreach($Matches as $Key=>$Count){
			if($MatchStr != ""){
				$MatchStr .= ", ";
			}
			$MatchStr .= $Key;
		}
		$FailingTests[] = "Failed to match $ExpectedType for $Title --> " . $MatchStr;
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
