<?php
declare(strict_types=1);

if( empty( $Detector ) )
{
	echo 'Run "Test.php"';
	exit( 1 );
}

$TestsIterator = new DirectoryIterator( __DIR__ . '/twopass' );

$SeenTestTypes = [];
$FailingTests = [];
$FalsePositives = [];
$PassedTests = 0;
$TotalTestsRun = 0;

$AllowedFalsePositives = [
	"Engine.AdobeAIR"=>["Engine.AdobeFlash"],
	"Engine.FNA"=>["Engine.XNA","Engine.MonoGame"],
	"Engine.MonoGame"=>["Engine.XNA"],
	"Engine.Heaps"=>["Engine.AdobeAIR","Engine.AdobeFlash"],
	"Engine.XNA"=>["Engine.FNA","Engine.MonoGame"]
	// "Engine.LIME_OR_OPENFL"=>["Engine.AdobeAIR","Engine.AdobeFlash"],
	// "Engine.PyGame"=>["Engine.RenPy"],
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
		if($Key != $ExpectedType && str_contains($Key,"Engine."))
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
