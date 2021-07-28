<?php
declare(strict_types=1);

require __DIR__ . '/FileDetector.php';

$Detector = new FileDetector( __DIR__ . '/../rules.ini' );

$TestsIterator = new DirectoryIterator( __DIR__ . '/twopass' );

$SeenTestTypes = [];
$FailingTests = [];
$FalsePositives = [];
$PassedTests = 0;
$TotalTestsRun = 0;

$AllowedFalsePositives = [
	"GameEngine.AdobeAIR"=>["GameEngine.AdobeFlash"],
	"GameEngine.FNA"=>["GameEngine.XNA","GameEngine.MonoGame"],
	"GameEngine.XNA"=>["GameEngine.FNA","GameEngine.MonoGame"],
	"GameEngine.HEAPS"=>["GameEngine.AdobeAIR","GameEngine.AdobeFlash"],
	"GameEngine.LIME_OR_OPENFL"=>["GameEngine.AdobeAIR","GameEngine.AdobeFlash"],
	"GameEngine.PyGame"=>["GameEngine.RenPy"],
	"GameEngine.MonoGame"=>["GameEngine.XNA"]
];

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

	$MatchStr = "";
	foreach($Matches as $Key=>$Count){
		if($MatchStr != ""){
			$MatchStr .= ", ";
		}
		$MatchStr .= $Key;
	}

	if( isset( $Matches[ $ExpectedType ] ) )
	{
		$PassedTests++;
	}
	else
	{
		$FailingTests[] = "Failed to match $ExpectedType for $Title --> " . $MatchStr;
	}
	
	foreach($Matches as $Key=>$Count){
		if($Key != $ExpectedType && str_contains($Key,"GameEngine."))
		{
			$pass = false;
			if(isset($AllowedFalsePositives[$ExpectedType]))
			{
				if(in_array($Key, $AllowedFalsePositives[$ExpectedType])){
					$pass = true;
				}
			}
			if(!$pass){
				$FalsePositives[] = "FALSE Positive? $ExpectedType for $Title --> " . $MatchStr;
			}
		}
	}
}

echo "{$PassedTests} tests out of {$TotalTestsRun} tests passed.\n";

if( !empty( $FailingTests ) || !empty ($FalsePositives) )
{
	if(!empty( $FailingTests)){
		err( count( $FailingTests ) . " tests failed:" );
	
		foreach( $FailingTests as $Test )
		{
			err( $Test );
		}
	}
	if(!empty($FalsePositives)){
		err( count( $FalsePositives) . " potential false positives:" );
		foreach( $FalsePositives as $FalsePos)
		{
			err( $FalsePos);
		}
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
